<?php

namespace App\Http\Controllers\Api;

use App\Models\NoticeTemplate;
use App\Models\NoticeTemplateDistribution;
use App\Models\NoticeTemplateRegard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class NoticeTemplateController extends Controller
{
    /**
     * List with simple pagination.
     * GET /api/notice-templates?page=1
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $user = $request->user();

        $query = NoticeTemplate::with(['distributions', 'regards', 'user.designation', 'approver']);

        // If user is PO, only show their own notices
        if ($user->hasRole('PO')) {
            $query->where('user_id', $user->id);
        }
        

        $data = $query->latest('id')->paginate($perPage);

        return response()->json($data);
    }

    public function myNotices(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $user = $request->user();
        
        // Optional: Check if user is PO (add middleware in routes instead)
        if (!$user->hasRole('PO')) {
            return response()->json(['error' => 'Unauthorized. This route is for PO users only.'], 403);
        }
        
        $data = NoticeTemplate::with(['distributions', 'regards', 'approver'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($data);
    }
    /**
     * Show single template with children.
     * GET /api/notice-templates/{id}
     */
    public function show($id)
    {
        $tpl = NoticeTemplate::with(['distributions', 'regards', 'user.designation'])
            ->findOrFail($id);

        // Calculate if the notice has been edited
        $isEdited = !$tpl->created_at->eq($tpl->updated_at);
        
        $tpl->is_edited = $isEdited;
        $tpl->created_at_formatted = $tpl->created_at->format('d/m/Y');
        $tpl->updated_at_formatted = $tpl->updated_at->format('d/m/Y');
        
        // Also add Bangla date for display
        $tpl->date_bn = $this->bnDigits($tpl->date->format('d/m/Y'));

        return response()->json($tpl);
    }

    /**
     * Create a new template.
     * POST /api/notice-templates
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'memorial_no'    => ['required','string','max:100','unique:notice_templates,memorial_no'],
            'date'           => ['required','date'],
            'subject'        => ['required','string','max:255'],
            'body'           => ['required','string'],
            'signature_body' => ['nullable','string'],
            'status'         => ['nullable','in:draft,pending,published,archived'],
            'is_active'      => ['nullable','boolean'],

            // Distributions payload
            'distributions.internal_user_ids'      => ['nullable','array'],
            'distributions.internal_user_ids.*'    => ['nullable','integer','exists:users,id'],
            'distributions.external_users'         => ['nullable','array'],
            'distributions.external_users.*.name'  => ['required_with:distributions.external_users','string','max:150'],
            'distributions.external_users.*.designation' => ['nullable','string','max:150'],

            // Regards payload
            'regards.internal_user_ids'            => ['nullable','array'],
            'regards.internal_user_ids.*'          => ['nullable','integer','exists:users,id'],
            'regards.external_users'               => ['nullable','array'],
            'regards.external_users.*.name'        => ['required_with:regards.external_users','string','max:150'],
            'regards.external_users.*.designation' => ['nullable','string','max:150'],
            'regards.external_users.*.note'        => ['nullable','string'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = $request->user();
            $status = $validated['status'] ?? 'draft';
            
            // Set approval status based on user role
            $approvalStatus = 'pending';
            $approvedBy = null;
            $approvedAt = null;
            
            if ($user->hasRole('PO')) {
                // PO cannot publish directly, needs AEPD approval
                $approvalStatus = 'pending';
                if ($status === 'published') {
                    $status = 'pending'; // Set to pending for approval
                }
            } elseif ($user->hasRole('AEPD')) {
                // AEPD can publish directly or approve PO's notices
                $approvalStatus = 'approved';
                $approvedBy = $user->id;
                $approvedAt = now();
                if ($status === 'published') {
                    $approvalStatus = 'approved';
                }
            } elseif ($user->hasRole('Admin')) {
                // Admin can publish directly
                $approvalStatus = 'approved';
                $approvedBy = $user->id;
                $approvedAt = now();
                if ($status === 'published') {
                    $approvalStatus = 'approved';
                }
            }

            $tpl = NoticeTemplate::create([
                'user_id'        => $user->id,
                'memorial_no'    => $validated['memorial_no'],
                'date'           => $validated['date'],
                'subject'        => $validated['subject'],
                'body'           => $validated['body'],
                'signature_body' => $validated['signature_body'] ?? null,
                'status'         => $status,
                'approval_status'=> $approvalStatus,
                'approved_by'    => $approvedBy,
                'approved_at'    => $approvedAt,
            ]);

            // build children
            $this->syncDistributions($tpl, $validated['distributions'] ?? []);
            $this->syncRegards($tpl, $validated['regards'] ?? []);

            $tpl->load(['distributions','regards','user','approver']);
            return response()->json($tpl, 201);
        });
    }

    /**
     * Update template
     */
    public function update(Request $request, $id)
    {
        $tpl = NoticeTemplate::findOrFail($id);
        $user = $request->user();

        $validated = $request->validate([
            'memorial_no'    => ['sometimes','string','max:100','unique:notice_templates,memorial_no,'.$tpl->id],
            'date'           => ['sometimes','date'],
            'subject'        => ['sometimes','string','max:255'],
            'body'           => ['sometimes','string'],
            'signature_body' => ['nullable','string'],
            'status'         => ['sometimes','in:draft,pending,published,archived'],
            'is_active'      => ['sometimes','boolean'],

            // keep parent keys so they remain in $validated
            'distributions' => ['sometimes','array'],
            'distributions.internal_user_ids'      => ['sometimes','array'],
            'distributions.internal_user_ids.*'    => ['integer','exists:users,id'],
            'distributions.external_users'         => ['sometimes','array'],
            'distributions.external_users.*.name'  => ['required_with:distributions.external_users','string','max:150'],
            'distributions.external_users.*.designation' => ['nullable','string','max:150'],

            'regards' => ['sometimes','array'],
            'regards.internal_user_ids'            => ['sometimes','array'],
            'regards.internal_user_ids.*'          => ['integer','exists:users,id'],
            'regards.external_users'               => ['sometimes','array'],
            'regards.external_users.*.name'        => ['required_with:regards.external_users','string','max:150'],
            'regards.external_users.*.designation' => ['nullable','string','max:150'],
            'regards.external_users.*.note'        => ['nullable','string'],
        ]);

        return DB::transaction(function () use ($validated, $tpl, $request, $user) {
            // Handle approval logic
            $parentUpdates = [];
            
            if (isset($validated['status'])) {
                if ($user->hasRole('PO')) {
                    // PO can only save as draft or submit for approval
                    if ($validated['status'] === 'published') {
                        $parentUpdates['status'] = 'pending';
                        $parentUpdates['approval_status'] = 'pending';
                    } else {
                        $parentUpdates['status'] = $validated['status'];
                    }
                } elseif ($user->hasRole('AEPD') && $tpl->approval_status === 'pending') {
                    // AEPD can approve PO's pending notices
                    if ($validated['status'] === 'published') {
                        $parentUpdates['approval_status'] = 'approved';
                        $parentUpdates['approved_by'] = $user->id;
                        $parentUpdates['approved_at'] = now();
                        $parentUpdates['status'] = 'published';
                    }
                } elseif ($user->hasRole('Admin') || $user->hasRole('AEPD')) {
                    // Admin/AEPD can publish directly
                    if ($validated['status'] === 'published') {
                        $parentUpdates['approval_status'] = 'approved';
                        $parentUpdates['approved_by'] = $user->id;
                        $parentUpdates['approved_at'] = now();
                    }
                    $parentUpdates['status'] = $validated['status'];
                }
            }

            // Update other fields
            foreach (['memorial_no','date','subject','body','signature_body','is_active'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $parentUpdates[$key] = $validated[$key];
                }
            }

            if ($parentUpdates) {
                $tpl->update($parentUpdates);
            }

            if ($request->has('distributions')) {
                $tpl->distributions()->delete();
                $this->syncDistributions($tpl, $validated['distributions'] ?? []);
            }

            if ($request->has('regards')) {
                $tpl->regards()->delete();
                $this->syncRegards($tpl, $validated['regards'] ?? []);
            }

            return response()->json(
                $tpl->load(['distributions','regards','user','approver'])
            );
        });
    }

    public function approve(Request $request, $id)
    {
        $tpl = NoticeTemplate::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('AEPD')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($tpl->approval_status !== 'pending') {
            return response()->json(['error' => 'Notice is not pending approval'], 400);
        }

        $tpl->update([
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'status' => 'published',
        ]);

        return response()->json(['message' => 'Notice approved successfully', 'notice' => $tpl]);
    }

    /**
     * Reject notice (for AEPD role)
     */
    public function reject(Request $request, $id)
    {
        $tpl = NoticeTemplate::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('AEPD')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $tpl->update([
            'approval_status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'status' => 'draft',
        ]);

        return response()->json(['message' => 'Notice rejected', 'notice' => $tpl]);
    }

    /**
     * Get pending approval notices
     */
    public function pendingApproval(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('AEPD')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notices = NoticeTemplate::with(['user.designation', 'distributions', 'regards'])
            ->where('approval_status', 'pending')
            ->latest()
            ->paginate(10);

        return response()->json($notices);
    }

    /**
     * Get my created notices (for PO)
     */

    /**
     * Delete template (children cascade via FK).
     * DELETE /api/notice-templates/{id}
     */
    public function destroy($id)
    {
        $tpl = NoticeTemplate::findOrFail($id);
        $tpl->delete();
        return response()->json(['ok' => true]);
    }

    /* =========================
     * Helpers (internal/external)
     * ========================= */

    // app/Http/Controllers/NoticeTemplateController.php

	private function syncDistributions(NoticeTemplate $tpl, array $payload): void
	{
		$internalIds   = $payload['internal_user_ids'] ?? [];
		$externalUsers = $payload['external_users'] ?? [];

		// INTERNAL (copy from users + their designation relation)
		$rows = [];
		if (!empty($internalIds)) {
			$users = User::with('designation:id,name')
				->whereIn('id', $internalIds)
				->get(['id','name','designation_id']);

			foreach ($users as $u) {
				$rows[] = [
					'name'        => $u->name,
					'designation' => optional($u->designation)->name,
					'is_active'   => true,
					'status'      => 'active',
				];
			}
		}

		// EXTERNAL
		if (!empty($externalUsers)) {
			foreach ($externalUsers as $x) {
				$rows[] = [
					'name'        => (string)($x['name'] ?? ''),
					'designation' => $x['designation'] ?? null,
					'is_active'   => true,
					'status'      => 'active',
				];
			}
		}

		if ($rows) {
			// THIS auto-fills notice_template_id + timestamps
			$tpl->distributions()->createMany($rows);
		}
	}

	private function syncRegards(NoticeTemplate $tpl, array $payload): void
	{
		$internalIds   = $payload['internal_user_ids'] ?? [];
		$externalUsers = $payload['external_users'] ?? [];

		$rows = [];

		// INTERNAL (note = null)
		if (!empty($internalIds)) {
			$users = User::with('designation:id,name')
				->whereIn('id', $internalIds)
				->get(['id','name','designation_id']);

			foreach ($users as $u) {
				$rows[] = [
					'name'        => $u->name,
					'designation' => optional($u->designation)->name,
					'note'        => null,
					'is_active'   => true,
					'status'      => 'active',
				];
			}
		}

		// EXTERNAL (includes note)
		if (!empty($externalUsers)) {
			foreach ($externalUsers as $x) {
				$rows[] = [
					'name'        => (string)($x['name'] ?? ''),
					'designation' => $x['designation'] ?? null,
					'note'        => $x['note'] ?? null,
					'is_active'   => true,
					'status'      => 'active',
				];
			}
		}

		if ($rows) {
			// auto-fills notice_template_id + timestamps
			$tpl->regards()->createMany($rows);
		}
	}

    public function download($id)
    {
        $template = NoticeTemplate::with(['distributions','regards','user.designation','approver.designation'])
            ->findOrFail($id);

        $isEdited = !$template->created_at->eq($template->updated_at);

        $data = [
            // fixed header lines (Bangla)
            'header_lines' => [
                'গণপ্রজাতন্ত্রী বাংলাদেশ সরকার',
                'অর্থ বিভাগ, অর্থ মন্ত্রণালয়',
                'স্কিলস ফর ইন্ডাস্ট্রি কম্পিটিটিভনেস এন্ড ইনোভেশন প্রোগ্রাম (SICIP)',
                'প্রবাসী কল্যাণ ভবন (১৫-১৬ তলা)',
                '৭১-৭২, ইস্টার্ন গার্ডেন, রমনা, ঢাকা-১০০০',
                'https://sicib.gov.bd',
            ],

            // dynamic
            'memorial_no'     => $template->memorial_no,
            'date_bn'         => $this->bnDigits($template->date->format('d/m/Y')),
            'subject'         => $template->subject,
            'body'            => $template->body,

            // signature (name from user, extra body from template)
            'sign_name'       => $template->user->name ?? '',
            'sign_designation'=> optional($template->user->designation)->name,
            'signature_body'  => $template->signature_body ?? '',

            // approver signature (if approved by AEPD)
            'approver_name'   => $template->approver->name ?? null,
            'approver_designation' => optional($template->approver->designation)->name ?? null,
            'approval_status' => $template->approval_status,
            'approved_at'     => $template->approved_at ? $template->approved_at->format('d/m/Y') : null,

            // lists
            'distributions'   => $template->distributions,
            'regards'         => $template->regards,

            'is_edited'       => $isEdited,
            'created_at'      => $template->created_at->format('d/m/Y'),
            'updated_at'      => $template->updated_at->format('d/m/Y'),
        ];

        // load blade -> pdf
        $pdf = Pdf::loadView('pdf.notice_template', $data);

        // download as attachment
        $filename = 'notice_'.$template->memorial_no.'.pdf';
        return $pdf->download($filename);
    }

    private function bnDigits(string $s): string
    {
        $en = ['0','1','2','3','4','5','6','7','8','9','-','/'];
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯','-','/'];
        return str_replace($en, $bn, $s);
    }
}