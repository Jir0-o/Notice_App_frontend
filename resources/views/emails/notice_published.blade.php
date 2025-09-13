<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Notice Published</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 0;
        }
        .container {
            background: #fff;
            margin: 40px auto;
            max-width: 520px;
            border-radius: 6px;
            box-shadow: 0 2px 8px #eaeaea;
            padding: 32px 28px 28px 28px;
        }
        .header {
            background: #213b52;
            color: #fff;
            padding: 14px 0 10px 0;
            text-align: center;
            border-radius: 6px 6px 0 0;
        }
        .notice-title {
            color: #213b52;
            font-size: 1.3em;
            margin-top: 28px;
            margin-bottom: 6px;
            font-weight: bold;
            letter-spacing: 0.01em;
        }
        .notice-meta {
            color: #757575;
            font-size: 0.96em;
            margin-bottom: 14px;
        }
        .content {
            color: #222;
            font-size: 1.07em;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 35px;
            color: #888;
            font-size: 0.93em;
            border-top: 1px solid #f0f0f0;
            padding-top: 10px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 9px 22px;
            background: #356de8;
            color: #fff !important;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span style="font-size:1.22em;font-weight:bold;">Notice Board</span>
        </div>
        <p style="margin:25px 0 10px 0;">
            Dear {{ $recipientName ?? 'User' }},
        </p>
        <div class="notice-title">{{ $notice->title }}</div>
        <div class="notice-meta">
            @if(isset($notice->priority_level))
                <span><strong>Priority:</strong> {{ ucfirst($notice->priority_level) }} &nbsp;|&nbsp;</span>
            @endif
            <span><strong>Status:</strong> {{ ucfirst($notice->status) }}</span>
            <br>
            <span>
                <strong>Published at:</strong>
                {{ \Carbon\Carbon::parse($notice->updated_at)->format('F j, Y, g:i a') }}
            </span>
        </div>
        <div class="content">
            {!! nl2br(e($notice->description)) !!}
        </div>
        @if(isset($notice->attachments) && count($notice->attachments))
            <div style="margin:22px 0 8px 0;">
                <strong>Attachments:</strong>
                <ul>
                @foreach($notice->attachments as $attachment)
                    <li>
                        <a href="{{ $attachment->file_path }}" target="_blank">
                            {{ $attachment->file_name }}
                        </a>
                    </li>
                @endforeach
                </ul>
            </div>
        @endif

        <div class="footer">
            &copy; {{ date('Y') }} Notice App &mdash; Please do not reply to this email.<br>
            <a href="{{ url('/') }}" class="btn">View Notices</a>
        </div>
    </div>
</body>
</html>
