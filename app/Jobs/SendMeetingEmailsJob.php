<?php

namespace App\Jobs;

use App\Mail\MeetingPublishedMail;
use App\Models\MeetingDetail;
use Google\Client as GoogleClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMeetingEmailsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $meetingDetailsId;
    public ?array $propagationIds;

    public function __construct(int $meetingDetailsId, ?array $propagationIds = null)
    {
        $this->meetingDetailsId = $meetingDetailsId;
        $this->propagationIds = $propagationIds;
    }

    public function handle()
    {
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

            // 1) Push notification (never block email if push fails)
            if ($prop->user && $prop->user->fcm_token) {
                try {
                    $this->sendFcmNotification(
                        $prop->user->fcm_token,
                        'New Meeting Published',
                        $meetingDetails->title
                    );
                } catch (\Throwable $e) {
                    Log::error("FCM push failed for {$email}: " . $e->getMessage());
                }
            }

            // 2) Email send
            try {
                Mail::to($email)->send(new MeetingPublishedMail($meetingDetails, $recipientName));
            } catch (\Throwable $e) {
                Log::error("Failed to send meeting email to {$email}: " . $e->getMessage());
            }
        }
    }

    private function getFcmAccessToken(): ?string
    {
        $credentials = config('services.firebase.credentials');

        if (!$credentials) {
            Log::error("FIREBASE_CREDENTIALS is missing (config/services.php).");
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($credentials); // path string OR decoded array both work
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            if (!is_array($token) || empty($token['access_token'])) {
                Log::error("Failed to fetch FCM access token.", ['response' => $token]);
                return null;
            }

            return $token['access_token'];
        } catch (\Throwable $e) {
            Log::error("FCM auth exception: " . $e->getMessage());
            return null;
        }
    }

    private function sendFcmNotification(string $token, string $title, string $body): void
    {
        $accessToken = $this->getFcmAccessToken();
        if (!$accessToken) {
            // Don’t throw, just log and exit
            Log::warning("Skipping FCM push: access token not available.");
            return;
        }

        $projectId = config('services.firebase.project_id', 'sicip-push-notification');
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
                        'id' => (string) $this->meetingDetailsId,
                    ]),
                ],
            ],
        ];

        $res = Http::withToken($accessToken)->post($url, $message);

        if (!$res->successful()) {
            Log::error("FCM send failed", [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
        }
    }
}