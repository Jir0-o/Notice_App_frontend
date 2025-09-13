<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $roles = Role::with('permissions')
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $roles,
                'message' => $roles->count() ? null : 'No roles found.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Error fetching roles: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve roles.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'          => 'required|string|unique:roles,name',
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name', // Ensure permissions exist
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $role = Role::create(['name' => $request->name]);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json([
                'success' => true,
                'data'    => $role->load('permissions'),
                'message' => 'Role created successfully.',
            ], 201);

        } catch (Throwable $e) {
            Log::error('Error creating role: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Role $role): JsonResponse
    {
        // Laravel injects $role, or 404s automatically
        return response()->json([
            'success' => true,
            'data'    => $role->load('permissions'),
        ], 200);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'          => "required|string|unique:roles,name,{$role->id}",
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $role->update(['name' => $request->name]);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json([
                'success' => true,
                'data'    => $role->load('permissions'),
                'message' => 'Role updated successfully.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Error updating role: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);

        } catch (Throwable $e) {
            Log::error('Error deleting role: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
