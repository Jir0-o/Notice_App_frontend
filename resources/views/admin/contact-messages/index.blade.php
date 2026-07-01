@extends('layouts.master')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Contact Messages</h3>
            <p class="text-muted mb-0">View and manage submitted contact messages.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">New</div>
                    <h4 class="mb-0">{{ $counts['new'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Read</div>
                    <h4 class="mb-0">{{ $counts['read'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Replied</div>
                    <h4 class="mb-0">{{ $counts['replied'] }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Closed</div>
                    <h4 class="mb-0">{{ $counts['closed'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-5">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Search by name, email, phone, subject, tracking no">
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(['new', 'read', 'replied', 'closed'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priority</option>
                        @foreach(['normal', 'high', 'urgent'] as $priority)
                            <option value="{{ $priority }}" @selected(request('priority') === $priority)>
                                {{ ucfirst($priority) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tracking No</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($messages as $message)
                            <tr>
                                <td>
                                    <strong>{{ $message->contact_no }}</strong>
                                </td>

                                <td>
                                    <div class="fw-semibold">{{ $message->name }}</div>
                                    <div class="text-muted small">{{ $message->email }}</div>
                                    @if($message->phone)
                                        <div class="text-muted small">{{ $message->phone }}</div>
                                    @endif
                                </td>

                                <td>{{ Str::limit($message->subject, 55) }}</td>

                                <td>
                                    @php
                                        $priorityClass = [
                                            'normal' => 'secondary',
                                            'high' => 'warning',
                                            'urgent' => 'danger',
                                        ][$message->priority] ?? 'secondary';
                                    @endphp

                                    <span class="badge bg-{{ $priorityClass }}">
                                        {{ ucfirst($message->priority) }}
                                    </span>
                                </td>

                                <td>
                                    @php
                                        $statusClass = [
                                            'new' => 'primary',
                                            'read' => 'info',
                                            'replied' => 'success',
                                            'closed' => 'dark',
                                        ][$message->status] ?? 'secondary';
                                    @endphp

                                    <span class="badge bg-{{ $statusClass }}">
                                        {{ ucfirst($message->status) }}
                                    </span>
                                </td>

                                <td>
                                    <div>{{ $message->created_at->format('d M Y') }}</div>
                                    <div class="text-muted small">{{ $message->created_at->format('h:i A') }}</div>
                                </td>

                                <td class="text-end">
                                    <a href="{{ route('admin.contact-messages.show', $message) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No contact messages found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $messages->links() }}
        </div>
    </div>
</div>
@endsection