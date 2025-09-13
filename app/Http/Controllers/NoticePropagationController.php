<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticePropagation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NoticePropagationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function myNotices(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            // Filter: read | unread | all (default)
            $status   = strtolower((string) $request->query('status', 'all'));
            $readFlag = null;
            if (in_array($status, ['read', '1', 'true'], true))   $readFlag = 1;
            if (in_array($status, ['unread', '0', 'false'], true)) $readFlag = 0;

            $perPage = max(1, min((int) $request->input('per_page', 10), 100));
            $page    = max(1, (int) $request->input('page', 1));

            // Subquery: only rows for THIS user that were actually sent (sent_at not null)
            $npSub = NoticePropagation::query()
                ->select([
                    'notice_id',
                    DB::raw('MAX(is_read) AS is_read'), // if any duplicate was read -> 1
                ])
                ->where(function ($q) use ($user) {
                    $q->where(function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                        ->orWhere('user_email', $user->email);
                    })
                    ->whereNotNull('sent_at'); // enforce "only if sent to this user"
                })
                ->groupBy('notice_id');

            // Main query: only published notices + only those targeted (via inner join to subquery)
            $notices = Notice::query()
                ->with(['attachments'])
                ->joinSub($npSub, 'np', function ($join) {
                    $join->on('np.notice_id', '=', 'notices.id'); // INNER JOIN => excludes unsent
                })
                ->where('notices.status', 'published') // only published
                ->when($readFlag !== null, fn ($q) => $q->where('np.is_read', $readFlag))
                ->orderByDesc('notices.id')
                ->select('notices.*', DB::raw('np.is_read AS is_read'))
                ->paginate($perPage, ['*'], 'page', $page);

            // Optional: running index `i`
            if ($notices->count()) {
                $start = $notices->firstItem() - 1;
                $notices->setCollection(
                    $notices->getCollection()->values()->map(function ($n, $k) use ($start) {
                        $n->setAttribute('i', $start + $k + 1);
                        return $n;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Notices fetched successfully.',
                'data'    => $notices,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching notices for user: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notices.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function showMyNotice(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated.'
                ], 401);
            }

            // Find propagation for this notice & user (by id or email)
            $propagation = NoticePropagation::where('notice_id', $id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                    ->orWhere('user_email', $user->email);
                })
                ->first();

            if (!$propagation) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this notice.'
                ], 403);
            }

            // Mark as read if not already
            if (!$propagation->is_read) {
                $propagation->is_read = 1;
                $propagation->save();
            }

            // Fetch full notice with relations
            $notice = Notice::with(['attachments'])
                ->join('notice_propagations', function($join) use ($user) {
                        $join->on('notices.id', '=', 'notice_propagations.notice_id')
                            ->where(function($q) use ($user) {
                                $q->where('notice_propagations.user_id', $user->id)
                                ->orWhere('notice_propagations.user_email', $user->email);
                            });
                    })
                ->select('notices.*', 'notice_propagations.is_read')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Notice detail fetched successfully.',
                'user'  => $user,
                'data'    => $notice,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notice not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching notice detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notice.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
