<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $messages = ContactMessage::query()
            ->search($request->q)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'new' => ContactMessage::where('status', 'new')->count(),
            'read' => ContactMessage::where('status', 'read')->count(),
            'replied' => ContactMessage::where('status', 'replied')->count(),
            'closed' => ContactMessage::where('status', 'closed')->count(),
        ];

        return view('admin.contact-messages.index', compact('messages', 'counts'));
    }

    public function show(ContactMessage $contactMessage): View
    {
        if ($contactMessage->status === 'new') {
            $contactMessage->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return view('admin.contact-messages.show', compact('contactMessage'));
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,read,replied,closed'],
            'priority' => ['required', 'in:normal,high,urgent'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['status'] === 'read' && !$contactMessage->read_at) {
            $data['read_at'] = now();
        }

        if ($data['status'] === 'replied' && !$contactMessage->replied_at) {
            $data['replied_at'] = now();
        }

        if ($data['status'] === 'closed' && !$contactMessage->closed_at) {
            $data['closed_at'] = now();
        }

        $contactMessage->update($data);

        return back()->with('success', 'Contact message updated successfully.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }
}