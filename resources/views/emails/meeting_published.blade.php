<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <title>New Meeting Published</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, sans-serif;">

  {{-- Preheader --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    New meeting: {{ $meeting->title ?? 'Meeting' }} on {{ \Carbon\Carbon::parse(method_exists($meeting, 'getRawOriginal') ? ($meeting->getRawOriginal('date') ?: $meeting->date) : $meeting->date)->format('F j, Y') }}
  </div>

  @php

    $rawValue = function ($model, string $key) {
        if (is_object($model) && method_exists($model, 'getRawOriginal')) {
            $raw = $model->getRawOriginal($key);

            if ($raw !== null && $raw !== '') {
                return $raw;
            }
        }

        return data_get($model, $key);
    };

    /*
    |--------------------------------------------------------------------------
    | Date
    |--------------------------------------------------------------------------
    */
    $rawDate = $rawValue($meeting, 'date');
    $dateText = $rawDate
        ? \Carbon\Carbon::parse($rawDate)->format('F j, Y')
        : '—';

    $formatTimeOnly = function ($value) {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = trim((string) $value);

        // Sometimes raw TIME can be "14:23:00" or "14:23"
        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat('!' . $format, $value)->format('g:i a');
            } catch (\Throwable $e) {
                // try next format
            }
        }

        // Last fallback only
        try {
            return \Carbon\Carbon::parse($value)->format('g:i a');
        } catch (\Throwable $e) {
            return $value;
        }
    };

    $startText = $formatTimeOnly($rawValue($meeting, 'start_time'));
    $endText   = $formatTimeOnly($rawValue($meeting, 'end_time'));

    /*
    |--------------------------------------------------------------------------
    | Published time
    |--------------------------------------------------------------------------
    | updated_at is full datetime. If your DB already stores local Bangladesh time,
    | do not timezone convert again.
    */
    $rawUpdatedAt = $rawValue($meeting, 'updated_at');

    $pubText = $rawUpdatedAt
        ? \Carbon\Carbon::parse($rawUpdatedAt)->format('F j, Y, g:i a')
        : '—';

    /*
    |--------------------------------------------------------------------------
    | Location
    |--------------------------------------------------------------------------
    */
    $location = $meeting->location
        ?? $meeting->room_name
        ?? $meeting->meeting?->title
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | Agenda
    |--------------------------------------------------------------------------
    */
    $agenda = $meeting->agenda ?? $meeting->description ?? null;

    if ($agenda) {
        $html = $agenda;
        $html = preg_replace('/<\/(p|div|h1|h2|h3|h4|li|blockquote)>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $agenda = trim(strip_tags($html));
        $agenda = preg_replace("/\r\n|\r/", "\n", $agenda);
        $agenda = preg_replace("/\n{3,}/", "\n\n", $agenda);
    }

    $meetingUrl = $meetingUrl ?? null;
  @endphp

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
         style="background:#f5f6fa;padding:26px 10px;">
    <tr>
      <td align="center">

        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.06);">

          {{-- Header --}}
          <tr>
            <td style="background:#213b52;padding:18px 22px;">
              <div style="color:#ffffff;font-size:16px;font-weight:800;letter-spacing:.2px;">
                Meeting Board
              </div>
              <div style="color:#cbd5e1;font-size:12px;margin-top:4px;">
                A new meeting has been scheduled
              </div>
            </td>
          </tr>

          {{-- Body --}}
          <tr>
            <td style="padding:22px;color:#111827;">
              <div style="font-size:14px;line-height:1.6;">
                <p style="margin:0 0 12px 0;">
                  Dear {{ $recipientName ?? 'User' }},
                </p>

                <div style="font-size:20px;font-weight:900;color:#213b52;margin:0 0 8px 0;">
                  {{ $meeting->title ?? 'Meeting' }}
                </div>

                <div style="font-size:12px;color:#6b7280;margin:0 0 14px 0;">
                  <span style="display:inline-block;background:#eef2ff;color:#1e3a8a;border:1px solid #c7d2fe;padding:3px 10px;border-radius:999px;font-weight:700;">
                    {{ $dateText }}
                  </span>
                  <span style="margin:0 8px;">•</span>
                  <span>
                    <strong>Time:</strong> {{ $startText }} – {{ $endText }}
                  </span>
                </div>

                {{-- Schedule block --}}
                <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:14px;">
                  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;">
                    <tr>
                      <td style="padding:4px 0;color:#6b7280;width:120px;"><strong>Date</strong></td>
                      <td style="padding:4px 0;color:#111827;">{{ $dateText }}</td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;color:#6b7280;"><strong>Start</strong></td>
                      <td style="padding:4px 0;color:#111827;">{{ $startText }}</td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;color:#6b7280;"><strong>End</strong></td>
                      <td style="padding:4px 0;color:#111827;">{{ $endText }}</td>
                    </tr>

                    @if(!empty($location))
                      <tr>
                        <td style="padding:4px 0;color:#6b7280;"><strong>Location</strong></td>
                        <td style="padding:4px 0;color:#111827;">{{ $location }}</td>
                      </tr>
                    @endif

                    <tr>
                      <td style="padding:4px 0;color:#6b7280;"><strong>Published</strong></td>
                      <td style="padding:4px 0;color:#111827;">{{ $pubText }}</td>
                    </tr>
                  </table>
                </div>

                {{-- Agenda / Details --}}
                @if(!empty($agenda))
                  <div style="margin:0 0 14px 0;">
                    <div style="font-weight:800;margin:0 0 8px 0;color:#111827;">Agenda / Notes</div>
                    <div style="white-space:pre-line;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;color:#111827;">
                      {{ $agenda }}
                    </div>
                  </div>
                @endif

                {{-- View button --}}
                @if(!empty($meetingUrl))
                  <div style="margin-top:12px;">
                    <a href="{{ $meetingUrl }}" target="_blank"
                       style="display:inline-block;background:#2563eb;color:#ffffff!important;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:800;font-size:14px;">
                      View Meeting
                    </a>
                  </div>
                @endif

              </div>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td style="padding:14px 22px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
              <div style="color:#6b7280;font-size:12px;line-height:1.5;">
                &copy; {{ date('Y') }} Notice App — Please do not reply to this email.
              </div>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>