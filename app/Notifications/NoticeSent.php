<?php

namespace App\Notifications;

use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NoticeSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Notice $notice) {}

    public function via($notifiable)
    {
        // Database notification, you can add 'mail' if you want email also
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'notice_id' => $this->notice->id,
            'title'     => $this->notice->title,
            'excerpt'   => str($this->notice->description)->limit(120),
        ];
    }
}
