<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $list = User::query()
                ->with(['department', 'designation', 'roles'])
                ->where('status', 'Active')
                ->where('is_active', 1)
                ->orderByDesc('id') // safer than latest() if created_at is missing
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add running index `i` across pages
            if ($list->count()) {
                $start = $list->firstItem() - 1;
                $list->setCollection(
                    $list->getCollection()->values()->map(function ($user, $k) use ($start) {
                        $user->setAttribute('i', $start + $k + 1);
                        return $user;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $list,
                'message' => $list->count() ? null : 'No users found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching users: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve users.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        // 1) Validate, including roles by name
        $v = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:6',
            'designation_id' => 'nullable|exists:designations,id',
            'department_id'  => 'nullable|exists:departments,id',
            'status'         => ['required', Rule::in(['active','inactive'])],
            'is_active'      => 'boolean',
            'phone'          => 'nullable|string|max:20',
            'roles'          => 'sometimes|array',
            'roles.*'        => 'string|exists:roles,name',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $data = $v->validated();
            $data['password'] = bcrypt($data['password']);

            // 2) Create user
            $user = User::create($data);

            // 3) Assign roles if provided
            if (!empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return response()->json([
                'success' => true,
                'data'    => $user->load(['department','designation','roles']),
                'message' => 'User created successfully.',
            ], 201);

        } catch (Throwable $e) {
            Log::error("Error creating user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with(['department','designation','roles'])->findOrFail($id);
            return response()->json(['success'=>true,'data'=>$user], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'User not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error fetching user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // 1) Validate, including roles by name
        $v = Validator::make($request->all(), [
            'name'           => 'sometimes|required|string|max:255',
            'email'          => "sometimes|required|email",
            'password'       => 'nullable|string|min:6',
            'designation_id' => 'nullable|exists:designations,id',
            'department_id'  => 'nullable|exists:departments,id',
            'phone'          => 'nullable|string|max:20',
            'roles'          => 'sometimes|array',
            'roles.*'        => 'string|exists:roles,name',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors.',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            $user = User::findOrFail($id);
            $data = $v->validated();

            // 2) Hash password if changed
            if (!empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

            // 3) Update user fields
            $user->update($data);

            // 4) Sync roles if provided
            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles'] ?? []);
            }

            return response()->json([
                'success' => true,
                'data'    => $user->load(['department','designation','roles']),
                'message' => 'User updated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);

        } catch (Throwable $e) {
            Log::error("Error updating user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // detach any roles/permissions...
            $user->is_active = '0';            // if is_active is still string
            $user->status    = 'Inactive';     // match your ENUM
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'User not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error deleting user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function activeUsers(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $user->is_active = '1';
            $user->status    = 'Active';
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'User not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error activating user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        try {
            $user = User::findOrFail(Auth::id());
            // 1) Validate, including roles by name
            $v = Validator::make($request->all(), [
                'name'           => 'sometimes|required|string|max:255',
                'phone'          => 'nullable|string|max:20',
                'designation_id' => 'nullable|exists:designations,id',
                'department_id'  => 'nullable|exists:departments,id',
            ]);
            $data = $v->validated();

            // 3) Update user fields
            $user->update($data);

            return response()->json([
                'success' => true,
                'data'    => $user->load(['department','designation','roles']),
                'message' => 'Profile updated successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);

        } catch (Throwable $e) {
            Log::error("Error updating user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function profileShow(): JsonResponse
    {
        try {
            $user = User::with(['department','designation'])->findOrFail(Auth::id());

            return response()->json(['success'=>true,'data'=>$user, 'message'=>'User profile fetched successfully.'], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'User not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error fetching user: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve user.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = User::findOrFail(Auth::id());

            $v = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'confirmed_password' => 'required|string|same:new_password',
                'new_password'     => 'required|string|min:6',
            ]);

            if ($v->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors.',
                    'errors'  => $v->errors(),
                ], 422);
            }

            if (!password_verify($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 422);
            }

            $user->password = bcrypt($request->new_password);
            $user->save();

            // Logout from current device only
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'User not found.'],404);

        } catch (Throwable $e) {
            Log::error("Error changing password: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function deActiveUsers(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            $users = User::query()
                ->where('is_active', 0)         // use int, not '0'
                ->where('status', 'Inactive')
                ->orderByDesc('id')             // safer than latest() if timestamps missing
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: add running index `i` across pages
            if ($users->count()) {
                $start = $users->firstItem() - 1;
                $users->setCollection(
                    $users->getCollection()->values()->map(function ($u, $k) use ($start) {
                        $u->setAttribute('i', $start + $k + 1);
                        return $u;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $users,
                'message' => $users->count() ? 'Inactive users retrieved successfully.' : 'No inactive users found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error fetching inactive users: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inactive users.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
