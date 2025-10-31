<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\MeetingDetailspropagation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // sanitize inputs
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $list = Department::query()
                ->where('is_active', 1)
                // 'latest()' defaults to created_at; use id to be safe if timestamps are missing
                ->latest('id')
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add serial index `i` (1-based across pages)
            if ($list->count()) {
                $start = $list->firstItem() - 1;
                $list->setCollection(
                    $list->getCollection()->values()->map(function ($dept, $k) use ($start) {
                        $dept->setAttribute('i', $start + $k + 1);
                        return $dept;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $list,
                'message' => $list->count() ? null : 'No departments found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching departments: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve departments.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'       => [
                'required','string','max:255',
                // only unique among active departments
                Rule::unique('departments')
                    ->where(fn($q) => $q->where('is_active', 1)),
            ],
            'short_name' => 'required|string|max:100',
            'is_active'  => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $dept = Department::create($v->validated());

            return response()->json([
                'success' => true,
                'data'    => $dept,
                'message' => 'Department created successfully.',
            ], 201);

        } catch (Throwable $e) {
            Log::error("Error creating department: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $dept = Department::findOrFail($id);
            return response()->json(['success'=>true,'data'=>$dept], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Department not found.'], 404);

        } catch (Throwable $e) {
            Log::error("Error fetching department: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve department.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'       => [
                'required','string','max:255',
                // unique among active, ignoring this record's own name
                Rule::unique('departments')
                    ->ignore($id)
                    ->where(fn($q) => $q->where('is_active', 1)),
            ],
            'short_name' => 'required|string|max:100',
            'is_active'  => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $dept = Department::findOrFail($id);
            $dept->update($v->validated());

            return response()->json([
                'success' => true,
                'data'    => $dept,
                'message' => 'Department updated successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.',
            ], 404);

        } catch (Throwable $e) {
            Log::error("Error updating department: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $dept = Department::findOrFail($id);

            $dept->is_active = '0';
            $dept->save();

            return response()->json([
                'success' => true,
                'message' => 'Department deactivated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Department not found.'], 404);

        } catch (Throwable $e) {
            Log::error("Error deleting department: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $dept = Department::findOrFail($id);

            $dept->is_active = '1';
            $dept->save();

            return response()->json([
                'success' => true,
                'message' => 'Department activated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Department not found.'], 404);

        } catch (Throwable $e) {
            Log::error("Error restoring department: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore department.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function deActiveDepartments(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $list = Department::query()
                ->where('is_active', 0)
                ->latest('id') // safer if timestamps are missing
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add running index `i`
            if ($list->count()) {
                $start = $list->firstItem() - 1;
                $list->setCollection(
                    $list->getCollection()->values()->map(function ($dept, $k) use ($start) {
                        $dept->setAttribute('i', $start + $k + 1);
                        return $dept;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $list,
                'message' => $list->count() ? null : 'No inactive departments found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching inactive departments: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve inactive departments.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function departmentList(): JsonResponse
    {
        try {
            $departments = Department::where('is_active', 1)->get();

            return response()->json([
                'success' => true,
                'data'    => $departments,
                'message' => $departments->count() ? 'Departments fetched successfully.' : 'No active departments found.',
            ], 200);

        } catch (Throwable $e) {
            Log::error("Error fetching department list: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve department list.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function departmentBasedUsers(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date'       => ['nullable','date'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time'   => ['nullable','date_format:H:i','after:start_time'],
        ]);

        // normalize
        $date      = $data['date']       ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime   = $data['end_time']   ?? null;

        try {
            // 1. get dept
            $dept = Department::findOrFail($id);

            // 2. get ALL active users of that dept
            $users = $dept->users()
                ->where('is_active', 1)
                ->where('status', 'Active')
                ->with('designation')
                ->get(['id','name','email','designation_id','department_id']);

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No users found for this department.',
                    'window'  => [
                        'date'       => $date,
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                    ],
                    'data'    => [],
                ], 200);
            }

            // if no window given, return users without busy check
            if (!$date || !$startTime || !$endTime) {
                $payload = $users->map(function ($u) {
                    return [
                        'id'          => $u->id,
                        'name'        => $u->name,
                        'email'       => $u->email,
                        'designation' => $u->designation?->name,
                        'is_busy'     => false,
                        'conflicts'   => [],
                        'busy_msg'    => null,
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'message' => 'Department users fetched (no time window supplied).',
                    'window'  => null,
                    'data'    => $payload,
                ], 200);
            }

            // 3. now we DO have a window, so check busy
            $userIds = $users->pluck('id')->values();

            $busy = MeetingDetailspropagation::query()
                ->whereIn('user_id', $userIds)
                ->whereHas('meetingDetail', function ($md) use ($date, $startTime, $endTime) {
                    $md->whereDate('date', $date)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time',   '>', $startTime);
                })
                ->with([
                    'meetingDetail:id,meeting_id,title,date,start_time,end_time',
                    'meetingDetail.meeting:id,title',
                ])
                ->get(['id','user_id','meeting_detail_id']);

            $busyByUser = $busy->groupBy('user_id');

            $payload = $users->map(function ($u) use ($busyByUser) {
                $conflicts = $busyByUser->get($u->id, collect());

                return [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'email'       => $u->email,
                    'designation' => $u->designation?->name,
                    'is_busy'     => $conflicts->isNotEmpty(),
                    'conflicts'   => $conflicts->map(function ($c) {
                        return [
                            'propagation_id'    => $c->id,
                            'meeting_detail_id' => $c->meeting_detail_id,
                            'meeting_title'     => optional($c->meetingDetail->meeting)->title,
                            'detail_title'      => $c->meetingDetail->title ?? null,
                            'date'              => optional($c->meetingDetail->date)?->toDateString(),
                            'start_time'        => optional($c->meetingDetail->start_time)?->format('H:i'),
                            'end_time'          => optional($c->meetingDetail->end_time)?->format('H:i'),
                        ];
                    })->values(),
                    'busy_msg'    => $conflicts->isNotEmpty()
                        ? 'This user is in another meeting in this time window.'
                        : null,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Department users with busy flags fetched.',
                'window'  => [
                    'date'       => $date,
                    'start_time' => $startTime,
                    'end_time'   => $endTime,
                ],
                'data'    => $payload,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.',
            ], 404);

        } catch (\Throwable $e) {
            Log::error("Error fetching department users with busy flags: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve department users.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

}
