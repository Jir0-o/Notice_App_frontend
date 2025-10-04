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
use App\Jobs\SendMeetingEmailsJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MeetingSent;

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
    // GET /api/meetings-details
	public function index(Request $request)
	{
		$q = MeetingDetail::query();

		// ---- Optional filters ----
		if ($s = $request->query('search')) {
			$q->where('title', 'like', "%{$s}%");
		}

		// Optional meeting_id filter
		if ($mid = $request->query('meeting_id')) {
			$q->where('meeting_id', (int) $mid);
		}

		// Date range on new 'date' column
		$from = $request->query('date_from');
		$to   = $request->query('date_to');

		if ($from && $to) {
			$q->whereDate('date', '>=', $from)
			->whereDate('date', '<=', $to);
		} elseif ($from) {
			$q->whereDate('date', '>=', $from);
		} elseif ($to) {
			$q->whereDate('date', '<=', $to);
		}

		// Upcoming / Past using (date, start_time)
		$now     = Carbon::now();
		$today   = $now->toDateString();
		$nowH_i  = $now->format('H:i');

		if ($request->boolean('upcoming')) {
			$q->where(function ($w) use ($today, $nowH_i) {
				$w->whereDate('date', '>', $today)
				->orWhere(function ($x) use ($today, $nowH_i) {
					$x->whereDate('date', $today)
						->where('start_time', '>=', $nowH_i);
				});
			});
		} elseif ($request->boolean('past')) {
			$q->where(function ($w) use ($today, $nowH_i) {
				$w->whereDate('date', '<', $today)
				->orWhere(function ($x) use ($today, $nowH_i) {
					$x->whereDate('date', $today)
						->where('start_time', '<', $nowH_i);
				});
			});
		}

		// ---- Sorting ----
		$sort = (string) $request->query('sort', 'date');
		$dir  = str_starts_with($sort, '-') ? 'desc' : 'asc';
		$col  = ltrim($sort, '-');
		if (!in_array($col, ['date','start_time','title','created_at'], true)) {
			$col = 'date';
		}
		$q->orderBy($col, $dir);

		// Stable secondary sort for deterministic ordering
		if ($col !== 'date') {
			$q->orderBy('date', 'asc');
		}
		if ($col !== 'start_time') {
			$q->orderBy('start_time', 'asc');
		}

		// ---- Includes ----
		$include = (string) $request->query('include');
		if (str_contains($include, 'propagations')) {
			$q->with('propagations');
		}
		if (str_contains($include, 'meeting')) {
			$q->with('meeting:id,title,capacity,is_active');
		}

		// Counts
		$q->withCount('propagations');

		// ---- Pagination ----
		$perPage   = (int) $request->query('per_page', 15);
		$page      = (int) $request->query('page', 1);
		$paginator = $q->paginate($perPage, ['*'], 'page', $page)
					->appends($request->query());

		// ---- Flat response (no nested meta) ----
		return response()->json([
			'data'  => \App\Http\Resources\MeetingDetailResource::collection($paginator->items()),
			'ok'    => true,

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

			// Echo filters/sort/include at top level
			'filters' => [
				'search'    => $request->query('search'),
				'meeting_id'=> $request->query('meeting_id'),
				'date_from' => $from,
				'date_to'   => $to,
				'upcoming'  => $request->query('upcoming'),
				'past'      => $request->query('past'),
			],
			'sort'    => $sort,
			'include' => $include,
		]);
	}

    /**
     * POST /v1/meetings/{meeting}/details
     * Optional nested "propagations" on create.
     */

	public function store(MeetingDetailStoreRequest $request)
	{
		DB::beginTransaction();
		try {
			// ---- VALIDATION (adds extra rules for internal/external split) ----
			$request->validate([
				'title'         => 'required|string|max:255',
				'date'          => 'required|date',
				'start_time'    => 'required|date_format:H:i',
				'end_time'      => 'required|date_format:H:i|after:start_time',
				'meeting_id'    => 'required|integer|exists:meetings,id',
				'internal_users'=> 'nullable|array',
				'internal_users.*' => 'integer|exists:users,id',
				'external_users'=> 'nullable|array',
				'external_users.*.email' => 'required_with:external_users|email',
				'external_users.*.name'  => 'required_with:external_users|string|max:255',
			]);

			$meetingId  = (int) $request->input('meeting_id');
			$meeting    = Meeting::select('id','title', 'capacity')->findOrFail($meetingId);

			$date       = \Carbon\Carbon::parse($request->input('date'))->toDateString();
			$startTime  = $request->input('start_time'); // H:i
			$endTime    = $request->input('end_time');   // H:i

			// ---- Resolve intended attendees (internal + external) ----
			$internalIds = collect($request->input('internal_users', []))
				->filter()->map(fn($v) => (int)$v)->unique()->values();

			$internalUsers = $internalIds->isNotEmpty()
				? \App\Models\User::whereIn('id', $internalIds)->get(['id','email'])
				: collect();

			$externals = collect($request->input('external_users', []))
				->filter(fn($x) => !empty($x['email']) && !empty($x['name']))
				->map(fn($x) => ['name' => $x['name'], 'email' => $x['email']])
				->values();

			// ---- CONFLICT CHECK (same meeting_id + same date + time overlap) ----
			// Build identities to test: by user_id, or by (name+email)
			$identities = collect();

			if ($internalUsers->isNotEmpty()) {
				foreach ($internalUsers as $u) {
					$identities->push([
						'user_id'    => (int)$u->id,
						'user_name'  => null,
						'user_email' => $u->email,
					]);
				}
			}

			if ($externals->isNotEmpty()) {
				foreach ($externals as $e) {
					$identities->push([
						'user_id'    => null,
						'user_name'  => $e['name'],
						'user_email' => $e['email'],
					]);
				}
			}

			$conflicts = [];
			foreach ($identities->unique(function ($x) {
				return $x['user_id'] ? 'id:'.$x['user_id']
									: 'ne:'.mb_strtolower($x['user_name'] ?? '')
										.'|'.mb_strtolower($x['user_email'] ?? '');
			}) as $who) {

				$hasId = !empty($who['user_id']);
				$hasNE = !empty($who['user_name']) && !empty($who['user_email']);
				if (!$hasId && !$hasNE) continue;

				$q = \App\Models\MeetingDetailspropagation::query()
					->whereHas('meetingDetail', function ($md) use ($meetingId, $date, $startTime, $endTime) {
						$md->where('meeting_id', $meetingId)
						->whereDate('date', $date)
						->where('start_time', '<', $endTime)  // strict overlap
						->where('end_time',   '>', $startTime);
					})
					->where(function ($w) use ($who, $hasId, $hasNE) {
						if ($hasId) $w->orWhere('user_id', (int) $who['user_id']);
						if ($hasNE) {
							$w->orWhere(function ($x) use ($who) {
								$x->where('user_name', $who['user_name'])
								->where('user_email', $who['user_email']);
							});
						}
					});

				$hits = $q->with([
						'meetingDetail:id,meeting_id,date,start_time,end_time',
						'meetingDetail.meeting:id,title',
					])
					->get(['id','user_id','user_name','user_email','meeting_detail_id']);

				if ($hits->isNotEmpty()) {
					$conflicts[] = [
						'user_id'    => $who['user_id'],
						'user_name'  => $who['user_name'],
						'user_email' => $who['user_email'],
						'conflicts'  => $hits->map(function ($c) {
							return [
								'propagation_id'    => $c->id,
								'meeting_detail_id' => $c->meeting_detail_id,
								'meeting_title'     => optional($c->meetingDetail->meeting)->title,
								'busy_date'         => optional($c->meetingDetail->date)?->toDateString(),
								'busy_start'        => optional($c->meetingDetail->start_time)?->format('H:i'),
								'busy_end'          => optional($c->meetingDetail->end_time)?->format('H:i'),
							];
						})->values(),
					];
				}
			}

			if (!empty($conflicts)) {
				DB::rollBack();
				return response()->json([
					'success'   => false,
					'message'   => "Time conflict: Users are already busy in this meeting’s requested window. Change the room or time.",
					'meeting'   => ['id' => $meeting->id, 'title' => $meeting->title],
					'conflicts' => $conflicts,
					'hint'      => 'Overlap rule is strict. Back-to-back (end == start) is allowed.',
				], 422);
			}

			// ---- CREATE (atomic) ----
			$detail = \App\Models\MeetingDetail::create([
				'meeting_id' => $meetingId,
				'title'      => $request->input('title'),
				'date'       => $date,
				'start_time' => $startTime,
				'end_time'   => $endTime,
			]);

			// Internal → user_id + user_email; External → name + email)
			if ($internalUsers->isNotEmpty()) {
				foreach ($internalUsers as $u) {
					$detail->propagations()->create([
						'user_id'    => $u->id,
						'user_name'  => null,
						'user_email' => $u->email,
						'is_read'    => false,
						'sent_at'    => now(),
					]);
				}

				try {
                    // Option A: queueable send (recommended in prod)
                    Notification::send($internalUsers, new MeetingSent($detail));

                    Log::info('Meeting notifications queued for internal users', [
                        'meeting_id' => $meeting->id,
                        'count'     => $internalUsers->count(),
                        'users'     => $internalUsers->pluck('email'),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Error sending meeting notifications', [
                        'meeting_id' => $detail->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
			}
			// External → user_id = null
			if ($externals->isNotEmpty()) {
				foreach ($externals as $e) {
					$detail->propagations()->create([
						'user_id'    => null,
						'user_name'  => $e['name'],
						'user_email' => $e['email'],
						'is_read'    => false,
						'sent_at'    => now(),
					]);
				}
			}

			DB::commit();

			$detail->loadCount('propagations');
			if (filter_var($request->query('include'), FILTER_VALIDATE_BOOLEAN) ||
				str_contains((string)$request->query('include'), 'propagations')) {
				$detail->load('propagations');
			}

			dispatch(new SendMeetingEmailsJob($detail->id));

			return (new \App\Http\Resources\MeetingDetailResource($detail))->additional([
				'success' => true,
				'message' => 'Meeting detail created successfully.',
			]);

		} catch (\Illuminate\Validation\ValidationException $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => 'Validation failed.',
				'errors'  => $e->errors(),
			], 422);
		} catch (\Throwable $e) {
			DB::rollBack();
			Log::error('Error creating meeting detail', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request' => $request->all()
			]);
			return response()->json([
				'success' => false,
				'message' => 'Failed to create meeting detail.',
				'error'   => config('app.debug') ? $e->getMessage() : null
			], 500);
		}
	}


	/**
	 * Update a detail (and its propagations) with conflict checks inside SAME meeting_id.
	 * Admin can move/update any propagation by global id. Supports sync delete.
	 */
	public function update(MeetingDetailUpdateRequest $request, $id)
	{
		DB::beginTransaction();
		try {
			// VALIDATION (mirror store; all are sometimes/nullable)
			$request->validate([
				'title'         => 'sometimes|string|max:255',
				'date'          => 'sometimes|date',
				'start_time'    => 'sometimes|date_format:H:i',
				'end_time'      => 'sometimes|date_format:H:i',
				'meeting_id'    => 'sometimes|integer|exists:meetings,id',
				'internal_users'=> 'nullable|array',
				'internal_users.*' => 'integer|exists:users,id',
				'external_users'=> 'nullable|array',
				'external_users.*.email' => 'required_with:external_users|email',
				'external_users.*.name'  => 'required_with:external_users|string|max:255',
			]);

			$newPropagationIdsToEmail = [];

			$detail = \App\Models\MeetingDetail::with('propagations')->findOrFail($id);

			// Target meeting + effective window after update
			$targetMeetingId = (int) $request->input('meeting_id', $detail->meeting_id);
			$targetMeeting   = Meeting::select('id','title')->findOrFail($targetMeetingId);

			$date      = $request->filled('date')       ? \Carbon\Carbon::parse($request->input('date'))->toDateString() : $detail->date?->toDateString();
			$startTime = $request->filled('start_time') ? $request->input('start_time') : $detail->start_time?->format('H:i');
			$endTime   = $request->filled('end_time')   ? $request->input('end_time')   : $detail->end_time?->format('H:i');

			// ---- Build intended attendees (internal/external) for CONFLICT check ----
			// Existing internal/external set from DB, then reconcile with payloads IF present.
			$existingInternalIds = $detail->propagations
				->whereNotNull('user_id')->pluck('user_id')->map(fn($v)=>(int)$v)->values();
			$existingExternals   = $detail->propagations
				->whereNull('user_id')
				->map(fn($p) => ['name' => $p->user_name, 'email' => $p->user_email])->values();

			$internalIds = $request->has('internal_users')
				? collect($request->input('internal_users', []))->filter()->map(fn($v)=>(int)$v)->unique()->values()
				: $existingInternalIds;

			$externalList = $request->has('external_users')
				? collect($request->input('external_users', []))
					->filter(fn($x) => !empty($x['email']) && !empty($x['name']))
					->map(fn($x) => ['name' => $x['name'], 'email' => $x['email']])
					->values()
				: $existingExternals;

			$internalUsers = $internalIds->isNotEmpty()
				? \App\Models\User::whereIn('id', $internalIds)->get(['id','email'])
				: collect();

			// ---- CONFLICT CHECK (same meeting_id + same date + time overlap; exclude THIS detail) ----
			$identities = collect();

			foreach ($internalUsers as $u) {
				$identities->push(['user_id'=>(int)$u->id, 'user_name'=>null, 'user_email'=>$u->email]);
			}
			foreach ($externalList as $e) {
				$identities->push(['user_id'=>null, 'user_name'=>$e['name'], 'user_email'=>$e['email']]);
			}

			$conflicts = [];
			foreach ($identities->unique(function ($x) {
				return $x['user_id'] ? 'id:'.$x['user_id']
									: 'ne:'.mb_strtolower($x['user_name'] ?? '')
										.'|'.mb_strtolower($x['user_email'] ?? '');
			}) as $who) {
				$hasId = !empty($who['user_id']);
				$hasNE = !empty($who['user_name']) && !empty($who['user_email']);
				if (!$hasId && !$hasNE) continue;

				$q = \App\Models\MeetingDetailspropagation::query()
					->whereHas('meetingDetail', function ($md) use ($targetMeetingId, $detail, $date, $startTime, $endTime) {
						$md->where('meeting_id', $targetMeetingId)
						->where('id', '!=', $detail->id)
						->whereDate('date', $date)
						->where('start_time', '<', $endTime)
						->where('end_time',   '>', $startTime);
					})
					->where(function ($w) use ($who, $hasId, $hasNE) {
						if ($hasId) $w->orWhere('user_id', (int) $who['user_id']);
						if ($hasNE) {
							$w->orWhere(function ($x) use ($who) {
								$x->where('user_name', $who['user_name'])
								->where('user_email', $who['user_email']);
							});
						}
					});

				$hits = $q->with([
						'meetingDetail:id,meeting_id,date,start_time,end_time',
						'meetingDetail.meeting:id,title',
					])
					->get(['id','user_id','user_name','user_email','meeting_detail_id']);

				if ($hits->isNotEmpty()) {
					$conflicts[] = [
						'user_id'    => $who['user_id'],
						'user_name'  => $who['user_name'],
						'user_email' => $who['user_email'],
						'conflicts'  => $hits->map(function ($c) {
							return [
								'propagation_id'    => $c->id,
								'meeting_detail_id' => $c->meeting_detail_id,
								'meeting_title'     => optional($c->meetingDetail->meeting)->title,
								'busy_date'         => optional($c->meetingDetail->date)?->toDateString(),
								'busy_start'        => optional($c->meetingDetail->start_time)?->format('H:i'),
								'busy_end'          => optional($c->meetingDetail->end_time)?->format('H:i'),
							];
						})->values(),
					];
				}
			}

			if (!empty($conflicts)) {
				DB::rollBack();
				return response()->json([
					'success'   => false,
					'message'   => 'Time conflict: Users are already busy in this meeting’s requested window. Change the room or time.',
					'meeting'   => ['id' => $targetMeeting->id, 'title' => $targetMeeting->title],
					'conflicts' => $conflicts,
					'hint'      => 'Overlap is strict; end==start is fine.',
				], 422);
			}

			// ---- APPLY UPDATE (atomic) ----
			$detail->update([
				'meeting_id' => $targetMeetingId,
				'title'      => $request->input('title', $detail->title),
				'date'       => $date,
				'start_time' => $startTime,
				'end_time'   => $endTime,
			]);

			// INTERNAL reconciliation: diff by user_id
			if ($request->has('internal_users')) {
				$existingInternalIds = $detail->propagations()
					->whereNotNull('user_id')
					->pluck('user_id')
					->map(fn($v)=>(int)$v)
					->toArray();

				$requestedInternalIds = $internalIds->toArray();

				$toAdd    = array_diff($requestedInternalIds, $existingInternalIds);
				$toRemove = array_diff($existingInternalIds, $requestedInternalIds);

				if (!empty($toRemove)) {
					\App\Models\MeetingDetailspropagation::where('meeting_detail_id', $detail->id)
						->whereIn('user_id', $toRemove)
						->delete();
				}
				if (!empty($toAdd)) {
					$users = \App\Models\User::whereIn('id', $toAdd)->get(['id','email']);
					foreach ($users as $u) {
						$prop = $detail->propagations()->create([
							'user_id'    => $u->id,
							'user_name'  => null,
							'user_email' => $u->email,
							'is_read'    => false,
							'sent_at'    => now(),
						]);
						$newPropagationIdsToEmail[] = $prop->id; // collect new IDs
					}
				}
			}

			// EXTERNAL replacement
			if ($request->has('external_users')) {
				\App\Models\MeetingDetailspropagation::where('meeting_detail_id', $detail->id)
					->whereNull('user_id')
					->delete();

				foreach ($externalList as $e) {
					$prop = $detail->propagations()->create([
						'user_id'    => null,
						'user_name'  => $e['name'],
						'user_email' => $e['email'],
						'is_read'    => false,
						'sent_at'    => now(),
					]);
					$newPropagationIdsToEmail[] = $prop->id; // collect new IDs
				}
			}

			// If notice already published and new recipients were added, enqueue emails just for them
            if (!empty($newPropagationIdsToEmail)) {
                dispatch(new SendMeetingEmailsJob($detail->id, $newPropagationIdsToEmail));
            }

			DB::commit();

			$detail->loadCount('propagations');
			if (filter_var($request->query('include'), FILTER_VALIDATE_BOOLEAN) ||
				str_contains((string)$request->query('include'), 'propagations')) {
				$detail->load('propagations');
			}

			return (new \App\Http\Resources\MeetingDetailResource($detail))->additional([
				'success' => true,
				'message' => 'Meeting detail updated successfully.',
				'new_propagation_emails_dispatched_for_ids' => $newPropagationIdsToEmail,
			]);

		} catch (\Illuminate\Validation\ValidationException $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => 'Validation failed.',
				'errors'  => $e->errors(),
			], 422);
		} catch (\Throwable $e) {
			DB::rollBack();
			Log::error('Error updating meeting detail', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request' => $request->all()
			]);
			return response()->json([
				'success' => false,
				'message' => 'Failed to update meeting detail.',
				'error'   => config('app.debug') ? $e->getMessage() : null,
			], 500);
		}
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

	public function byUser(Request $request)
	{
		// --- minimal validation (since we're not using a FormRequest) ---
		$request->validate([
			'user_id'    => 'sometimes|integer|exists:users,id',
			'user_name'  => 'sometimes|string',
			'user_email' => 'sometimes|email',
			'is_read'    => 'sometimes|in:0,1',
			'start'      => 'sometimes|date',
			'end'        => 'sometimes|date|after_or_equal:start',
			'per_page'   => 'sometimes|integer|min:1|max:100',
			'sort'       => 'sometimes|string',
			'is_active'  => 'sometimes|boolean',
			'include'    => 'sometimes|string',
		]);

		// Prefer the authenticated user; allow explicit override via query if you want
		$authId  = Auth::user()?->id;
		$userId  = $request->filled('user_id') ? (int) $request->input('user_id') : $authId;

		$userName = $request->input('user_name');   // only used if no user_id
		$userMail = $request->input('user_email');
		$isRead   = $request->input('is_read');     // 0|1|null

		// If we have neither an auth user nor explicit (name+email), bail early
		if (empty($userId) && !(filled($userName) && filled($userMail))) {
			return response()->json([
				'message' => 'Authenticated user not found. Provide user_id or user_name + user_email.',
				'errors'  => ['user' => ['Authenticated user not found. Provide user_id or user_name + user_email.']],
			], 422);
		}

		// Optional window
		$start = $request->filled('start') ? Carbon::parse($request->input('start')) : null;
		$end   = $request->filled('end')   ? Carbon::parse($request->input('end'))   : null;

		$include = strtolower((string) $request->input('include', ''));
		$per     = (int) $request->input('per_page', 15);

		// Sorting
		$sortInput = (string) $request->input('sort', 'date');
		$dir  = str_starts_with($sortInput, '-') ? 'desc' : 'asc';
		$col  = ltrim($sortInput, '-');
		if (!in_array($col, ['date','start_time','created_at'], true)) {
			$col = 'date';
		}

		// Build the propagation matcher
		$matchUser = function ($q) use ($userId, $userName, $userMail, $isRead) {
			if (!empty($userId)) {
				$q->where('user_id', $userId);
			} else {
				// only when user_id absent and both provided
				$q->where('user_name', $userName)->where('user_email', $userMail);
			}
			if (!is_null($isRead)) {
				$q->where('is_read', (int) $isRead);
			}
		};

		$q = MeetingDetail::query()
			// optional parent meeting filter by is_active
			->when(!is_null($request->input('is_active')), fn($qq) =>
				$qq->whereHas('meeting', fn($m) => $m->where('is_active', (int) $request->boolean('is_active')))
			)
			// time/date filtering on (date, start_time, end_time)
			->when($start && $end, function ($qq) use ($start, $end) {
				$startDate = $start->toDateString();
				$endDate   = $end->toDateString();

				if ($startDate === $endDate) {
					$startTime = $start->format('H:i');
					$endTime   = $end->format('H:i');

					$qq->whereDate('date', $startDate)
					->where('start_time', '<', $endTime)   // strict overlap
					->where('end_time',   '>', $startTime);
				} else {
					$qq->whereDate('date', '>=', $startDate)
					->whereDate('date', '<=', $endDate);
				}
			})
			// must have at least one matching propagation
			->whereHas('propagations', $matchUser)
			// counts only matching rows
			->withCount(['propagations as user_propagations_count' => $matchUser])
			// ALWAYS include meeting summary (so you see title/capacity)
			->with(['meeting:id,title,capacity,is_active'])
			// include propagations only if requested
			->when(str_contains($include, 'propagations'), fn($qq) => $qq->with(['propagations' => $matchUser]))
			// ordering
			->orderBy($col, $dir);

		if ($col !== 'date') {
			$q->orderBy('date', 'asc');
		}
		if ($col !== 'start_time') {
			$q->orderBy('start_time', 'asc');
		}

		$paginator = $q->paginate($per)->appends($request->query());

		return response()->json([
			'data'  => \App\Http\Resources\MeetingDetailResource::collection($paginator->items()),
			'ok'    => true,
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

			// Echoed filters
			'filters' => [
				'user_id'    => $userId,                 // now defaults to Auth::id()
				'user_name'  => $userName,
				'user_email' => $userMail,
				'start'      => $request->input('start'),
				'end'        => $request->input('end'),
				'is_active'  => $request->input('is_active'),
				'is_read'    => $isRead,
			],
			'include' => $include,
			'sort'    => $sortInput,
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