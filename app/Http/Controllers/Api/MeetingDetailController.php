<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingDetailStoreRequest;
use App\Http\Requests\MeetingDetailUpdateRequest;
use App\Http\Resources\MeetingDetailResource;
use App\Models\Meeting;
use App\Models\MeetingDetail;
use App\Models\MeetingDetailspropagation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Requests\MeetingDetailsByUserRequest;
use Illuminate\Support\Facades\Auth;

class MeetingDetailController extends Controller
{
    /**
     * GET /v1/meetings/{meeting}/details
     * Query params:
     * - search=... (title)
     * - date_from=YYYY-MM-DD, date_to=YYYY-MM-DD
     * - upcoming=1 | past=1 (mutually exclusive; uses start_date)
     * - sort=start_date|-start_date|end_date|-end_date|title|-title|created_at|-created_at
     * - include=propagations
     * - per_page=15
     */
    public function index(Meeting $meeting, Request $request)
    {
        $q = $meeting->meetingDetails()->getQuery(); // scoped to meeting

        // Filters
        if ($s = $request->query('search')) {
            $q->where('title', 'like', "%{$s}%");
        }
        if ($from = $request->query('date_from')) {
            $q->whereDate('start_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('end_date', '<=', $to);
        }
        if ($request->boolean('upcoming')) {
            $q->where('start_date', '>=', now());
        } elseif ($request->boolean('past')) {
            $q->where('start_date', '<', now());
        }

        // Sorting
        $sort = $request->query('sort', 'start_date');
        $dir  = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col  = ltrim($sort, '-');
        if (!in_array($col, ['start_date','end_date','title','created_at'])) {
            $col = 'start_date';
        }
        $q->orderBy($col, $dir);

        // Includes
        $with = [];
        $include = (string) $request->query('include');
        if (str_contains($include, 'propagations')) {
            $with[] = 'propagations';
        }
        if ($with) $q->with($with);

        $q->withCount('propagations');

        $perPage   = (int) $request->query('per_page', 15);
        $page      = (int) $request->query('page', 1);
        $paginator = $q->paginate($perPage, ['*'], 'page', $page)->appends($request->query());

        return MeetingDetailResource::collection($paginator)->additional([
            'ok'   => true,
            'meta' => [
                'meeting_id' => $meeting->id,
                'filters'    => [
                    'search'    => $request->query('search'),
                    'date_from' => $request->query('date_from'),
                    'date_to'   => $request->query('date_to'),
                    'upcoming'  => $request->query('upcoming'),
                    'past'      => $request->query('past'),
                ],
                'sort'    => $sort,
                'include' => $include,
            ],
        ]);
    }

    /**
     * POST /v1/meetings/{meeting}/details
     * Optional nested "propagations" on create.
     */

	public function store(MeetingDetailStoreRequest $request)
	{
		$payload   = $request->validated();

		// Meeting context comes from request (NOT from route param)
		$meetingId = (int) $payload['meeting_id'];
		$meeting   = Meeting::select('id','title')->findOrFail($meetingId);

		// Window to book
		$start = Carbon::parse($payload['start_date']);
		$end   = Carbon::parse($payload['end_date']);

		// -------- CONFLICT CHECK (same meeting_id only) --------
		$conflictItems = [];
		foreach (($payload['propagations'] ?? []) as $idx => $p) {
			$q = MeetingDetailspropagation::query()
				->whereHas('meetingDetail', function ($md) use ($start, $end, $meetingId) {
					$md->where('meeting_id', $meetingId)
					->where('start_date', '<', $end)    // strict <
					->where('end_date',   '>', $start); // strict >
				})
				->where(function ($w) use ($p) {
					if (!empty($p['user_id'])) {
						$w->orWhere('user_id', (int) $p['user_id']);
					}
					if (!empty($p['user_name']) && !empty($p['user_email'])) {
						$w->orWhere(function ($x) use ($p) {
							$x->where('user_name', $p['user_name'])
							->where('user_email', $p['user_email']);
						});
					}
				});

			$hits = $q->with([
					'meetingDetail:id,meeting_id,start_date,end_date',
					'meetingDetail.meeting:id,title',
				])
				->get(['id','user_id','user_name','user_email','meeting_detail_id']);

			if ($hits->isNotEmpty()) {
				$conflictItems[] = [
					'payload_index' => $idx,
					'user_id'       => $p['user_id']   ?? null,
					'user_name'     => $p['user_name'] ?? null,
					'user_email'    => $p['user_email']?? null,
					'conflicts'     => $hits->map(function ($c) {
						return [
							'propagation_id'    => $c->id,
							'meeting_detail_id' => $c->meeting_detail_id,
							'meeting_title'     => optional($c->meetingDetail->meeting)->title,
							'busy_start'        => optional($c->meetingDetail->start_date)->toISOString(),
							'busy_end'          => optional($c->meetingDetail->end_date)->toISOString(),
						];
					})->values(),
				];
			}
		}

		// NOTE: use $conflictItems (not $conflicts)
		if (!empty($conflictItems)) {
			// Dedupe to user-centric list
			$busyUsersMap = [];
			foreach ($conflictItems as $c) {
				$label = !empty($c['user_id'])
					? "ID#{$c['user_id']}" . (!empty($c['user_name']) ? " ({$c['user_name']})" : '')
					: (trim(($c['user_name'] ?? '').' <'.($c['user_email'] ?? '').'>') ?: 'Unknown user');

				if (!isset($busyUsersMap[$label])) {
					$busyUsersMap[$label] = [
						'user_id'    => $c['user_id']   ?? null,
						'user_name'  => $c['user_name'] ?? null,
						'user_email' => $c['user_email']?? null,
						'blocking_windows' => [],
					];
				}
				foreach ($c['conflicts'] as $hit) {
					$busyUsersMap[$label]['blocking_windows'][] = $hit;
				}
			}
			$busyUsers = array_values($busyUsersMap);

			return response()->json([
				'ok'         => false,
				'message'    => "Time conflict: Users are already busy in this meeting’s requested window. For this meeting, change the room or the time.",
				'meeting'    => ['id' => $meeting->id, 'title' => $meeting->title],
				'conflicts'  => $conflictItems,
				'busy_users' => $busyUsers,
				'hint'       => 'Back-to-back is allowed. Adjust start/end or pick a different slot.',
			], 422);
		}

		// -------- CREATE (atomic) --------
		$detail = DB::transaction(function () use ($payload, $meetingId) {
			$detail = MeetingDetail::create([
				'meeting_id' => $meetingId,
				'title'      => $payload['title'],
				'start_date' => $payload['start_date'],
				'end_date'   => $payload['end_date'],
			]);

			foreach (($payload['propagations'] ?? []) as $p) {
				$detail->propagations()->create([
					'user_name'  => $p['user_name']  ?? null,
					'user_email' => $p['user_email'] ?? null,
					'is_read'    => (int) ($p['is_read'] ?? 0),
					'user_id'    => $p['user_id']    ?? null,
					'sent_at'    => $p['sent_at']    ?? null,
				]);
			}

			return $detail;
		});

		$detail->loadCount('propagations');

		$include = (string) $request->query('include');
		if (str_contains($include, 'propagations')) {
			$detail->load('propagations');
		}

		return (new MeetingDetailResource($detail))->additional([
			'ok'   => true,
			'meta' => ['message' => 'Meeting detail created successfully.'],
		]);
	}

	/**
	 * Update a detail (and its propagations) with conflict checks inside SAME meeting_id.
	 * Admin can move/update any propagation by global id. Supports sync delete.
	 */
	public function update(MeetingDetailUpdateRequest $request, $id)
	{
		// explicitly fetch detail by id from URL or request
		$detailId = $id ?? $request->input('id');
		$detail   = MeetingDetail::findOrFail($detailId);

		// Target meeting (allow reassign)
		$targetMeetingId = (int) $request->input('meeting_id', $detail->meeting_id);
		$targetMeeting   = Meeting::select('id','title')->findOrFail($targetMeetingId);

		// Effective window after update (use proposed values if provided)
		$effectiveStart = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : $detail->start_date;
		$effectiveEnd   = $request->filled('end_date')   ? Carbon::parse($request->input('end_date'))   : $detail->end_date;

		// ---- Build intended propagations set (for conflict check) ----
		$current   = $detail->propagations()->get(['id','user_id','user_name','user_email']);
		$intended  = $current->keyBy('id'); // id => obj
		$rows      = collect($request->input('propagations', []));
		$syncMode  = $request->boolean('propagations_sync'); // replace set?

		if ($request->has('propagations') && $syncMode) {
			$intended = collect(); // rebuild from payload only
		}

		foreach ($rows as $row) {
			$p = is_array($row) ? $row : [];

			if (!empty($p['id']) && array_key_exists('delete', $p) && (bool)$p['delete'] === true) {
				$intended->forget($p['id']);
				continue;
			}

			$uid = $p['user_id']    ?? null;
			$un  = $p['user_name']  ?? null;
			$ue  = $p['user_email'] ?? null;

			if (!empty($p['id'])) {
				if ($existing = MeetingDetailspropagation::find($p['id'])) {
					$intended->put($existing->id, (object)[
						'id'         => $existing->id,
						'user_id'    => array_key_exists('user_id', $p)    ? $uid : $existing->user_id,
						'user_name'  => array_key_exists('user_name', $p)  ? $un  : $existing->user_name,
						'user_email' => array_key_exists('user_email', $p) ? $ue  : $existing->user_email,
					]);
					continue;
				}
			}

			if (!empty($uid) || (!empty($un) && !empty($ue))) {
				$tmpKey = 'new:' . spl_object_id((object)$p);
				$intended->put($tmpKey, (object)[
					'id'         => null,
					'user_id'    => $uid,
					'user_name'  => $un,
					'user_email' => $ue,
				]);
			}
		}

		// ---- CONFLICT CHECK (same target meeting_id only; exclude this detail) ----
		$uniqueIdentities = $intended
			->map(fn($x) => ['user_id'=>$x->user_id, 'user_name'=>$x->user_name, 'user_email'=>$x->user_email])
			->unique(function ($x) {
				return !empty($x['user_id'])
					? 'id:'.$x['user_id']
					: 'ne:'.mb_strtolower(trim((string)$x['user_name'])).'|'.mb_strtolower(trim((string)$x['user_email']));
			})
			->values();

		$conflicts = [];
		foreach ($uniqueIdentities as $who) {
			$hasId = !empty($who['user_id']);
			$hasNE = !empty($who['user_name']) && !empty($who['user_email']);
			if (!$hasId && !$hasNE) continue;

			$q = MeetingDetailspropagation::query()
				->whereHas('meetingDetail', function ($md) use ($effectiveStart, $effectiveEnd, $detail, $targetMeetingId) {
					$md->where('meeting_id', $targetMeetingId)
					->where('id', '!=', $detail->id)
					->where('start_date', '<', $effectiveEnd)
					->where('end_date',   '>', $effectiveStart);
				})
				->where(function ($w) use ($who, $hasId, $hasNE) {
					if ($hasId) $w->orWhere('user_id', (int)$who['user_id']);
					if ($hasNE) {
						$w->orWhere(function ($x) use ($who) {
							$x->where('user_name', $who['user_name'])
							->where('user_email', $who['user_email']);
						});
					}
				});

			$hits = $q->with([
					'meetingDetail:id,meeting_id,start_date,end_date',
					'meetingDetail.meeting:id,title',
				])
				->get(['id','user_id','user_name','user_email','meeting_detail_id']);

			if ($hits->isNotEmpty()) {
				$conflicts[] = [
					'user_id'    => $who['user_id']   ?? null,
					'user_name'  => $who['user_name'] ?? null,
					'user_email' => $who['user_email']?? null,
					'conflicts'  => $hits->map(function ($c) {
						return [
							'propagation_id'    => $c->id,
							'meeting_detail_id' => $c->meeting_detail_id,
							'meeting_title'     => optional($c->meetingDetail->meeting)->title,
							'busy_start'        => optional($c->meetingDetail->start_date)->toISOString(),
							'busy_end'          => optional($c->meetingDetail->end_date)->toISOString(),
						];
					})->values(),
				];
			}
		}

		if (!empty($conflicts)) {
			return response()->json([
				'ok'         => false,
				'message'    => 'Time conflict: Users are already busy in this meeting’s requested window. Change the room or time.',
				'meeting'    => ['id' => $targetMeeting->id, 'title' => $targetMeeting->title],
				'conflicts'  => $conflicts,
				'hint'       => 'Back-to-back is allowed. Adjust start/end or pick another slot.',
			], 422);
		}

		// ---- APPLY UPDATE ----
		DB::transaction(function () use ($request, $detail, $rows, $syncMode, $targetMeetingId) {
			$detail->update($request->only(['title','start_date','end_date']) + ['meeting_id' => $targetMeetingId]);

			if ($request->has('propagations')) {
				$idsToKeep = [];

				foreach ($rows as $row) {
					$p = is_array($row) ? $row : [];

					if (!empty($p['id']) && array_key_exists('delete', $p) && (bool)$p['delete'] === true) {
						MeetingDetailspropagation::whereKey($p['id'])->delete();
						continue;
					}

					$payload = [];
					foreach (['user_name','user_email','is_read','user_id','sent_at'] as $k) {
						if (array_key_exists($k, $p)) $payload[$k] = $p[$k];
					}

					if (!empty($p['id'])) {
						if ($existing = MeetingDetailspropagation::find($p['id'])) {
							if ($existing->meeting_detail_id !== $detail->id) {
								$existing->meeting_detail_id = $detail->id;
							}
							if (!empty($payload)) $existing->fill($payload);
							$existing->save();
							$idsToKeep[] = $existing->id;
							continue;
						}
					}

					if (!empty($payload)) {
						$created = $detail->propagations()->create($payload);
						$idsToKeep[] = $created->id;
					}
				}

				if ($syncMode) {
					$detail->propagations()->whereNotIn('id', $idsToKeep)->delete();
				}
			}
		});

		$detail->loadCount('propagations');

		$include = (string) $request->query('include');
		if (str_contains($include, 'propagations')) {
			$detail->load('propagations');
		}

		return (new MeetingDetailResource($detail))->additional([
			'ok'   => true,
			'meta' => ['message' => 'Meeting detail updated successfully.'],
		]);
	}

    /**
     * GET /v1/details/{detail}
     */
    public function show(Request $request, $detailId)
	{
		$detail = \App\Models\MeetingDetail::withCount('propagations')->findOrFail($detailId);

		// Parse include param: supports "true", "1", "propagations", or comma lists
		$includeRaw = (string) $request->query('include', '');
		$includeArr = collect(explode(',', $includeRaw))
			->map(fn ($s) => strtolower(trim($s)))
			->filter()
			->values();

		$wantProp = filter_var($includeRaw, FILTER_VALIDATE_BOOLEAN)   // "true"/"1"
				|| $includeArr->contains('propagations');             // explicit

		if ($wantProp) {
			$detail->load('propagations'); // eager load so the resource can render it
		}

		return (new \App\Http\Resources\MeetingDetailResource($detail))->additional([
			'ok'   => true,
			'meta' => ['include' => $includeRaw, 'meeting_id' => $detail->meeting_id],
		]);
	}
    /**
     * DELETE /v1/details/{detail}
     */
    public function destroy(Request $request, $id)
	{
		// Accept id from URL OR body (fallback)
		$detailId = $id ?? $request->input('id');

		$detail = MeetingDetail::with('meeting:id,title')
			->withCount('propagations')
			->findOrFail($detailId);

		DB::transaction(function () use ($detail) {
			// If you don’t have ON DELETE CASCADE, clear children explicitly
			$detail->propagations()->delete();
			$detail->delete();
		});

		return response()->json([
			'ok'      => true,
			'message' => 'Meeting detail deleted successfully.',
			'meta'    => [
				'deleted_detail_id'   => $detail->id,
				'deleted_children'    => $detail->propagations_count, // count from pre-delete load
				'meeting'             => $detail->relationLoaded('meeting')
									? ['id' => $detail->meeting?->id, 'title' => $detail->meeting?->title]
									: null,
			],
		], 200);
	}

	public function byUser(MeetingDetailsByUserRequest $request)
	{
		$userId   = $request->input('user_id');
		$userName = $request->input('user_name');
		$userMail = $request->input('user_email');
		$isRead   = $request->input('is_read'); // 0|1|null

		$start = $request->filled('start') ? Carbon::parse($request->input('start')) : null;
		$end   = $request->filled('end')   ? Carbon::parse($request->input('end'))   : null;

		$include = strtolower((string)$request->input('include', ''));
		$per     = (int) $request->input('per_page', 15);

		// Sorting
		$sortInput = (string) $request->input('sort', 'start_date');
		$dir  = str_starts_with($sortInput, '-') ? 'desc' : 'asc';
		$col  = ltrim($sortInput, '-');
		if (!in_array($col, ['start_date','created_at'])) $col = 'start_date';

		// User + is_read matcher for propagations
		$matchUser = function ($q) use ($userId, $userName, $userMail, $isRead) {
			$q->when($userId, fn($qq) => $qq->where('user_id', $userId))
			->when(!$userId && $userName && $userMail, fn($qq) =>
					$qq->where('user_name', $userName)->where('user_email', $userMail)
			)
			->when(!is_null($isRead), fn($qq) =>
					$qq->where('is_read', (int) $isRead) // 0/1
			);
		};

		$q = MeetingDetail::query()
			// filter parent meeting by is_active
			->when(!is_null($request->input('is_active')), fn($qq) =>
				$qq->whereHas('meeting', fn($m) => $m->where('is_active', (int) $request->boolean('is_active')))
			)
			// optional time overlap
			->when($start && $end, fn($qq) =>
				$qq->where('start_date', '<', $end)->where('end_date', '>', $start)
			)
			// must have at least one matching propagation
			->whereHas('propagations', $matchUser)
			// helpful count (only matching rows)
			->withCount(['propagations as user_propagations_count' => $matchUser])
			// includes
			->when(str_contains($include, 'meeting'), fn($qq) => $qq->with('meeting:id,title,capacity,is_active'))
			->when(str_contains($include, 'propagations'), fn($qq) => $qq->with(['propagations' => $matchUser]))
			->orderBy($col, $dir);

		$paginator = $q->paginate($per)->appends($request->query());

		return MeetingDetailResource::collection($paginator)->additional([
			'ok'   => true,
			'meta' => [
				'filters' => [
					'user_id'   => $userId,
					'user_name' => $userName,
					'user_email'=> $userMail,
					'start'     => $start?->toISOString(),
					'end'       => $end?->toISOString(),
					'is_active' => $request->input('is_active'),
					'is_read'   => $isRead, // echo back
				],
				'include' => $include,
				'sort'    => $sortInput,
			],
		]);
	}

	public function markAsRead(Request $request, $id)
	{
		$detail = MeetingDetail::findOrFail($id);
		$userId = Auth::id();

		$updated = $detail->propagations()
			->where('user_id', $userId)
			->where('is_read', 0)
			->update(['is_read' => 1]);
		
		return response()->json([
			'ok'      => true,
			'message' => "{$updated} propagation(s) marked as read.",
			'meta'    => [
				'meeting_detail_id' => $detail->id,
				'meeting_id'        => $detail->meeting_id,
				'user_id'           => $userId,
			],
		]);
	}
}