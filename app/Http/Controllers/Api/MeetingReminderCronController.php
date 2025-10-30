<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendMeetingReminderJob;
use App\Models\MeetingDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeetingReminderCronController extends Controller
{
    // GET /api/cron/meeting-reminders
    public function __invoke(Request $request)
    {
        $now    = Carbon::now();                     // current server time
        $from   = $now->copy()->addMinutes(29);      // 29
        $to     = $now->copy()->addMinutes(31);      // +/- 1min window

        // meetings today whose start_time is between now+29 and now+31
        $due = MeetingDetail::query()
            ->whereDate('date', $now->toDateString())
            ->whereBetween('start_time', [
                $from->format('H:i:00'),
                $to->format('H:i:59'),
            ])
            ->withCount('propagations')
            ->get();

        foreach ($due as $detail) {
            dispatch(new SendMeetingReminderJob($detail->id));
        }

        return response()->json([
            'success' => true,
            'run_at'  => $now->toDateTimeString(),
            'window'  => [
                'from' => $from->toTimeString(),
                'to'   => $to->toTimeString(),
            ],
            'dispatched' => $due->pluck('id'),
            'count'      => $due->count(),
        ]);
    }
}