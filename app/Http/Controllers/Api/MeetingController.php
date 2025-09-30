<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingStoreRequest;
use App\Http\Requests\MeetingUpdateRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    /**
     * GET /v1/meetings
     * Filters: search, is_active, capacity_min/max
     * Sorting: sort=created_at|-created_at|title|-title|capacity|-capacity|updated_at|-updated_at
     * Include: include=details,propagations (read-only)
     */
    public function index(Request $request)
    {
        $q = Meeting::query();

        // Filters
        if ($s = $request->query('search')) {
            $q->where('title', 'like', "%{$s}%");
        }
        if (!is_null($request->query('is_active'))) {
            $q->where('is_active', (int) $request->query('is_active') ? 1 : 0);
        }
        if ($min = $request->query('capacity_min')) {
            $q->where('capacity', '>=', (int) $min);
        }
        if ($max = $request->query('capacity_max')) {
            $q->where('capacity', '<=', (int) $max);
        }

        // Sorting
        $sort = $request->query('sort', '-created_at');
        $dir  = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col  = ltrim($sort, '-');
        if (!in_array($col, ['created_at','title','capacity','updated_at'])) {
            $col = 'created_at';
        }
        $q->orderBy($col, $dir);

        // Includes
        $include = (string) $request->query('include');
        $with = [];
        if (str_contains($include, 'details')) {
            $with[] = 'meetingDetails';
            if (str_contains($include, 'propagations')) {
                $with[] = 'meetingDetails.propagations';
            }
        }
        if ($with) $q->with($with);

        // Counts
        $q->withCount('meetingDetails');

        // Pagination
        $perPage   = (int) $request->query('per_page', 15);
        $page      = (int) $request->query('page', 1);
        $paginator = $q->paginate($perPage, ['*'], 'page', $page)->appends($request->query());

        // Build custom response (no meta wrapper)
        return response()->json([
            'data'  => MeetingResource::collection($paginator->items()),
            'ok'    => true,
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            // Flatten pagination info here
            'current_page' => $paginator->currentPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
            // Keep filters + sort at top level too
            'filters' => [
                'search'       => $request->query('search'),
                'is_active'    => $request->query('is_active'),
                'capacity_min' => $request->query('capacity_min'),
                'capacity_max' => $request->query('capacity_max'),
            ],
            'sort'    => $sort,
            'include' => $include,
        ]);
    }


    /**
     * POST /v1/meetings
     * (No nested details here)
     */
    public function store(MeetingStoreRequest $request)
    {
        $payload = $request->validated();

        $meeting = Meeting::create([
            'title'     => $payload['title'],
            'capacity'  => $payload['capacity'],
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        $meeting->loadCount('meetingDetails');

        // Optional include for read-after-write
        $include = (string) $request->query('include');
        if (str_contains($include, 'details')) {
            $with = ['meetingDetails'];
            if (str_contains($include, 'propagations')) {
                $with[] = 'meetingDetails.propagations';
            }
            $meeting->load($with);
        }

        return (new MeetingResource($meeting))->additional([
            'ok'   => true,
            'meta' => ['message' => 'Meeting created successfully.'],
        ]);
    }

    /**
     * GET /v1/meetings/{meeting}
     */
    public function show(Request $request, Meeting $meeting)
    {
        $meeting->loadCount('meetingDetails');

        $include = (string) $request->query('include');
        if (str_contains($include, 'details')) {
            $with = ['meetingDetails'];
            if (str_contains($include, 'propagations')) {
                $with[] = 'meetingDetails.propagations';
            }
            $meeting->load($with);
        }

        return (new MeetingResource($meeting))->additional([
            'ok'   => true,
            'meta' => ['include' => $include],
        ]);
    }

    /**
     * PATCH/PUT /v1/meetings/{meeting}
     */
    public function update(MeetingUpdateRequest $request, Meeting $meeting)
    {
        $meeting->update($request->validated());
        $meeting->loadCount('meetingDetails');

        return (new MeetingResource($meeting))->additional([
            'ok'   => true,
            'meta' => ['message' => 'Meeting updated successfully.'],
        ]);
    }

    /**
     * DELETE /v1/meetings/{meeting}
     */
    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Meeting deleted successfully.',
        ]);
    }

    /**
     * PATCH /v1/meetings/{meeting}/toggle
     */
    public function toggle(Meeting $meeting)
    {
        $meeting->is_active = (int)!$meeting->is_active;
        $meeting->save();

        return (new MeetingResource($meeting))->additional([
            'ok'   => true,
            'meta' => ['message' => 'Meeting status toggled.'],
        ]);
    }
}