<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
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

    public function departmentBasedUsers(int $id): JsonResponse
    {
        try {
            $dept = Department::findOrFail($id);
            $users = $dept->users()->where('is_active', 1)->where('status', 'Active')->get();

            return response()->json([
                'success' => true,
                'data'    => $users,
                'message' => $users->count() ? 'Department users fetched successfully.' : 'No users found for this department.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Department not found.'], 404);

        } catch (Throwable $e) {
            Log::error("Error fetching department users: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve department users.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
