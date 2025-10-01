<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingDetail;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
    public function free(Request $request)
    {
        // ---- Validate inputs ----
        $v = Validator::make($request->all(), [
            'date'        => ['required','date'],          // YYYY-MM-DD
            'start_time'  => ['required','date_format:H:i'],
            'end_time'    => ['required','date_format:H:i','after:start_time'],

            // optional filters
            'is_active'    => ['nullable','boolean'],
            'capacity_min' => ['nullable','integer','min:0'],
            'capacity_max' => ['nullable','integer','min:0'],

            'per_page'     => ['nullable','integer','min:1','max:200'],
            'include'      => ['nullable','string'],       // e.g., 'busy_details'
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $date       = $request->input('date');        // '2025-11-10'
        $startH     = $request->input('start_time');  // '09:00'
        $endH       = $request->input('end_time');    // '10:00'
        $per        = (int) $request->input('per_page', 200);
        $include    = (string) $request->input('include', '');
        $allowBackToBack = $request->boolean('allow_back_to_back', true);

        // Choose operators based on the policy
        // half-open: start < end && end > start
        // closed:    start <= end && end >= start
        [$lt, $gt] = $allowBackToBack ? ['<', '>'] : ['<=', '>='];

        // ---- Busy meetings: any detail on that date that overlaps the time window ----
        // Overlap rule: existing.start_time < endH AND existing.end_time > startH
        // --- Busy meetings for EXACT date + time window ---
        $busyMeetingIds = \App\Models\MeetingDetail::query()
            ->whereDate('date', $date)
            ->where('start_time', $lt,  $endH)
            ->where('end_time',   $gt,  $startH)
            ->pluck('meeting_id')
            ->unique()
            ->values();

        // ---- Free meetings base query (no overlapping details on that date/time) ----
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
            ->whereDoesntHave('meetingDetails', function ($md) use ($date, $startH, $endH, $lt, $gt) {
                $md->whereDate('date', $date)
                ->where('start_time', $lt, $endH)
                ->where('end_time',   $gt, $startH);
            })
            ->orderBy('title', 'asc');

        $paginator = $freeQuery->paginate($per)->appends($request->query());

        // ---- Optional: include busy meetings + their blocking details ----
        $busyPayload = null;
        if (str_contains($include, 'busy_details') && $busyMeetingIds->isNotEmpty()) {
            $busyMeetings = \App\Models\Meeting::query()
                    ->whereIn('id', $busyMeetingIds)
                    ->with(['meetingDetails' => function ($md) use ($date, $startH, $endH, $lt, $gt) {
                        $md->whereDate('date', $date)
                        ->where('start_time', $lt, $endH)
                        ->where('end_time',   $gt, $startH)
                    ->orderBy('date', 'asc')
                    ->orderBy('start_time', 'asc');
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
                            'date'       => $d->date?->toDateString(),
                            'start_time' => $d->start_time?->format('H:i'),
                            'end_time'   => $d->end_time?->format('H:i'),
                        ];
                    })->values(),
                ];
            })->values();
        }

        // ---- Flat response (no "meta" wrapper) ----
        return response()->json([
            'data'  => MeetingResource::collection($paginator->items()),
            'ok'    => true,

            'query_window' => [
                'date'       => $date,
                'start_time' => $startH,
                'end_time'   => $endH,
            ],
            'filters' => [
                'is_active'    => $request->input('is_active'),
                'capacity_min' => $request->input('capacity_min'),
                'capacity_max' => $request->input('capacity_max'),
            ],
            'include'       => $include,
            'busy_meetings' => $busyPayload, // null unless include=busy_details

            // Pagination (top-level)
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'current_page' => $paginator->currentPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }
}