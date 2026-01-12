<?php

namespace App\Jobs;

use App\Mail\MeetingPublishedMail;
use App\Models\Meeting;
use App\Models\MeetingDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class SendMeetingEmailsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $meetingDetailsId;
    public ?array $propagationIds; // optional subset

    public function __construct(int $meetingDetailsId, ?array $propagationIds = null)
    {
        $this->meetingDetailsId = $meetingDetailsId;
        $this->propagationIds = $propagationIds;
    }

    public function handle()
    {
        Log::debug("Here");

        $meetingDetails = MeetingDetail::with('propagations.user')->find($this->meetingDetailsId);
        if (!$meetingDetails) {
            Log::warning("Meeting not found in SendMeetingEmailsJob: {$this->meetingDetailsId}");
            return;
        }

        $propagations = $meetingDetails->propagations;

        if (is_array($this->propagationIds)) {
            $propagations = $propagations->whereIn('id', $this->propagationIds);
        }

        foreach ($propagations as $prop) {
            $email = $prop->user_email;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning("Invalid email skipped: {$email}");
                continue;
            }

            $recipientName = $prop->name ?: ($prop->user?->name ?? null);

            Log::debug( $meetingDetails->title);

            try {
                if ($prop->user && $prop->user->fcm_token) {
                    $this->sendFcmNotification(
                        $prop->user->fcm_token,
                        'New Meeting Published',
                        $meetingDetails->title
                    );
                }
                Mail::to($email)->send(new MeetingPublishedMail($meetingDetails, $recipientName));
            } catch (\Throwable $e) {
                Log::error("Failed to send meeting email to {$email}: " . $e->getMessage());
            }
        }
    }

    private function getFcmAccessToken()
    {
        $client = new GoogleClient();
        $client->setAuthConfig(env('FIREBASE_CREDENTIALS'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();
        Log::debug("FCM Access Token: " . $accessToken['access_token']);
        return $accessToken['access_token'];
    }

    private function sendFcmNotification($token, $title, $body)
    {
        if (!$token) {
            return;
        }

        $accessToken = $this->getFcmAccessToken();
        $projectId = 'sicip-push-notification';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'payload' => json_encode([
                        'screen' => 'home',
                        'id' => $this->meetingDetailsId,
                    ]),
                ],
            ],
        ];

        Http::withToken($accessToken)->post($url, $message);
    }
}