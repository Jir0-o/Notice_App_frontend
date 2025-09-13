<?php

namespace App\Mail;

use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NoticePublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $notice;
    public $recipientName;

    public function __construct(Notice $notice, $recipientName = null)
    {
        $this->notice = $notice;
        $this->recipientName = $recipientName;
    }

    public function build()
    {
        return $this->subject('New Notice: ' . $this->notice->title)
            ->view('emails.notice_published')
            ->with([
                'notice' => $this->notice,
                'recipientName' => $this->recipientName,
            ]);
    }
}
