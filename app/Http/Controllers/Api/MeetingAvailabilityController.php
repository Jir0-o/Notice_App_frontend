<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingDetail;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\MeetingDetailspropagation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'include'      => ['nullable','string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $date    = $request->input('date');
        $startH  = $request->input('start_time'); // requested start
        $endH    = $request->input('end_time');   // requested end
        $per     = (int) $request->input('per_page', 200);
        $include = (string) $request->input('include', '');
        $allowBackToBack = $request->boolean('allow_back_to_back', true);

        // with cooldown:
        // overlap if: existing.start_time < req.end
        //         AND (existing.end_time + 15min) > req.start
        // back-to-back allowed => strictly < and >
        // back-to-back not allowed => <= and >=
        [$lt, $gt] = $allowBackToBack ? ['<', '>'] : ['<=', '>='];

        // ---- Busy meetings (with 15 min buffer) ----
        $busyMeetingIds = \App\Models\MeetingDetail::query()
            ->whereDate('date', $date)
            ->where('start_time', $lt, $endH)
            ->whereRaw("DATE_ADD(end_time, INTERVAL 15 MINUTE) {$gt} ?", [$startH])
            ->pluck('meeting_id')
            ->unique()
            ->values();

        // ---- Free meetings ----
        $freeQuery = \App\Models\Meeting::query()
            ->where('is_active', '=', 1)
            ->when($request->filled('capacity_min'), fn ($q) =>
                $q->where('capacity', '>=', (int) $request->input('capacity_min'))
            )
            ->when($request->filled('capacity_max'), fn ($q) =>
                $q->where('capacity', '<=', (int) $request->input('capacity_max'))
            )
            ->whereDoesntHave('meetingDetails', function ($md) use ($date, $startH, $endH, $lt, $gt) {
                $md->whereDate('date', $date)
                ->where('start_time', $lt, $endH)
                ->whereRaw("DATE_ADD(end_time, INTERVAL 15 MINUTE) {$gt} ?", [$startH]);
            })
            ->orderBy('title', 'asc');

        $paginator = $freeQuery->paginate($per)->appends($request->query());

        // ---- Optional: busy details payload ----
        $busyPayload = null;
        if (str_contains($include, 'busy_details') && $busyMeetingIds->isNotEmpty()) {
            $busyMeetings = \App\Models\Meeting::query()
                ->whereIn('id', $busyMeetingIds)
                ->with(['meetingDetails' => function ($md) use ($date, $startH, $endH, $lt, $gt) {
                    $md->whereDate('date', $date)
                    ->where('start_time', $lt, $endH)
                    ->whereRaw("DATE_ADD(end_time, INTERVAL 15 MINUTE) {$gt} ?", [$startH])
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
                            'cooldown_till' => $d->end_time
                                ? \Carbon\Carbon::parse($d->end_time)->addMinutes(15)->format('H:i')
                                : null,
                        ];
                    })->values(),
                ];
            })->values();
        }

        return response()->json([
            'data'  => \App\Http\Resources\MeetingResource::collection($paginator->items()),
            'ok'    => true,

            'query_window' => [
                'date'       => $date,
                'start_time' => $startH,
                'end_time'   => $endH,
                'cooldown'   => '15 minutes',
            ],
            'filters' => [
                'is_active'    => $request->input('is_active'),
                'capacity_min' => $request->input('capacity_min'),
                'capacity_max' => $request->input('capacity_max'),
            ],
            'include'       => $include,
            'busy_meetings' => $busyPayload,

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


    public function checkUser(Request $request)
    {
        $data = $request->validate([
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',

            'user_id'    => 'nullable|integer|exists:users,id',
            'user_name'  => 'nullable|string|max:255',
            'user_email' => 'nullable|email',
        ]);

        $date      = Carbon::parse($data['date'])->toDateString();
        $startTime = $data['start_time'];
        $endTime   = $data['end_time'];

        $hasId = !empty($data['user_id']);
        $hasNE = !empty($data['user_name']) && !empty($data['user_email']);

        if (!$hasId && !$hasNE) {
            return response()->json([
                'success' => false,
                'message' => 'Provide user_id or user_name + user_email.',
            ], 422);
        }

        $q = MeetingDetailspropagation::query()
            ->whereHas('meetingDetail', function ($md) use ($date, $startTime, $endTime) {
                $md->whereDate('date', $date)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time',   '>', $startTime);
            })
            ->with([
                'meetingDetail:id,meeting_id,title,date,start_time,end_time',
                'meetingDetail.meeting:id,title',
            ])
            ->select(['id','user_id','user_name','user_email','meeting_detail_id']);

        // filter by user identity
        $q->where(function ($w) use ($hasId, $hasNE, $data) {
            if ($hasId) {
                $w->orWhere('user_id', (int) $data['user_id']);
            }
            if ($hasNE) {
                $w->orWhere(function ($x) use ($data) {
                    $x->where('user_name', $data['user_name'])
                      ->where('user_email', $data['user_email']);
                });
            }
        });

        $hits = $q->get();

        if ($hits->isEmpty()) {
            return response()->json([
                'success' => true,
                'busy'    => false,
                'message' => 'User is free in this time window.',
            ]);
        }

        $conflicts = $hits->map(function ($c) {
            return [
                'propagation_id'    => $c->id,
                'meeting_detail_id' => $c->meeting_detail_id,
                'meeting_title'     => optional($c->meetingDetail->meeting)->title,
                'detail_title'      => $c->meetingDetail->title ?? null,
                'busy_date'         => optional($c->meetingDetail->date)?->toDateString(),
                'busy_start'        => optional($c->meetingDetail->start_time)?->format('H:i'),
                'busy_end'          => optional($c->meetingDetail->end_time)?->format('H:i'),
            ];
        })->values();

        $name = $hits->first()->user_name
            ?? ($hasNE ? $data['user_name'] : 'This user');

        return response()->json([
            'success'   => true,
            'busy'      => true,
            'message'   => "{$name} is already in another meeting in this time window.",
            'conflicts' => $conflicts,
        ]);
    }
}