<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FreeMeetingsRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\MeetingDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeetingAvailabilityController extends Controller
{
    /**
     * GET /v1/meetings/free
     * Query:
     *   - start=2025-10-05T09:00:00
     *   - end=2025-10-05T16:00:00
     * Optional filters:
     *   - is_active=1|0
     *   - capacity_min=...
     *   - capacity_max=...
     *   - per_page=15
     *   - include=busy_details  (returns which meetings are busy + which conflicting details)
     *
     * Overlap rule: existing.start < query_end && existing.end > query_start
     * Boundary-touch is allowed (no conflict if end == start).
     */
    public function free(FreeMeetingsRequest $request)
    {
        $start = Carbon::parse($request->input('start'));
        $end   = Carbon::parse($request->input('end'));
        $per   = (int) $request->input('per_page', 15);
        $include = (string) $request->input('include', ''); // e.g., 'busy_details'

        // Busy meetings: those that have ANY MeetingDetail overlapping the window
        $busyMeetingIds = MeetingDetail::query()
            ->where('start_date', '<', $end)   // strict <
            ->where('end_date',   '>', $start) // strict >
            ->pluck('meeting_id')
            ->unique()
            ->values();

        // Base query for free meetings (no overlapping details in the window)
        $freeQuery = Meeting::query()
            ->when(!is_null($request->input('is_active')), fn ($q) =>
                $q->where('is_active', (int) $request->boolean('is_active'))
            )
            ->when($request->filled('capacity_min'), fn ($q) =>
                $q->where('capacity', '>=', (int) $request->input('capacity_min'))
            )
            ->when($request->filled('capacity_max'), fn ($q) =>
                $q->where('capacity', '<=', (int) $request->input('capacity_max'))
            )
            ->whereDoesntHave('meetingDetails', function ($md) use ($start, $end) {
                $md->where('start_date', '<', $end)
                   ->where('end_date',   '>', $start);
            })
            ->orderBy('title', 'asc');

        $paginator = $freeQuery->paginate($per)->appends($request->query());

        // Optionally also return which meetings were busy + their conflicting details
        $busyPayload = null;
        if (str_contains($include, 'busy_details') && $busyMeetingIds->isNotEmpty()) {
            $busyMeetings = Meeting::query()
                ->whereIn('id', $busyMeetingIds)
                ->with(['meetingDetails' => function ($md) use ($start, $end) {
                    $md->where('start_date', '<', $end)
                       ->where('end_date',   '>', $start)
                       ->orderBy('start_date', 'asc');
                }])
                ->get(['id','title','capacity','is_active']);

            $busyPayload = $busyMeetings->map(function ($m) {
                return [
                    'meeting' => [
                        'id'        => $m->id,
                        'title'     => $m->title,
                        'capacity'  => $m->capacity,
                        'is_active' => (bool) $m->is_active,
                    ],
                    'blocking_details' => $m->meetingDetails->map(function ($d) {
                        return [
                            'detail_id'  => $d->id,
                            'title'      => $d->title,
                            'start_date' => $d->start_date?->toISOString(),
                            'end_date'   => $d->end_date?->toISOString(),
                        ];
                    })->values(),
                ];
            })->values();
        }

        return MeetingResource::collection($paginator)->additional([
            'ok'   => true,
            'meta' => [
                'query_window' => [
                    'start' => $start->toISOString(),
                    'end'   => $end->toISOString(),
                ],
                'filters' => [
                    'is_active'    => $request->input('is_active'),
                    'capacity_min' => $request->input('capacity_min'),
                    'capacity_max' => $request->input('capacity_max'),
                ],
                'include'       => $include,
                'busy_meetings' => $busyPayload, // null unless include=busy_details
            ],
        ]);
    }
}