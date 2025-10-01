<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\MeetingDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $meetingDetails;
    public $recipientName;

    public function __construct(MeetingDetail $meetingDetails, $recipientName = null)
    {
        $this->meetingDetails = $meetingDetails;
        $this->recipientName = $recipientName;
    }

    public function build()
    {
        return $this->subject('New Meeting: ' . $this->meetingDetails->title)
            ->view('emails.meeting_published')
            ->with([
                'meeting' => $this->meetingDetails,
                'recipientName' => $this->recipientName,
            ]);
    }
}
