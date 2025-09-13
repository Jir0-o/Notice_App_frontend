<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $perms = Permission::latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $perms,
                'message' => $perms->count() ? null : 'No permissions found.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Error fetching permissions: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve permissions.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $perm = Permission::create(['name' => $request->name]);

            return response()->json([
                'success' => true,
                'data'    => $perm,
                'message' => 'Permission created successfully.',
            ], 201);

        } catch (Throwable $e) {
            Log::error('Error creating permission: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $permission,
        ], 200);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => "required|string|unique:permissions,name,{$permission->id}",
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $permission->update(['name' => $request->name]);

            return response()->json([
                'success' => true,
                'data'    => $permission,
                'message' => 'Permission updated successfully.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Error updating permission: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $perm = Permission::findOrFail($id);

            // detach any attached roles first
            $perm->roles()->detach();

            // now it’s safe to delete
            $perm->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);

        } catch (Throwable $e) {
            Log::error('Error deleting permission: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

}
