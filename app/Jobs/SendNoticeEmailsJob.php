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

            Log::debug("FCM access token fetched successfully.". " Expires in {$token['expires_in']} seconds.");

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
                        'id' => (string) $this->noticeId,
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
