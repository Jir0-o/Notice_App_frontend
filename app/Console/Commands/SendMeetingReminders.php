<?php

namespace App\Console\Commands;

use App\Jobs\SendMeetingReminderJob;
use App\Models\MeetingDetail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';
    protected $description = 'Dispatch reminder notifications for meetings starting in 30 minutes';

    public function handle(): int
    {
         $now = Carbon::now('Asia/Dhaka');
        $from = $now->copy()->addMinutes(29);
        $to   = $now->copy()->addMinutes(31);

        Log::info('called');

        $due = MeetingDetail::query()
            ->whereDate('date', $now->toDateString())
            ->whereBetween('start_time', [
                $from->format('H:i:00'),
                $to->format('H:i:59'),
            ])
            ->withCount('propagations')
            ->get();
        Log::info($due);

        foreach ($due as $detail) {
            dispatch(new SendMeetingReminderJob($detail->id));
        }

        $this->info('Reminders dispatched: '.$due->count());

        return self::SUCCESS;
    }
}