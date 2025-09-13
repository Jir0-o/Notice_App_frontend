<?php
namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use App\Models\Notice;
use App\Models\NoticePropagation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // Superadmin Dashboard
    public function superadmin()
    {
        try {
            $data = [
                'departments_count'  => Department::where('is_active', 1)->count(),
                'designations_count' => Designation::where('is_active', 1)->count(),
                'users_count'        => User::where('is_active', 1)->count(),
                'notices_count'      => Notice::where('status', 'published')->count()
            ];

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Superadmin dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Admin Dashboard
    public function admin()
    {
        try {
            $data = [
                'published_notices_count' => Notice::where('status', 'published')->where('created_by', Auth::id())->count(),
                'draft_notices_count'     => Notice::where('status', 'draft')->where('created_by', Auth::id())->count(),
            ];

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // User Dashboard
    public function user(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            $unreadCount = NoticePropagation::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                    ->orWhere('user_email', $user->email);
                })
                ->where('is_read', 0)
                // only count propagations whose parent notice is published
                ->whereHas('notice', function ($q) {
                    $q->where('status', 'published');
                })
                // if the same notice was propagated multiple times, count it once
                ->distinct('notice_id')
                ->count('notice_id');

            return response()->json([
                'success' => true,
                'data' => [
                    'notice_propagations_count' => $unreadCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('User dashboard error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

