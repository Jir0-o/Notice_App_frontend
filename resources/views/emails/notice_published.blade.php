<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <title>New Notice Published</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, sans-serif;">

  {{-- Preheader --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    New notice: {{ $notice->title ?? 'Notice' }}
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

    $rawUpdatedAt = $rawValue($notice, 'updated_at');

    try {
        $publishedText = $rawUpdatedAt
            ? \Carbon\Carbon::parse($rawUpdatedAt)->format('F j, Y, g:i a')
            : '—';
    } catch (\Throwable $e) {
        $publishedText = $rawUpdatedAt ?: '—';
    }

    /*
    |--------------------------------------------------------------------------
    | Notice description: HTML / Quill to clean plain text
    |--------------------------------------------------------------------------
    */
    $html = $notice->description ?? '';

    $html = preg_replace('/<\/(p|div|h1|h2|h3|h4|li|blockquote)>/i', "\n", $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

    $plain = trim(strip_tags($html));
    $plain = preg_replace("/\r\n|\r/", "\n", $plain);
    $plain = preg_replace("/\n{3,}/", "\n\n", $plain);

    /*
    |--------------------------------------------------------------------------
    | Attachments safe count
    |--------------------------------------------------------------------------
    */
    $attachments = $notice->attachments ?? collect();
  @endphp

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
         style="background:#f5f6fa;padding:26px 10px;">
    <tr>
      <td align="center">

        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.06);">

          {{-- Header --}}
          <tr>
            <td style="background:#213b52;padding:18px 22px;">
              <div style="color:#ffffff;font-size:16px;font-weight:700;letter-spacing:.2px;">
                Notice Board
              </div>
              <div style="color:#cbd5e1;font-size:12px;margin-top:4px;">
                New notice published
              </div>
            </td>
          </tr>

          {{-- Body --}}
          <tr>
            <td style="padding:22px 22px 10px 22px;color:#111827;">
              <div style="font-size:14px;line-height:1.6;">
                <p style="margin:0 0 12px 0;">
                  Dear {{ $recipientName ?? 'User' }},
                </p>

                <div style="font-size:20px;font-weight:800;color:#213b52;margin:0 0 10px 0;">
                  {{ $notice->title ?? 'Notice' }}
                </div>

                <div style="font-size:12px;color:#6b7280;margin:0 0 14px 0;">
                  @if(isset($notice->priority_level) && $notice->priority_level)
                    <span style="display:inline-block;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:3px 10px;border-radius:999px;font-weight:700;">
                      {{ ucfirst($notice->priority_level) }} Priority
                    </span>
                    <span style="margin:0 8px;">•</span>
                  @endif

                  <span>
                    <strong>Published:</strong> {{ $publishedText }}
                  </span>
                </div>

                {{-- Description as clean text --}}
                <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
                  <div style="white-space:pre-line;font-size:14px;color:#111827;">
                    {{ $plain ?: 'No description provided.' }}
                  </div>
                </div>

                {{-- Attachments --}}
                @if($attachments && count($attachments))
                  <div style="margin-top:18px;">
                    <div style="font-weight:800;margin-bottom:8px;color:#111827;">Attachments</div>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      @foreach($attachments as $attachment)
                        <tr>
                          <td style="padding:6px 0;">
                            <a href="{{ $attachment->file_path ?? '#' }}" target="_blank"
                               style="display:inline-block;color:#2563eb;text-decoration:none;font-size:13px;">
                              📎 {{ $attachment->file_name ?? 'Attachment' }}
                            </a>
                          </td>
                        </tr>
                      @endforeach
                    </table>
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