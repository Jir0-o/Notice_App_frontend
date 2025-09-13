<?php

namespace App\Jobs;

use App\Mail\NoticePublishedMail;
use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class SendNoticeEmailsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $noticeId;
    public ?array $propagationIds; // optional subset

    public function __construct(int $noticeId, ?array $propagationIds = null)
    {
        $this->noticeId = $noticeId;
        $this->propagationIds = $propagationIds;
    }

    public function handle()
    {
        $notice = Notice::with('propagations.user')->find($this->noticeId);
        if (!$notice) {
            Log::warning("Notice not found in SendNoticeEmailsJob: {$this->noticeId}");
            return;
        }

        $propagations = $notice->propagations;

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



            try {
                if ($prop->user && $prop->user->fcm_token) {
                    $this->sendFcmNotification(
                        $prop->user->fcm_token,
                        'New Notice Published',
                        $notice->title
                    );
                }
                Mail::to($email)->send(new NoticePublishedMail($notice, $recipientName));
            } catch (\Throwable $e) {
                Log::error("Failed to send notice email to {$email}: " . $e->getMessage());
            }
        }
    }

    private function getFcmAccessToken()
    {
        $client = new GoogleClient();
        $client->setAuthConfig(env('FIREBASE_CREDENTIALS'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();
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
                        'screen' => 'details',
                        'id' => $this->noticeId,
                    ]),
                ],
            ],
        ];

        Http::withToken($accessToken)->post($url, $message);
    }
}
