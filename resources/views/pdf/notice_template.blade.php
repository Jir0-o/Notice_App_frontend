<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <title>Notice</title>
  <style>
    @page { margin: 28mm 18mm 28mm 18mm; }
    @font-face{
		font-family: 'notosansbengali';
		src: url('{{ storage_path('fonts/BL Fiona Bangla Unicode.ttf') }}') format('truetype');
		font-weight: normal;
		font-style: normal;
	}
	body { font-family: 'notosansbengali', sans-serif; }

    .header { text-align: center; margin-bottom: 10px; }
    .header h4, .header p { margin: 0; }
    .header a { color: #000; text-decoration: underline; }

    .meta { text-align: left; margin-top: 10px; margin-bottom: 12px; }
    .meta .right { float: right; }

    .subject { text-align: center; font-weight: bold; margin: 16px 0; }
    .content { text-align: justify; }

    .signature { margin-top: 40px; text-align: right; }
    .signature .name { font-weight: bold; }
    .signature .body { margin-top: 4px; white-space: pre-wrap; }

    .section-title { margin-top: 26px; font-weight: bold; }
    ul { margin: 6px 0 0 18px; padding: 0; }
    li { margin-bottom: 3px; }
    .clearfix::after { content: ""; display: table; clear: both; }
  </style>
</head>
<body>
  <!-- Fixed header block -->
  <div class="header">
    <h4>{{ $header_lines[0] }}</h4>
    <p>{{ $header_lines[1] }}</p>
    <p>{{ $header_lines[2] }}</p>
    <p>{{ $header_lines[3] }}</p>
    <p>{{ $header_lines[4] }}</p>
    <p><a href="{{ $header_lines[5] }}">{{ $header_lines[5] }}</a></p>
  </div>

  <!-- Meta: memo no & date -->
  <div class="meta clearfix">
    <div>নং- {{ $memorial_no }}</div>
    <div class="right">তারিখ: {{ $date_bn }}</div>
  </div>

  <!-- Subject -->
  <div class="subject">বিষয়: {{ $subject }}</div>

  <!-- Body (Bangla) -->
  <div class="content">
    {!! nl2br(e($body)) !!}
  </div>

  <!-- Signature block -->
  <div class="signature">
    <div class="name">({{ $sign_name }})</div>
    @if($sign_designation)
      <div>{{ $sign_designation }}</div>
    @endif
    @if($signature_body)
      <div class="body">{!! nl2br(e($signature_body)) !!}</div>
    @endif
  </div>

  <!-- Distribution -->
  @if($distributions->count())
    <div class="section-title">বিতরণ (জ্যেষ্ঠতার ক্রমানুসারে নয়):</div>
    <ul>
      @foreach($distributions as $d)
        <li>{{ $d->name }}{{ $d->designation ? ' - '.$d->designation : '' }}</li>
      @endforeach
    </ul>
  @endif

  <!-- Regards -->
  @if($regards->count())
    <div class="section-title" style="margin-top:18px;">সদয় অবগতি ও কার্যার্থে:</div>
    <ul>
      @foreach($regards as $r)
        <li>
          {{ $r->name }}{{ $r->designation ? ' - '.$r->designation : '' }}
          @if($r->note) ({{ $r->note }}) @endif
        </li>
      @endforeach
    </ul>
  @endif
</body>
</html>