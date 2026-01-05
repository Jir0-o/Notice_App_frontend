<?php

namespace App\Jobs;

use App\Models\MeetingDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class SendMeetingReminderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $meetingDetailId;

    public function __construct(int $meetingDetailId)
    {
        $this->meetingDetailId = $meetingDetailId;
    }

    public function handle()
    {
        $detail = MeetingDetail::with(['propagations.user','meeting'])->find($this->meetingDetailId);

        if (!$detail) {
            Log::warning("Reminder: meeting detail not found: {$this->meetingDetailId}");
            return;
        }

        // avoid double reminder: you can add a flag column later
        $props = $detail->propagations;
        foreach ($props as $prop) {
            Log::info($prop);

            $email = $prop->user_email;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $recipientName = $prop->user_name
                ?: ($prop->user?->name ?? 'Participant');

            try {
                  // push
                if ($prop->user && $prop->user->fcm_token) {
                    $this->sendFcmNotification(
                        $prop->user->fcm_token,
                        'Meeting in 30 minutes',
                        $detail->title
                    );
                }

                // email
                Mail::raw(
                    "Reminder: '{$detail->title}' at {$detail->start_time->format('H:i')} on {$detail->date->toDateString()}",
                    function ($msg) use ($email, $recipientName, $detail) {
                        $msg->to($email, $recipientName)
                            ->subject('Meeting Reminder: '.$detail->title);
                    }
                );

              

            } catch (\Throwable $e) {
                Log::error("Reminder send failed to {$email}: ".$e->getMessage());
            }
        }
    }

    private function sendFcmNotification($token, $title, $body)
    {
        if (!$token) return;

        Log::info($token);

        $accessToken = $this->getFcmAccessToken();
        $projectId   = 'sicip-push-notification';
        $url         = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'type' => 'meeting_reminder',
                    'meeting_detail_id' => $this->meetingDetailId,
                ],
            ],
        ];

        Http::withToken($accessToken)->post($url, $message);
    }

    private function getFcmAccessToken()
    {
        $client = new GoogleClient();
        $client->setAuthConfig(env('FIREBASE_CREDENTIALS'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }
}