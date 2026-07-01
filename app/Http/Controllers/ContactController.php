<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact.index');
    }

    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::create([
            'contact_no' => $this->generateContactNo(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject,
            'message' => $request->message,
            'priority' => $request->priority ?: 'normal',
            'status' => 'new',
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been submitted successfully.',
            'data' => [
                'contact_no' => $message->contact_no,
            ],
        ]);
    }

    public function track(string $contact_no): JsonResponse
    {
        $contactNo = Str::upper(trim($contact_no));
        $contactNo = preg_replace('/\s+/', '', $contactNo);

        $validator = validator(
            ['contact_no' => $contactNo],
            [
                'contact_no' => [
                    'required',
                    'string',
                    'max:40',
                    'regex:/^SICIP-[0-9]{8}-[A-Z0-9]{6}$/',
                ],
            ],
            [
                'contact_no.regex' => 'Please enter a valid tracking number. Example: SICIP-20260627-XI55DT',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tracking number.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = ContactMessage::where('contact_no', $contactNo)->first();

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'No message found with this tracking number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tracking information loaded successfully.',
            'data' => [
                'contact_no' => $message->contact_no,
                'subject' => $message->subject,
                'message' => $message->message,

                'priority' => [
                    'key' => $message->priority,
                    'label' => $this->priorityLabel($message->priority),
                ],

                'status' => [
                    'key' => $message->status,
                    'label' => $this->statusLabel($message->status),
                    'description' => $this->statusDescription($message->status),
                ],

                'submitted_at' => optional($message->created_at)->format('d M Y, h:i A'),
                'last_updated_at' => optional($message->updated_at)->format('d M Y, h:i A'),

                'timeline' => [
                    [
                        'label' => 'Message Submitted',
                        'completed' => true,
                        'time' => optional($message->created_at)->format('d M Y, h:i A'),
                    ],
                    [
                        'label' => 'Seen by Admin',
                        'completed' => (bool) $message->read_at || in_array($message->status, ['read', 'replied', 'closed']),
                        'time' => optional($message->read_at)->format('d M Y, h:i A'),
                    ],
                    [
                        'label' => 'Replied / Processing',
                        'completed' => (bool) $message->replied_at || in_array($message->status, ['replied', 'closed']),
                        'time' => optional($message->replied_at)->format('d M Y, h:i A'),
                    ],
                    [
                        'label' => 'Closed',
                        'completed' => (bool) $message->closed_at || $message->status === 'closed',
                        'time' => optional($message->closed_at)->format('d M Y, h:i A'),
                    ],
                ],
            ],
        ]);
    }

    private function generateContactNo(): string
    {
        do {
            $contactNo = 'SICIP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (ContactMessage::where('contact_no', $contactNo)->exists());

        return $contactNo;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'New',
            'read' => 'Seen by Admin',
            'replied' => 'Replied / Processing',
            'closed' => 'Closed',
            default => ucfirst($status),
        };
    }

    private function statusDescription(string $status): string
    {
        return match ($status) {
            'new' => 'Your message has been submitted successfully and is waiting for admin review.',
            'read' => 'Your message has been seen by the admin team.',
            'replied' => 'Your message is being processed or has been replied to by the admin team.',
            'closed' => 'This support message has been closed.',
            default => 'Your message is under review.',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
            default => ucfirst($priority),
        };
    }
}