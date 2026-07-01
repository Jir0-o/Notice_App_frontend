@extends('layouts.master')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Contact Message Details</h3>
            <p class="text-muted mb-0">{{ $contactMessage->contact_no }}</p>
        </div>

        <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-light">
            Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">{{ $contactMessage->subject }}</h5>

                    <div class="border rounded p-3 bg-light" style="white-space: pre-line;">
                        {{ $contactMessage->message }}
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Name</div>
                            <div class="fw-semibold">{{ $contactMessage->name }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold">{{ $contactMessage->email }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Phone</div>
                            <div class="fw-semibold">{{ $contactMessage->phone ?: 'N/A' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Submitted At</div>
                            <div class="fw-semibold">{{ $contactMessage->created_at->format('d M Y, h:i A') }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">IP Address</div>
                            <div class="fw-semibold">{{ $contactMessage->ip_address ?: 'N/A' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">User Agent</div>
                            <div class="fw-semibold small">{{ $contactMessage->user_agent ?: 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Admin Action</h5>

                    <form method="POST" action="{{ route('admin.contact-messages.update', $contactMessage) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                @foreach(['normal', 'high', 'urgent'] as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', $contactMessage->priority) === $priority)>
                                        {{ ucfirst($priority) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('priority')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach(['new', 'read', 'replied', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $contactMessage->status) === $status)>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Admin Note</label>
                            <textarea name="admin_note" class="form-control" rows="6"
                                      placeholder="Write internal note...">{{ old('admin_note', $contactMessage->admin_note) }}</textarea>
                            @error('admin_note')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-primary w-100">
                            Update Message
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('admin.contact-messages.destroy', $contactMessage) }}"
                          class="mt-2"
                          onsubmit="return confirm('Are you sure you want to delete this message?')">
                        @csrf
                        @method('DELETE')

                        <button class="btn btn-outline-danger w-100">
                            Delete Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection