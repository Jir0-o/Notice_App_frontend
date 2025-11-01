<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Jobs\SendNoticeEmailsJob;
use App\Models\Attachment;
use App\Models\Notice;
use App\Models\NoticePropagation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Mail\NoticePublishedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NoticeSent;

class NoticeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/notices",
     *     tags={"Notice"},
     *     summary="List all notices",
     *     description="Returns a list of all notices",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="A list of notices",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Notice")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
     */
    // List all notices
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $page    = (int) $request->input('page', 1);

            // status filter supports: read | unread | all | published | draft
            $raw    = $request->query('status', 'all');
            $status = strtolower(trim((string) $raw));
            if ($status === '' || $status === 'undefined' || $status === 'null') {
                $status = 'all';
            }

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            // Decide which type of filter we’re applying
            $readFlag      = null;          // 1 or 0, null means no read filter
            $noticeStatus  = null;          // 'published' | 'draft' | null

            if (in_array($status, ['read', '1', 'true'], true)) {
                $readFlag = 1;
            } elseif (in_array($status, ['unread', '0', 'false'], true)) {
                $readFlag = 0;
            } elseif (in_array($status, ['published', 'draft'], true)) {
                $noticeStatus = $status;     // filter by notices.status
            } // else 'all' => no filter on read or status

            // Subquery: one row per notice with THIS user's read state
            $npSub = NoticePropagation::query()
                ->select([
                    'notice_id',
                    DB::raw('MAX(is_read) as my_is_read'),
                ])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                    ->orWhere('user_email', $user->email);
                })
                ->groupBy('notice_id');

            $notices = Notice::query()
                ->where('created_by', $user->id) // keep your author scope
                ->when($noticeStatus !== null, fn ($q) => $q->where('notices.status', $noticeStatus))
                ->with(['creator', 'modifier', 'attachments', 'propagations.user'])
                ->leftJoinSub($npSub, 'np', 'np.notice_id', '=', 'notices.id')
                ->when($readFlag !== null, fn ($q) =>
                    $q->whereRaw('COALESCE(np.my_is_read, 0) = ?', [$readFlag])
                )
                ->orderByDesc('notices.created_at')
                ->select('notices.*', DB::raw('COALESCE(np.my_is_read, 0) as is_read'))
                ->paginate($perPage, ['*'], 'page', $page);

            // OPTIONAL: running index `i` per page
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
                'data'    => $notices,
                'message' => $notices->count() ? null : 'No notices found.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching notices: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve notices.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/notices/{id}",
     *     tags={"Notice"},
     *     summary="Show a specific notice",
     *     description="Returns details of a specific notice by ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the notice",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notice details",
     *         @OA\JsonContent(ref="#/components/schemas/Notice")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notice not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Notice not found."))
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
     */
    // Show single notice
    public function show(Notice $notice)
    {
        try {
            $notice->load(['creator', 'modifier', 'attachments', 'propagations.user']);

            return response()->json([
                'success' => true,
                'data'    => $notice,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching notice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve notice.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/notices",
     *     tags={"Notice"},
     *     summary="Create a new notice (with internal/external users, attachments, draft/publish)",
     *     description="Creates a new notice. You may optionally assign to multiple internal users (by user IDs) or external users (name & email), and upload multiple attachments. Set 'status' to 'draft' or 'published'.",
     *     operationId="storeNotice",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Notice creation payload. For file upload, use multipart/form-data.",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "description"},
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Notice headline/title (required, max 255 chars).",
     *                     example="Meeting on Friday"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Main content/body of the notice (required).",
     *                     example="There will be a meeting in the main hall at 10am."
     *                 ),
     *                 @OA\Property(
     *                     property="internal_users",
     *                     type="array",
     *                     description="Optional array of internal user IDs (must exist in users table).",
     *                     @OA\Items(type="integer", example=5)
     *                 ),
     *                 @OA\Property(
     *                     property="external_users",
     *                     type="array",
     *                     description="Optional array of objects for external users (name & email required).",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"name", "email"},
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     enum={"draft","published"},
     *                     description="Set as 'draft' to save as draft, or 'published' to send immediately. Default: published.",
     *                     example="draft"
     *                 ),
     *                 @OA\Property(
     *                     property="priority_level",
     *                     type="string",
     *                     description="Priority level (optional, e.g., high, normal, low)",
     *                     example="high"
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="Optional multiple files: PDF, DOC, DOCX, XLSX, XLS, JPG, JPEG, PNG (10MB max each).",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Notice created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notice created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Meeting on Friday"),
     *                 @OA\Property(property="description", type="string", example="There will be a meeting in the main hall at 10am."),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="priority_level", type="string", example="high"),
     *                 @OA\Property(property="created_by", type="integer", example=2),
     *                 @OA\Property(property="modified_by", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="List of uploaded attachments",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="file_name", type="string", example="5f8d9abc_file.pdf"),
     *                         @OA\Property(property="file_type", type="string", example="pdf"),
     *                         @OA\Property(property="file_path", type="string", example="https://example.com/notices/5f8d9abc_file.pdf"),
     *                         @OA\Property(property="uploaded_at", type="string", format="date-time", example="2025-08-03T15:32:08Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="propagations",
     *                     type="array",
     *                     description="All target recipients (internal/external)",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="notice_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", nullable=true, example=5, description="Internal user ID, or null for external"),
     *                         @OA\Property(property="name", type="string", nullable=true, example="John Doe", description="External user name, null for internal"),
     *                         @OA\Property(property="user_email", type="string", example="john@example.com"),
     *                         @OA\Property(property="is_read", type="boolean", example=false),
     *                         @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example="2025-08-03T15:32:08Z"),
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="title",
     *                     type="array",
     *                     @OA\Items(type="string", example="The title field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="attachments.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The attachments.0 must be a file of type: pdf, doc, docx, xlsx, xls, jpg, jpeg, png.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create notice",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create notice."),
     *             @OA\Property(property="error", type="string", nullable=true, example="SQLSTATE[23000]...")
     *         )
     *     )
     * )
     */
    // Create a new notice
    public function store(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'internal_users' => 'nullable|array',
                'internal_users.*' => 'exists:users,id',
                'status' => 'nullable|string|in:draft,published', // Add this line
                'external_users' => 'nullable|array',
                'external_users.*.email' => 'required_with:external_users|email',
                'external_users.*.name' => 'required_with:external_users|string',
                'attachments' => 'sometimes|array',
                'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xlsx,xls,jpg,jpeg,png|max:10240', // 10MB max
            ]);

            // Create the notice
            $notice = Notice::create([
                'title'       => $request->input('title'),
                'description' => $request->input('description'),
                'status'      => $request->input('status', 'published'), // default to published
                'created_by'  => Auth::id(),
                'modified_by' => Auth::id(),
            ]);

            // Internal users (system users by ID)
            if ($request->filled('internal_users')) {
                $internalUsers = User::whereIn('id', $request->internal_users)->get(['id', 'email']);
                foreach ($internalUsers as $user) {
                    NoticePropagation::create([
                        'notice_id'  => $notice->id,
                        'user_id'    => $user->id,
                        'user_email' => $user->email,
                        'name'       => null,          // Leave name null for internal
                        'is_read'    => false,
                        'sent_at'  => Carbon::now() // Automatically set sent_at to now
                    ]);
                }

                try {
                    // Option A: queueable send (recommended in prod)
                    Notification::send($internalUsers, new NoticeSent($notice));

                    Log::info('Notice notifications queued for internal users', [
                        'notice_id' => $notice->id,
                        'count'     => $internalUsers->count(),
                        'users'     => $internalUsers->pluck('email'),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Error sending notice notifications', [
                        'notice_id' => $notice->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // External users (only name & email)
            if ($request->filled('external_users')) {
                foreach ($request->external_users as $external) {
                    NoticePropagation::create([
                        'notice_id'  => $notice->id,
                        'user_id'    => null,                 // No user_id for external
                        'user_email' => $external['email'],
                        'name'       => $external['name'],
                        'is_read'    => false,
                        'sent_at'  => Carbon::now(),  // Automatically set sent_at to now
                    ]);
                }
            }

            // Handle attachments (as before)
            $files = $request->file('attachments');
            if ($request->hasFile('attachments')) {
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $filename = uniqid() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('notices'), $filename);
                    $filePath = url('notices/' . $filename);

                    $notice->attachments()->create([
                        'file_name'   => $filename,
                        'file_type'   => $file->getClientOriginalExtension(),
                        'file_path'   => $filePath,
                        'uploaded_at' => now(),
                    ]);
                }
            }

            dispatch(new SendNoticeEmailsJob($notice->id));

            $notice->load(['attachments', 'propagations']);

            return response()->json([
                'success' => true,
                'message' => 'Notice created successfully.',
                'data'    => $notice,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating notice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notice.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/notices/draft",
     *     tags={"Notice"},
     *     summary="Create a new draft notice (with internal/external users and attachments)",
     *     description="Creates a new notice in draft mode. You may assign to multiple internal users (by user IDs) or external users (by name and email), and upload multiple attachments. The notice is not published until you call the publish endpoint.",
     *     operationId="draftNotice",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Draft notice creation payload. For file upload, use multipart/form-data.",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "description"},
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Notice headline/title (required, max 255 chars).",
     *                     example="Board Meeting (Draft)"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Main content/body of the notice (required).",
     *                     example="There will be a board meeting. Details will be finalized before publishing."
     *                 ),
     *                 @OA\Property(
     *                     property="internal_users",
     *                     type="array",
     *                     description="Optional array of internal user IDs (must exist in users table).",
     *                     @OA\Items(type="integer", example=7)
     *                 ),
     *                 @OA\Property(
     *                     property="external_users",
     *                     type="array",
     *                     description="Optional array of objects for external users (name & email required).",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"name", "email"},
     *                         @OA\Property(property="name", type="string", example="Jane External"),
     *                         @OA\Property(property="email", type="string", format="email", example="jane.external@example.com")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     enum={"draft"},
     *                     description="Always set as 'draft' for this endpoint.",
     *                     example="draft"
     *                 ),
     *                 @OA\Property(
     *                     property="priority_level",
     *                     type="string",
     *                     description="Priority level (optional, e.g., high, normal, low)",
     *                     example="normal"
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="Optional multiple files: PDF, DOC, DOCX, XLSX, XLS, JPG, JPEG, PNG (10MB max each).",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Draft notice created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notice created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=11),
     *                 @OA\Property(property="title", type="string", example="Board Meeting (Draft)"),
     *                 @OA\Property(property="description", type="string", example="There will be a board meeting. Details will be finalized before publishing."),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="priority_level", type="string", example="normal"),
     *                 @OA\Property(property="created_by", type="integer", example=2),
     *                 @OA\Property(property="modified_by", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="List of uploaded attachments",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="file_name", type="string", example="5f8d9abc_file.pdf"),
     *                         @OA\Property(property="file_type", type="string", example="pdf"),
     *                         @OA\Property(property="file_path", type="string", example="https://example.com/notices/5f8d9abc_file.pdf"),
     *                         @OA\Property(property="uploaded_at", type="string", format="date-time", example="2025-08-03T15:32:08Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="propagations",
     *                     type="array",
     *                     description="All target recipients (internal/external) - all with sent_at as null.",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=20),
     *                         @OA\Property(property="notice_id", type="integer", example=11),
     *                         @OA\Property(property="user_id", type="integer", nullable=true, example=7),
     *                         @OA\Property(property="name", type="string", nullable=true, example="Jane External"),
     *                         @OA\Property(property="user_email", type="string", example="jane.external@example.com"),
     *                         @OA\Property(property="is_read", type="boolean", example=false),
     *                         @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null),
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="title",
     *                     type="array",
     *                     @OA\Items(type="string", example="The title field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="attachments.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The attachments.0 must be a file of type: pdf, doc, docx, xlsx, xls, jpg, jpeg, png.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create notice",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create notice."),
     *             @OA\Property(property="error", type="string", nullable=true, example="SQLSTATE[23000]...")
     *         )
     *     )
     * )
     */

    // draft notices
    public function draftNotices(Request $request)
    {
        try {
            // Validation
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'internal_users' => 'nullable|array',
                'internal_users.*' => 'exists:users,id',
                'status' => 'nullable|string|in:draft,published', // Add this line
                'external_users' => 'nullable|array',
                'external_users.*.email' => 'required_with:external_users|email',
                'external_users.*.name' => 'required_with:external_users|string',
                'attachments' => 'sometimes|array',
                'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xlsx,xls,jpg,jpeg,png|max:10240', // 10MB max
            ]);

            // Create the notice
            $notice = Notice::create([
                'title'       => $request->input('title'),
                'description' => $request->input('description'),
                'status'      => $request->input('status', 'draft'), // default to draft
                'created_by'  => Auth::id(),
                'modified_by' => Auth::id(),
            ]);

            // Internal users (system users by ID)
            if ($request->filled('internal_users')) {
                $internalUsers = User::whereIn('id', $request->internal_users)->get(['id', 'email']);
                foreach ($internalUsers as $user) {
                    NoticePropagation::create([
                        'notice_id'  => $notice->id,
                        'user_id'    => $user->id,
                        'user_email' => $user->email,
                        'name'       => null,          // Leave name null for internal
                        'is_read'    => false,
                        'sent_at'  => null,  // Automatically set sent_at to null
                    ]);
                }
            }

            // External users (only name & email)
            if ($request->filled('external_users')) {
                foreach ($request->external_users as $external) {
                    NoticePropagation::create([
                        'notice_id'  => $notice->id,
                        'user_id'    => null,                 // No user_id for external
                        'user_email' => $external['email'],
                        'name'       => $external['name'],
                        'is_read'    => false,
                        'sent_at'  => null,  // Automatically set sent_at to null
                    ]);
                }
            }

            // Handle attachments (as before)
            $files = $request->file('attachments');
            if ($request->hasFile('attachments')) {
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $filename = uniqid() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('notices'), $filename);
                    $filePath = url('notices/' . $filename);

                    $notice->attachments()->create([
                        'file_name'   => $filename,
                        'file_type'   => $file->getClientOriginalExtension(),
                        'file_path'   => $filePath,
                        'uploaded_at' => now(),
                    ]);
                }
            }

            $notice->load(['attachments', 'propagations']);

            return response()->json([
                'success' => true,
                'message' => 'Notice created successfully.',
                'data'    => $notice,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating notice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notice.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/notices/{id}/publish",
     *     tags={"Notice"},
     *     summary="Publish a draft notice (set status and send to all recipients)",
     *     description="Publishes a draft notice. Updates the notice status to 'published' and sets 'sent_at' for all recipients who have not yet received the notice.",
     *     operationId="publishNotice",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the draft notice to publish.",
     *         @OA\Schema(type="integer", example=11)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notice published successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notice published successfully."),
     *             @OA\Property(property="total_sent", type="integer", example=12)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Notice is already published",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Notice is already published."),
     *             @OA\Property(property="notice_id", type="integer", example=11),
     *             @OA\Property(property="status", type="string", example="published")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No unsent propagations found or Notice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No unsent propagations found for this notice."),
     *             @OA\Property(property="notice_id", type="integer", example=11),
     *             @OA\Property(property="error", type="string", example="Notice not found.", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to publish notice",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to publish notice."),
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]...", nullable=true)
     *         )
     *     )
     * )
     */

    public function publish($id)
    {
        try {
            $notice = Notice::with(['propagations'])->findOrFail($id);

            if ($notice->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice is already published.',
                    'notice_id' => $notice->id,
                    'status' => $notice->status,
                ], 400);
            }

            $notice->status = 'published';
            $notice->save();

            $affectedRows = NoticePropagation::where('notice_id', $notice->id)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);

            if ($affectedRows === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No unsent propagations found for this notice.',
                    'notice_id' => $notice->id,
                ], 404);
            }

            $internalUsers = $notice->propagations->whereNotNull('user_id')->pluck('user_email')->unique();

            try {
                // Option A: queueable send (recommended in prod)
                Notification::send($internalUsers, new NoticeSent($notice));

                Log::info('Notice notifications queued for internal users', [
                    'notice_id' => $notice->id,
                    'count'     => $internalUsers->count(),
                    'users'     => $internalUsers->pluck('email'),
                ]);
            } catch (\Throwable $e) {
                Log::error('Error sending notice notifications', [
                    'notice_id' => $notice->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            dispatch(new SendNoticeEmailsJob($notice->id));

            return response()->json([
                'success' => true,
                'message' => 'Notice published successfully. Emails are being sent in the background.',
                'total_sent' => $notice->propagations->whereNotNull('sent_at')->count(),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notice not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error publishing notice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'notice_id' => $id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish notice.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/notices/drafts",
     *     tags={"Notice"},
     *     summary="List all draft notices (paginated)",
     *     description="Returns a paginated list of all draft notices, including creator, modifier, attachments, and propagations. Supports a per_page query parameter for pagination.",
     *     operationId="draftNoticesList",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of draft notices per page (default: 10).",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated draft notice list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=23),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=11),
     *                         @OA\Property(property="title", type="string", example="Board Meeting (Draft)"),
     *                         @OA\Property(property="description", type="string", example="Draft description."),
     *                         @OA\Property(property="status", type="string", example="draft"),
     *                         @OA\Property(property="priority_level", type="string", example="normal"),
     *                         @OA\Property(property="created_by", type="integer", example=2),
     *                         @OA\Property(property="modified_by", type="integer", example=2),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-03T15:32:08Z"),
     *                         @OA\Property(
     *                             property="creator",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Admin User"),
     *                             @OA\Property(property="email", type="string", example="admin@example.com")
     *                         ),
     *                         @OA\Property(
     *                             property="modifier",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Admin User"),
     *                             @OA\Property(property="email", type="string", example="admin@example.com")
     *                         ),
     *                         @OA\Property(
     *                             property="attachments",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="file_name", type="string", example="draft_file.pdf"),
     *                                 @OA\Property(property="file_type", type="string", example="pdf"),
     *                                 @OA\Property(property="file_path", type="string", example="https://example.com/notices/draft_file.pdf"),
     *                                 @OA\Property(property="uploaded_at", type="string", format="date-time", example="2025-08-03T15:32:08Z")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="propagations",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=20),
     *                                 @OA\Property(property="notice_id", type="integer", example=11),
     *                                 @OA\Property(property="user_id", type="integer", nullable=true, example=7),
     *                                 @OA\Property(property="name", type="string", nullable=true, example="Jane External"),
     *                                 @OA\Property(property="user_email", type="string", example="jane.external@example.com"),
     *                                 @OA\Property(property="is_read", type="boolean", example=false),
     *                                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null)
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="message", type="string", nullable=true, example="No draft notices found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Could not retrieve draft notices."),
     *             @OA\Property(property="error", type="string", nullable=true, example="SQLSTATE[23000]...")
     *         )
     *     )
     * )
     */

    public function draftNoticesList(Request $request)
    {
        try {
            $notices = Notice::where('status', 'draft')
                ->with(['creator', 'modifier', 'attachments', 'propagations'])
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 10)); // Support per_page param

            return response()->json([
                'success' => true,
                'data'    => $notices,
                'message' => $notices->count() ? 'Draft notices retrieved successfully.' : 'No draft notices found.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching draft notices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve draft notices.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/notices/{id}",
     *     tags={"Notice"},
     *     summary="Update an existing notice",
     *     description="Update a notice by ID. Partial update allowed; fields you omit will remain unchanged. You can upload new attachments (same format as store).",
     *     operationId="updateNotice",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the notice to update",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Notice update payload. Only send fields you want to update. Use multipart/form-data for file upload.",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Updated notice title (optional, max 255 chars).",
     *                     example="Updated Meeting Title"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Updated body/content of the notice (optional).",
     *                     example="The meeting time has changed to 11am."
     *                 ),
     *                 @OA\Property(
     *                     property="attachments[]",
     *                     type="array",
     *                     description="Optional new attachments (same formats as create). Existing attachments remain unless removed elsewhere.",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notice updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Notice")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notice not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Notice not found."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="attachments.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The attachments.0 must be a file of type: pdf, doc, docx, xlsx, xls, jpg, jpeg, png.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
     */
    // Update a notice
    public function updateNotice(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Validation
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'attachments' => 'sometimes|array',
                'modified_by' => 'nullable|exists:users,id',
                'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xlsx,xls,jpg,jpeg,png|max:10240',
                'internal_users' => 'nullable|array',
                'internal_users.*' => 'exists:users,id',
                'external_users' => 'nullable|array',
                'external_users.*.email' => 'required_with:external_users|email',
                'external_users.*.name' => 'required_with:external_users|string',
            ]);

            $notice = Notice::with('propagations')->findOrFail($id);

            // Update basic fields
            $notice->title = $request->input('title');
            $notice->description = $request->input('description');
            $notice->modified_by = Auth::id();
            
            $notice->save();

            $newPropagationIdsToEmail = [];

            // === Internal users reconciliation ===
            if ($request->has('internal_users')) {
                $requestedInternal = array_unique($request->input('internal_users', []));
                $existingInternal = $notice->propagations
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->map(fn($v) => (int)$v)
                    ->toArray();

                $toAdd = array_diff($requestedInternal, $existingInternal);
                $toRemove = array_diff($existingInternal, $requestedInternal);

                // Remove deselected
                if (!empty($toRemove)) {
                    NoticePropagation::where('notice_id', $notice->id)
                        ->whereIn('user_id', $toRemove)
                        ->delete();
                }

                // Add new internal
                if (!empty($toAdd)) {
                    $users = User::whereIn('id', $toAdd)->get(['id', 'email']);
                    foreach ($users as $user) {
                        $prop = NoticePropagation::create([
                            'notice_id'  => $notice->id,
                            'user_id'    => $user->id,
                            'user_email' => $user->email,
                            'name'       => null,
                            'is_read'    => false,
                            'sent_at'    => $notice->status === 'published' ? now() : null,
                        ]);
                        if ($notice->status === 'published') {
                            $newPropagationIdsToEmail[] = $prop->id;
                        }
                    }
                }
            }

            // === External users replacement ===
            if ($request->has('external_users')) {
                // Remove all existing external (user_id null)
                NoticePropagation::where('notice_id', $notice->id)
                    ->whereNull('user_id')
                    ->delete();

                foreach ($request->external_users as $external) {
                    $prop = NoticePropagation::create([
                        'notice_id'  => $notice->id,
                        'user_id'    => null,
                        'user_email' => $external['email'],
                        'name'       => $external['name'],
                        'is_read'    => false,
                        'sent_at'    => $notice->status === 'published' ? now() : null,
                    ]);
                    if ($notice->status === 'published') {
                        $newPropagationIdsToEmail[] = $prop->id;
                    }
                }
            }

            // Handle attachments (replace if new ones uploaded)
            if ($request->hasFile('attachments')) {
                foreach ($notice->attachments as $attachment) {
                    $filePath = public_path('notices/' . $attachment->file_name);
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                    $attachment->delete();
                }

                $files = $request->file('attachments');
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $filename = uniqid() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('notices'), $filename);
                    $filePath = url('notices/' . $filename);

                    $notice->attachments()->create([
                        'file_name'   => $filename,
                        'file_type'   => $file->getClientOriginalExtension(),
                        'file_path'   => $filePath,
                        'uploaded_at' => now(),
                    ]);
                }
            }

            // If notice already published and new recipients were added, enqueue emails just for them
            if ($notice->status === 'published' && !empty($newPropagationIdsToEmail)) {
                dispatch(new SendNoticeEmailsJob($notice->id, $newPropagationIdsToEmail));
            }

            DB::commit();

            $notice->load(['attachments', 'propagations']);

            return response()->json([
                'success' => true,
                'message' => 'Notice updated successfully.',
                'data' => $notice,
                'new_propagation_emails_dispatched_for' => $newPropagationIdsToEmail,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating notice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notice.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/notices/{id}",
     *     tags={"Notice"},
     *     summary="Delete a notice",
     *     description="Deletes a notice by ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the notice",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Notice deleted successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notice not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Notice not found."))
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
     */
    // Delete a notice
    public function destroy($id)
    {
        try {
            $notice = Notice::with(['propagations', 'attachments'])->find($id);

            if (! $notice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice not found.',
                ], 404);
            }

            DB::beginTransaction();

            // 1) delete propagations
            $notice->propagations()->delete();

            // 2) delete attachment files + rows
            foreach ($notice->attachments as $attachment) {
                // adjust this if you store full path
                $filePath = public_path('notices/' . $attachment->file_name);
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $notice->attachments()->delete();

            // 3) delete notice
            $notice->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notice deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error deleting notice: '.$e->getMessage(), [
                'notice_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notice.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function downloadAttachments($id)
    {
        try {
            $notice = Notice::with('attachments')->findOrFail($id);

            $attachments = $notice->attachments;
            if ($attachments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attachments found for this notice.'
                ], 404);
            }  

            $zipFileName = 'notice_' . $notice->id . '_attachments.zip';
            $zipFilePath = public_path('notices/' . $zipFileName);

            // Ensure directory exists
            if (!file_exists(public_path('notices'))) {
                mkdir(public_path('notices'), 0777, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not create zip file.'
                ], 500);
            }
            foreach ($attachments as $attachment) {
                $filePath = public_path('notices/' . $attachment->file_name);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $attachment->file_name);
                } else {
                    Log::warning('Attachment file not found: ' . $filePath);
                }
            }
            $zip->close();

            if (!file_exists($zipFilePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create zip file.'
                ], 500);
            }
            // This downloads AND leaves the zip file for local use/history
            return response()->json([
                'success' => true,
                'message' => 'ZIP file created.',
                'url'     => url('notices/' . $zipFileName),
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading attachments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download attachments.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function downloadSingleAttachment($noticeId, $attachmentId)
    {
        try {
            $notice = Notice::findOrFail($noticeId);

            // Ensure the attachment belongs to this notice
            /** @var NoticeAttachment $attachment */
            $attachment = $notice->attachments()->where('id', $attachmentId)->firstOrFail();

            // If you store only a file name under /public/notices
            $filePath = public_path('notices/' . $attachment->file_name);

            if (!file_exists($filePath)) {
                Log::warning('Attachment file not found: '.$filePath);

                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            $downloadName = $attachment->original_name ?: $attachment->file_name; // optional pretty name
            $mime         = File::mimeType($filePath) ?: 'application/octet-stream';

            // Stream the file to the browser with correct headers
            return response()->download($filePath, $downloadName, [
                'Content-Type'              => $mime,
                'Content-Disposition'       => 'attachment; filename="'.addslashes($downloadName).'"',
                'X-Content-Type-Options'    => 'nosniff',
            ]);

        } catch (\Throwable $e) {
            Log::error('Error downloading single attachment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to download attachment.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function noticeMarkAsRead($id)
    {
        try{
            $notice = Notice::findOrFail($id);
            
            $noticePropagation = NoticePropagation::where('notice_id', $notice->id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            $noticePropagation->is_read = 1;
            $noticePropagation->save();

            return response()->json([
                'success' => true,
                'message' => 'Notice marked as read.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notice as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notice as read.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function noticeSearch(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $page    = (int) $request->input('page', 1);

            // Search + date filters (all optional)
            $q    = trim((string) $request->query('q', ''));     // search text in title/description
            $date = trim((string) $request->query('date', ''));  // YYYY-MM-DD
            $from = trim((string) $request->query('from', ''));  // YYYY-MM-DD
            $to   = trim((string) $request->query('to', ''));    // YYYY-MM-DD

            // If your API is authenticated, keep this:
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            $query = Notice::query()
                // Keep the same scope as your main index if needed:
                ->where('created_by', $user->id)
                // Text search
                ->when($q !== '', function ($qBuilder) use ($q) {
                    $qBuilder->where(function ($w) use ($q) {
                        $w->where('title', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                    });
                })
                // Single-day filter (created_at)
                ->when($date !== '', fn($qBuilder) => $qBuilder->whereDate('created_at', $date))
                // Date range (created_at)
                ->when(($from !== '' || $to !== '') && $date === '', function ($qBuilder) use ($from, $to) {
                    if ($from !== '' && $to !== '') {
                        $qBuilder->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
                    } elseif ($from !== '') {
                        $qBuilder->where('created_at', '>=', "{$from} 00:00:00");
                    } else { // only $to
                        $qBuilder->where('created_at', '<=', "{$to} 23:59:59");
                    }
                })
                ->orderBy('created_at', 'desc');

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // ✅ Add running index `i`
            if ($results->count()) {
                $start = $results->firstItem() - 1;
                $results->setCollection(
                    $results->getCollection()->values()->map(function ($notice, $k) use ($start) {
                        $notice->setAttribute('i', $start + $k + 1);
                        return $notice;
                    })
                );
            }

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Notice search error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}