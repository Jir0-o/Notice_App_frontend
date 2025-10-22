<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title id="pageTitle">Notice</title>

<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

<style>
  :root{ --page-width:800px; --font-size:15px; --gap:12px; }
  html,body{height:100%;margin:0;background:#f3f3f3;font-family:"Noto Serif Bengali", serif;}
  .page{ width:var(--page-width); margin:30px auto; background:#fff; padding:36px 44px; box-shadow:0 6px 18px rgba(0,0,0,0.08); color:#000; font-size:var(--font-size); line-height:1.8; position:relative; }
  .center{text-align:center;} .bold{font-weight:700;} .underline{text-decoration:underline;} .gov p{margin:2px 0;} .gov a{color:inherit;text-decoration:underline;}
  .meta{display:flex;justify-content:space-between;margin-top:18px;margin-bottom:10px;font-size:0.95rem;}
  .title{font-weight:700;text-decoration:underline;text-align:center;margin:10px 0 18px;font-size:1.05rem;}
  .para{ text-indent:2.4rem; margin-bottom:var(--gap);}
  .signature{ display:flex; justify-content:flex-end; margin-top:18px; gap:18px; }
  .sig-block{ width:240px; text-align:center; }
  .sig-block img{ width:140px; height:auto; display:block; margin:0 auto 6px; object-fit:contain; border-radius:4px; }
  .sig-block p { margin:4px 0; line-height:1.2; font-size:0.95rem; }
  #sigExtra { margin-top:6px; font-size:0.95rem; line-height:1.25; white-space:pre-wrap; }
  .section-title{ font-weight:700; text-decoration:underline; margin-top:18px; margin-bottom:6px; }
  .list{ margin-left:22px; } .list p{ margin:6px 0; }
  .muted{ color:#6b7280; font-size:.95rem; }
  .loading-overlay{ text-align:center; padding:18px 10px; }
  /* download button */
  #btn-download{ padding:.4rem .6rem; border:1px solid #ccc; background:#fff; border-radius:6px; cursor:pointer; font-size:0.9rem; }
  @media (max-width:860px){ .page{width:94%; padding:22px;} .meta{flex-direction:column; gap:6px; align-items:flex-start;} }
    /* push the date block to the right */
  .meta-date{ margin-left:auto; text-align:right; }

  @media print{
    body{background:#fff;}
    .page{box-shadow:none;margin:0;}
    #btn-download{display:none;}
    /* keep meta in a single row for print */
    .meta{ flex-direction:row !important; align-items:flex-start !important; }
    .meta-date{ margin-left:auto !important; text-align:right !important; }
  }
</style>
</head>
<body>
  <div class="page" id="notice">
    <div style="position:absolute; right:12px; top:12px; z-index:10;">
      <button id="btn-download" type="button">Download / Print</button>
    </div>

    <div class="gov center">
      <p class="bold">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</p>
      <p>অর্থ বিভাগ, অর্থ মন্ত্রণালয়</p>
      <p class="bold">স্কিলস ফর ইনডাস্ট্রি কম্পিটিটিভনেস এন্ড ইনোভেশন প্রোগ্রাম (SICIP)</p>
      <p>প্রবাসী কল্যাণ ভবন (১৫-১৬ তলা)</p>
      <p>৭১-৭২, ইস্কাটন গার্ডেন, রমনা, ঢাকা-১০০০</p>
      <p class="underline"><a href="https://sicib.gov.bd" target="_blank" rel="noopener">https://sicib.gov.bd</a></p>
    </div>

    {{-- Loading / error area --}}
    <div id="loading" class="loading-overlay muted">Loading notice…</div>
    <div id="loadError" class="loading-overlay" style="display:none;color:#b02a37;"></div>

    {{-- meta: memorial no & date --}}
    <div class="meta" id="metaBlock" style="display:none;">
      <div><strong>নং:</strong> <span id="memorialNo">-</span></div>
      <div class="meta-date"><strong>তারিখ:</strong> <span id="noticeDate">-</span></div>
    </div>

    {{-- title / subject --}}
    <div class="title" id="noticeTitle" style="display:none;">—</div>

    {{-- body: allow HTML (Quill output) --}}
    <div class="para" id="noticeBody" style="display:none;"></div>

    {{-- signature block --}}
    <div class="signature" role="note" id="signatureBlock" style="display:none;">
      <div class="sig-block" aria-hidden="true">
        <img id="sigImage" src="" alt="signature" style="display:none;">
        <div id="sigFallback" style="height:74px;"></div>

        <p><strong id="sigName">-</strong></p>
        <p id="sigDesignation"></p>
        <p id="sigDepartment"></p>
        <div id="sigExtra"></div>
      </div>
    </div>

    {{-- Distributions --}}
    <div id="distributionsSection" style="display:none;">
      <div class="section-title">বিতরণ (জ্যেষ্ঠতার ক্রমানুসারে নয়):</div>
      <div class="list" id="distributionsList"></div>
    </div>

    {{-- Regards --}}
    <div id="regardsSection" style="display:none;">
      <div class="section-title">সদয় অবগতি ও কার্যার্থে অনুলিপি:</div>
      <div class="list" id="regardsList"></div>
    </div>
  </div>

  {{-- scripts --}}
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
  (function(){
    'use strict';

    const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}/api";
    const APP_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
    let templateId = "{{ request()->route('id') ?? '' }}";
    if (!templateId) {
      const parts = window.location.pathname.replace(/\/+$/,'').split('/');
      templateId = parts[parts.length-1] || '';
    }

    function showError(msg){
      $('#loading').hide();
      $('#loadError').text(msg).show();
      if (window.toastr) toastr.error(msg);
    }

      function formatBanglaDate(iso){
        if(!iso) return '';
        const d = new Date(iso);
        if(!isNaN(d)){
          try{
            return d.toLocaleDateString('bn-BD', { day:'numeric', month:'long', year:'numeric' });
          }catch(e){}
          // fallback: YYYY-MM-DD -> DD/MM/YYYY with Bangla digits
          const bn = s => String(s).replace(/\d/g, c => '০১২৩৪৫৬৭৮৯'[c]);
          const [y,m,day] = String(iso).split('-');
          return bn(`${day}/${m}/${y}`);
        }
        return iso; // as-is if invalid
      }

    function safeHtmlText(s){ return s == null ? '' : String(s); }

    function normalizeSignatureHtml(raw){
      if (!raw) return '';
      const trimmed = String(raw).trim();
      if (/<[a-z][\s\S]*>/i.test(trimmed)) return trimmed.replace(/\s{3,}/g,' ');
      const escaped = trimmed.replace(/</g,'&lt;').replace(/>/g,'&gt;');
      return escaped.replace(/\n{2,}/g,'\n').replace(/\n/g,'<br>');
    }

    function makeAbsUrl(url){
      if (!url) return null;
      url = String(url).trim();
      if (/^https?:\/\//i.test(url) || url.startsWith('data:')) return url;
      return APP_BASE.replace(/\/+$/,'') + '/' + url.replace(/^\/+/,'');
    }

    function render(template){
      $('#pageTitle').text(template.subject || 'Notice');
      $('#memorialNo').text(template.memorial_no || '-');
      $('#noticeDate').text(template.date ? formatBanglaDate(template.date) : '-');
      $('#noticeTitle').text(template.subject || '-');
      $('#noticeBody').html(template.body || '');

      // signature / user info
      const user = template.user || null;
      const rawSigPath = (user && (user.signature_path || user.photo || user.avatar)) || null;
      const sigImageUrl = makeAbsUrl(rawSigPath);

      if (sigImageUrl) {
        $('#sigImage').attr('src', sigImageUrl).show();
        $('#sigFallback').hide();
      } else {
        $('#sigImage').hide();
        $('#sigFallback').show();
      }

      $('#sigName').text(user?.name || template.user_name || '-');
      $('#sigDesignation').text(user?.designation?.name || template.signature_designation || '');
      $('#sigDepartment').text(user?.department?.name || template.user_department || '');

      // Normalize signature text (trim & collapse)
      $('#sigExtra').html(normalizeSignatureHtml(template.signature_body || ''));

      // distributions
      const dList = $('#distributionsList').empty();
      if (Array.isArray(template.distributions) && template.distributions.length){
        template.distributions.forEach(function(d, i){
          const row = $('<p>').html(
            (i+1) + '. <strong>' + (d.name || '-') + '</strong>' + (d.designation ? ' — ' + d.designation : '') +
            (d.department_name ? ' (' + d.department_name + ')' : '')
          );
          dList.append(row);
        });
      } else {
        dList.append($('<p class="muted">কোন বিতরণ নেই।</p>'));
      }

      // regards
      const rList = $('#regardsList').empty();
      if (Array.isArray(template.regards) && template.regards.length){
        template.regards.forEach(function(r, i){
          const p = $('<p>');
          p.html((i+1) + '. <strong>' + (r.name || '-') + '</strong>' + (r.designation ? ' — ' + r.designation : ''));
          if (r.note) p.append('<br><small>Note: ' + safeHtmlText(r.note) + '</small>');
          rList.append(p);
        });
      } else {
        rList.append($('<p class="muted">কোন অনুলিপি নিয়োগ নেই।</p>'));
      }

      $('#loading').hide();
      $('#loadError').hide();
      $('#metaBlock, #noticeTitle, #noticeBody, #signatureBlock, #distributionsSection, #regardsSection').show();
    }

    function fetchTemplate(){
      if (!templateId) {
        showError('Invalid template id in URL.');
        return;
      }

      $('#loading').show();
      $('#loadError').hide();

      const token = localStorage.getItem('api_token') || null;
      const headers = { 'X-Requested-With': 'XMLHttpRequest' };
      if (token) headers['Authorization'] = 'Bearer ' + token;

      $.ajax({
        url: API_BASE + '/notice-templates/' + encodeURIComponent(templateId),
        method: 'GET',
        headers: headers,
        success: function(res){
          if (!res || (typeof res !== 'object')) {
            showError('Invalid response from server.');
            return;
          }
          render(res);
        },
        error: function(xhr){
          let msg = 'Failed to load notice.';
          try {
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            else if (xhr && xhr.responseText) msg = xhr.responseText;
          } catch(e){}
          showError(msg);
        }
      });
    }

    // Download / Print button -> open print dialog (browser will allow Save as PDF)
    $(document).on('click', '#btn-download', function(){
      window.print();
    });

    $(function(){ fetchTemplate(); });
  })();
  </script>
</body>
</html>
