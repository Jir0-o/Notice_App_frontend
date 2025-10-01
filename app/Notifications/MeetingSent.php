<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\MeetingDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class MeetingSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MeetingDetail $meetingDetails) {}

    public function via($notifiable)
    {
        // Database notification, you can add 'mail' if you want email also
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'meeting_id' => $this->meetingDetails->id,
            'title'      => $this->meetingDetails->title,
            'capacity'   => $this->meetingDetails->capacity,
        ];
    }
}
