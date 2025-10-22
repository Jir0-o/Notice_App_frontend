@extends('layouts.master')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<style>
  .notice-panel { max-width: 900px; margin: 0 auto; padding: 1.25rem; background: #fff; border-radius: 8px; border:1px solid #e6e6e6; }
  .attachment-item { display:flex; justify-content:space-between; gap:1rem; align-items:center; padding:.5rem; border:1px solid #eee; border-radius:6px; margin-bottom:.5rem; }
</style>

<div class="container py-4">
  <div class="notice-panel">
    <h3 id="noticeTitle">Notice Details</h3>
    <div class="mb-3">
      <strong>Title:</strong> <span id="notice_title_text"></span>
    </div>
    <div class="mb-3">
      <strong>Description:</strong>
      <div id="notice_description" class="mt-2"></div>
    </div>

    <div class="mb-3">
      <h5>Attachments</h5>
      <div id="attachmentsList">
        <!-- items inserted here -->
      </div>
    </div>

    <div class="text-center mt-3">
      <button id="downloadAllBtn" class="btn btn-primary">Download All Attachments</button>
      <a href="{{ url()->previous() }}" class="btn btn-secondary ms-2">Back</a>
    </div>
  </div>
</div>

<!-- libs -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function(){
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
  const token = localStorage.getItem('api_token') || null;

  // parse id from URL (assumes route like /notices/{id})
  const pathParts = window.location.pathname.split('/');
  const id = pathParts[pathParts.length - 1] || null;

  if(!id){
    alert('No notice id found in URL');
    return;
  }

  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  function safeHtml(html){ return DOMPurify.sanitize(html || ''); }

  async function loadNotice(){
    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/my-notices/${id}`,
        method: 'GET',
        dataType: 'json'
      });
      const payload = res?.data || {};
      $('#notice_title_text').text(payload.title || 'N/A');
      $('#notice_description').html(safeHtml(payload.description || ''));

      // attachments listing
      const attachments = payload.attachments || [];
      const list = $('#attachmentsList').empty();
      if(attachments.length === 0){
        list.append('<div class="text-muted">No attachments available</div>');
      } else {
        attachments.forEach(att => {
          const row = $(`
            <div class="attachment-item" data-id="${att.id}">
              <div class="text-truncate">${escapeHtml(att.file_name || att.file_name)}</div>
              <div>
                <button class="btn btn-sm btn-outline-success download-attachment">Download</button>
              </div>
            </div>
          `);
          list.append(row);
        });
      }
    } catch(err) {
      console.error('loadNotice', err);
      if(err && err.status === 401){
        toastr.error('Unauthorized. Token may be expired. Please login again.');
      } else {
        toastr.error('Failed to load notice');
      }
    }
  }

  // per-attachment download -> returns blob
  $(document).on('click', '.download-attachment', async function(){
    const attRow = $(this).closest('.attachment-item');
    const attId = attRow.data('id');
    if(!attId) return;
    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/notices/${id}/attachments/${attId}`,
        method: 'GET',
        xhrFields: { responseType: 'blob' },
        cache: false
      });

      // browser will receive blob in response; but jQuery hides headers;
      // to get filename, try to fetch via fetch API instead for reliable headers
      // Use fetch with Authorization header for filename parsing:
      const fetchRes = await fetch(`${API_BASE}/api/notices/${id}/attachments/${attId}`, {
        headers: token ? { 'Authorization': 'Bearer ' + token } : {},
      });
      if(!fetchRes.ok) throw new Error('Download failed');
      const contentDisp = fetchRes.headers.get('content-disposition') || '';
      const match = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(contentDisp);
      const filename = match ? decodeURIComponent(match[1].replace(/"/g, '')) : `attachment-${attId}`;

      const blob = await fetchRes.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch(err){
      console.error('attachment download', err);
      toastr.error('Download failed');
    }
  });

  // download all attachments (API returns a URL to zip)
  $('#downloadAllBtn').on('click', async function(){
    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/notices/${id}/attachments/`,
        method: 'GET',
        dataType: 'json'
      });
      const downloadUrl = res?.data?.url || res?.url || null;
      if(!downloadUrl){
        toastr.error('No downloadable ZIP URL returned from server');
        return;
      }
      // download by creating an anchor
      const a = document.createElement('a');
      a.href = downloadUrl;
      // browser will do name from server; you can set a filename if needed
      document.body.appendChild(a);
      a.click();
      a.remove();
    } catch(err) {
      console.error('downloadAll', err);
      toastr.error('Failed to prepare download for attachments');
    }
  });

  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  loadNotice();
});
</script>
@endsection
