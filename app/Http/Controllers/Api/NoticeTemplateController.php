<?php

namespace App\Http\Controllers\Api;

use App\Models\NoticeTemplate;
use App\Models\NoticeTemplateDistribution;
use App\Models\NoticeTemplateRegard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class NoticeTemplateController extends Controller
{
    /**
     * List with simple pagination.
     * GET /api/notice-templates?page=1
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $data = NoticeTemplate::with(['distributions', 'regards', 'user'])
            ->latest('id')
            ->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Show single template with children.
     * GET /api/notice-templates/{id}
     */
    public function show($id)
    {
        $tpl = NoticeTemplate::with(['distributions', 'regards', 'user'])
            ->findOrFail($id);

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
            'status'         => ['nullable','in:draft,published,archived'],
            'is_active'      => ['nullable','boolean'],

            // Distributions payload
            'distributions.internal_user_ids'      => ['nullable','array'],
            'distributions.internal_user_ids.*'    => ['nullable','integer','exists:users,id'],
            'distributions.external_users'         => ['nullable','array'],
            'distributions.external_users.*.name'  => ['required_with:distributions.external_users','string','max:150'],
            'distributions.external_users.*.designation' => ['nullable','string','max:150'],

            // Regards payload (same as distributions but +note)
            'regards.internal_user_ids'            => ['nullable','array'],
            'regards.internal_user_ids.*'          => ['nullable','integer','exists:users,id'],
            'regards.external_users'               => ['nullable','array'],
            'regards.external_users.*.name'        => ['required_with:regards.external_users','string','max:150'],
            'regards.external_users.*.designation' => ['nullable','string','max:150'],
            'regards.external_users.*.note'        => ['nullable','string'],
        ]);

        return DB::transaction(function () use ($validated, $request) {

            $tpl = NoticeTemplate::create([
                'user_id'        => $request->user()->id, // creator
                'memorial_no'    => $validated['memorial_no'],
                'date'           => $validated['date'],
                'subject'        => $validated['subject'],
                'body'           => $validated['body'],
                'signature_body' => $validated['signature_body'] ?? null,
            ]);

            // build children
            $this->syncDistributions($tpl, $validated['distributions'] ?? []);
            $this->syncRegards($tpl, $validated['regards'] ?? []);

            $tpl->load(['distributions','regards','user']);
            return response()->json($tpl, 201);
        });
    }

    /**
     * Update template (simple strategy: replace children).
     * PUT /api/notice-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $tpl = NoticeTemplate::findOrFail($id);

        $validated = $request->validate([
            // parent fields (optional)
            'memorial_no'    => ['sometimes','string','max:100','unique:notice_templates,memorial_no,'.$tpl->id],
            'date'           => ['sometimes','date'],
            'subject'        => ['sometimes','string','max:255'],
            'body'           => ['sometimes','string'],
            'signature_body' => ['nullable','string'],
            'status'         => ['sometimes','in:draft,published,archived'],
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

        return DB::transaction(function () use ($validated, $tpl, $request) {
            // ✅ Explicit field assignment (don’t dump arrays into fill)
            $parentUpdates = [];
            foreach (['memorial_no','date','subject','body','signature_body','status','is_active'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $parentUpdates[$key] = $validated[$key];
                }
            }
            if ($parentUpdates) {
                $tpl->update($parentUpdates);
            }

            // ✅ Rebuild children if the client sent the key (even empty array to wipe)
            if ($request->has('distributions')) {
                $tpl->distributions()->delete();
                $this->syncDistributions($tpl, $validated['distributions'] ?? []);
            }

            if ($request->has('regards')) {
                $tpl->regards()->delete();
                $this->syncRegards($tpl, $validated['regards'] ?? []);
            }

            return response()->json(
                $tpl->load(['distributions','regards','user'])
            );
        });
    }


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
}