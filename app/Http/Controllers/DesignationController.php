<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $list = Designation::query()
                ->where('is_active', 1)
                ->latest('id') // safer if created_at is missing
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add running index `i` across pages
            if ($list->count()) {
                $start = $list->firstItem() - 1;
                $list->setCollection(
                    $list->getCollection()->values()->map(function ($row, $k) use ($start) {
                        $row->setAttribute('i', $start + $k + 1);
                        return $row;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $list,
                'message' => $list->count() ? null : 'No designations found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching designations: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve designations.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'       => [
                'required','string','max:255',
                // only unique among active (is_active = 1) designations
                Rule::unique('designations')->where(fn($q) => $q->where('is_active', 1)),
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
            $des = Designation::create($v->validated());

            return response()->json([
                'success' => true,
                'data'    => $des,
                'message' => 'Designation created successfully.',
            ], 201);

        } catch (Throwable $e) {
            Log::error("Error creating designation: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to create designation.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $des = Designation::findOrFail($id);
            return response()->json(['success'=>true,'data'=>$des],200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Designation not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error fetching designation: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve designation.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'       => [
                'required','string','max:255',
                // unique among active, but ignore this record’s own name
                Rule::unique('designations')
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
            $des = Designation::findOrFail($id);
            $des->update($v->validated());

            return response()->json([
                'success' => true,
                'data'    => $des,
                'message' => 'Designation updated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Designation not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error updating designation: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update designation.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $des = Designation::findOrFail($id);
            $des->is_active = '0';
            $des->save();

            return response()->json([
                'success' => true,
                'message' => 'Designation deactivated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Designation not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error deleting designation: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete designation.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $des = Designation::findOrFail($id);
            $des->is_active = '1';
            $des->save();

            return response()->json([
                'success' => true,
                'message' => 'Designation activated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Designation not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error restoring designation: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore designation.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function designationList(): JsonResponse
    {
        try {
            $designations = Designation::where('is_active', 1)->get();

            return response()->json([
                'success' => true,
                'data'    => $designations,
                'message' => $designations->count() ? 'Designations fetched successfully.' : 'No active designations found.',
            ], 200);

        } catch (Throwable $e) {
            Log::error("Error fetching designation list: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve designation list.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function deActiveDesignations(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $list = Designation::query()
                ->where('is_active', 0)
                ->latest('id') // safer than latest() if created_at is missing
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add running index `i` across pages
            if ($list->count()) {
                $start = $list->firstItem() - 1;
                $list->setCollection(
                    $list->getCollection()->values()->map(function ($row, $k) use ($start) {
                        $row->setAttribute('i', $start + $k + 1);
                        return $row;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $list,
                'message' => $list->count() ? null : 'No deactivated designations found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching deactivated designations: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve deactivated designations.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
