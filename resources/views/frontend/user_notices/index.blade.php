@extends('layouts.master')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<style>
  .side-box { max-height: 32vh; overflow:auto; padding: .5rem; border:1px solid #e3e3e3; border-radius:6px; background:#fff; }
  .muted { color: #6c757d; }
  .pagination-btn { padding: .4rem .6rem; border-radius: 6px; border:1px solid #ddd; cursor:pointer; user-select:none; }
  .disabled { opacity: .5; cursor: not-allowed; }
  .modal-panel { max-width: 900px; width: 95%; }
  /* professional modal styles */
  .notice-meta { font-size: 0.9rem; color: #6b7280; }
  .attachment-row { display:flex; justify-content:space-between; align-items:center; gap:0.75rem; padding:0.5rem 0; border-bottom:1px solid #f1f1f1; }
  .attachment-row .name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 65%; }
  .btn-attachment { min-width: 38px; }
  .badge-type { font-size: 11px; padding:3px 6px; border-radius: 6px; }
  .modal-header .title { font-weight:700; font-size:1.05rem; }
</style>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">My Notices</h5>
    <div>
      <button id="refreshBtn" class="btn btn-sm btn-outline-primary">Refresh</button>
    </div>
  </div>

  <!-- Controls -->
  <div class="row mb-3 gx-2 gy-2">
    <div class="col-auto">
      <label class="form-label mb-1">Items per page</label>
      <select id="perPage" class="form-select form-select-sm">
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="20">20</option>
      </select>
    </div>

    <div class="col-auto">
      <label class="form-label mb-1">Filter by read status</label>
      <select id="filterStatus" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="read">Read</option>
        <option value="unread">Unread</option>
      </select>
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-striped" id="noticesTable">
      <thead>
        <tr>
          <th>Index</th>
          <th>Title</th>
          <th>Description</th>
          <th>Status</th>
          <th>View</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- rows inserted by JS -->
      </tbody>
    </table>
  </div>

  <!-- Footer: summary + pagination -->
  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <div class="muted" id="summaryText">Showing 0 to 0 of 0 results</div>
    <div class="d-flex gap-1 align-items-center flex-wrap" id="paginationContainer"></div>
  </div>
</div>

<!-- Notice Details Modal (clean & responsive) -->
<div id="noticeModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="noticeModalTitleLabel">
  <div class="modal-dialog modal-dialog-centered modal-panel">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header border-0 pb-0">
        <div class="me-3">
          <h5 id="noticeModalTitleLabel" class="modal-title mb-1" style="font-weight:700;" aria-live="polite">Notice details</h5>
          <div id="noticeMeta" class="text-muted small">—</div>
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <div class="row gy-3">

          <!-- Left: Title + Description -->
          <div class="col-12 col-md-7">
            <div class="mb-2">
              <h5 id="modal_title" class="mb-1" style="font-size:1.05rem; font-weight:600;">—</h5>
              {{-- optional small subtitle: <div id="modal_submeta" class="text-muted small"></div> --}}
            </div>

            <section aria-labelledby="descLabel">
              <h6 id="descLabel" class="mb-2" style="font-weight:600;">Description</h6>
              <div id="modal_description"
                   class="small text-muted"
                   style="max-height:360px; overflow:auto; padding:0.6rem; border-radius:6px; background:#fbfbfb; border:1px solid #f0f0f0;">
                <!-- description HTML injected by JS (sanitized on insertion) -->
              </div>
            </section>
          </div>

          <!-- Right: Attachments -->
          <aside class="col-12 col-md-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div><strong>Attachments</strong> <small id="modal_attachments_count_label" class="text-muted">(0)</small></div>
              <div>
                <button id="btn-download-all" type="button" class="btn btn-sm btn-primary" aria-label="Download all attachments">
                  Download all
                </button>
              </div>
            </div>

            <div id="modal_attachments" class="side-box" role="list" aria-live="polite" style="background:#fff;">
              <div class="text-muted small">No attachments</div>
            </div>

            <div class="mt-3 small text-muted">
              Tip: click <strong>Open</strong> to preview (if available) or <strong>Download</strong> to save.
            </div>
          </aside>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- Optional local styles to tweak the modal look (put in page head or a CSS file) -->
<style>
  /* keep these small and page-local */
  .modal-panel { max-width: 900px; width: 95%; }
  .side-box { max-height: 32vh; overflow:auto; padding: .5rem; border:1px solid #e9ecef; border-radius:6px; background:#ffffff; }
  .attachment-row { display:flex; justify-content:space-between; align-items:center; gap:.75rem; padding:.45rem 0; border-bottom:1px solid #f1f1f1; }
  .attachment-row .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:62%; }
  .btn-attachment { min-width:42px; }
  @media (max-width:767px){
    .modal-body { padding: 1rem; }
    .modal-header .modal-title { font-size: 1rem; }
  }
</style>


<!-- libs -->
<!-- libs -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){
  // CONFIG
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
  let token = localStorage.getItem('api_token') || null;

  // state
  let currentPage = 1;
  let perPage = Number($('#perPage').val() || 10);
  let filterStatus = $('#filterStatus').val() || '';
  let lastPage = 1;
  let summary = { from:0, to:0, total:0 };

  // Bootstrap modal instance
  const noticeModalEl = document.getElementById('noticeModal');
  const noticeModal = noticeModalEl ? new bootstrap.Modal(noticeModalEl, { backdrop: 'static' }) : null;

  // AJAX setup
  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){
      token = localStorage.getItem('api_token') || null;
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  // ===== Helpers =====
  function showToast(type, msg){ toastr[type](msg); }
  function safeText(html){ return DOMPurify.sanitize(html || '', {ALLOWED_TAGS: []}); }
  function safeHtml(html){ return DOMPurify.sanitize(html || ''); }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // 1) Nice date formatting
  function fmtDateTime(s){
    if(!s) return '—';
    const d = new Date(s);
    if(Number.isNaN(d.getTime())) return s;
    return d.toLocaleString(undefined, {
      day:'2-digit', month:'short', year:'numeric',
      hour:'2-digit', minute:'2-digit', hour12:true
    });
  }

  // 2) Download any fetch() Response as a file
  async function downloadFetchResponse(res, fallbackName='attachments.zip'){
    if(!res.ok) throw new Error('bad response');
    const disp = res.headers.get('content-disposition') || '';
    let fname = fallbackName;
    const m = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(disp);
    if(m && m[1]) fname = decodeURIComponent(m[1].replace(/"/g,''));
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = fname;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  }

  // ===== Fetch notices =====
  async function fetchNotices(){
    token = localStorage.getItem('api_token') || null;
    if(!token){
      showToast('warning','No API token found. Please login to fetch notices.');
      renderTable([]);
      return;
    }

    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/my-notices`,
        method: 'GET',
        dataType: 'json',
        data: { page: currentPage, per_page: perPage, status: filterStatus }
      });

      const payload = (res && res.data) ? res.data : (res || {});
      const data = payload.data || [];
      summary.from = payload.from || (data.length ? ((currentPage - 1) * perPage + 1) : 0);
      summary.to = payload.to || (data.length ? (summary.from + data.length - 1) : 0);
      summary.total = payload.total || (data.length ? data.length : 0);
      lastPage = payload.last_page || 1;

      renderTable(data);
      renderSummary();
      renderPagination();
    } catch(err) {
      console.error('fetchNotices', err);
      if(err && err.status === 401){
        showToast('error','Unauthorized. Token may be expired. Please login again.');
      } else {
        showToast('error','Failed to load notices');
      }
      renderTable([]);
      renderSummary();
      renderPagination();
    }
  }

  function renderTable(notices){
    const tbody = $('#noticesTable tbody').empty();
    if(!Array.isArray(notices) || notices.length === 0){
      tbody.append(`<tr><td colspan="6" class="text-muted">No notices found</td></tr>`);
      return;
    }

    notices.forEach((n, idx) => {
      const iIndex = (summary.from ? summary.from : ((currentPage - 1) * perPage + 1)) + idx;
      const shortTitle = (n.title || '').length > 50 ? (n.title || '').slice(0, 50) + '...' : (n.title || '');
      const plain = safeText(n.description || '');
      const shortDesc = plain.length > 50 ? plain.slice(0, 50) + '...' : plain;
      const statusText = (n.is_read === 1) ? 'Read' : 'Unread';

      const row = $(`
        <tr data-id="${escapeHtml(String(n.id))}">
          <td>${escapeHtml(String(iIndex))}</td>
          <td title="${escapeHtml(n.title || '')}">${escapeHtml(shortTitle)}</td>
          <td>${escapeHtml(shortDesc)}</td>
          <td>${escapeHtml(statusText)}</td>
          <td><a href="#" class="link-primary view-notice" data-id="${escapeHtml(String(n.id))}">View</a></td>
          <td>${n.is_read !== 1 ? '<button class="btn btn-sm btn-success mark-read">Mark as Read</button>' : ''}</td>
        </tr>
      `);

      tbody.append(row);
    });
  }

  function renderSummary(){
    $('#summaryText').text(`Showing ${summary.from || 0} to ${summary.to || 0} of ${summary.total || 0} results`);
  }

  function renderPagination(){
    const container = $('#paginationContainer').empty();
    const prev = $('<div>').addClass('pagination-btn').text('Prev').toggleClass('disabled', currentPage === 1);
    prev.on('click', function(){ if(currentPage>1){ currentPage--; fetchNotices(); } });
    container.append(prev);

    const maxButtons = 4;
    let start = Math.max(1, currentPage - Math.floor(maxButtons/2));
    let end = start + maxButtons - 1;
    if(end > lastPage){ end = lastPage; start = Math.max(1, end - maxButtons + 1); }

    for(let p = start; p <= end; p++){
      const btn = $('<div>').addClass('pagination-btn').text(p).toggleClass('bg-dark text-white', p === currentPage);
      if(p !== currentPage) btn.on('click', () => { currentPage = p; fetchNotices(); });
      container.append(btn);
    }

    const next = $('<div>').addClass('pagination-btn').text('Next').toggleClass('disabled', currentPage === lastPage);
    next.on('click', function(){ if(currentPage<lastPage){ currentPage++; fetchNotices(); } });
    container.append(next);
  }

  // Mark as read
  $(document).on('click', '.mark-read', async function(){
    const tr = $(this).closest('tr');
    const id = tr.data('id');
    if(!id) return;
    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/notices/${id}/read`,
        method: 'POST',
        dataType: 'json'
      });
      showToast('success', res?.message || 'Marked as read');
      fetchNotices();
    } catch(err) {
      console.error('markRead', err);
      showToast('error', 'Failed to mark as read');
    }
  });

  // View (AJAX modal) -> GET /api/my-notices/{id}
  $(document).on('click', '.view-notice', async function(e){
    e.preventDefault();
    const id = $(this).data('id');
    if(!id) return;
    token = localStorage.getItem('api_token') || null;
    if(!token){
      showToast('warning', 'No API token found. Please login.');
      return;
    }

    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/my-notices/${encodeURIComponent(id)}`,
        method: 'GET',
        dataType: 'json'
      });

      const payload = (res && res.data) ? res.data : (res || {});

      // fill modal
      $('#noticeModalTitle').text(payload.title || 'Notice details');
      $('#modal_title').text(payload.title || '');
      $('#modal_description').html(safeHtml(payload.description || ''));

      const when = payload.updated_at || payload.published_at || payload.created_at;
      $('#noticeMeta').text(
        (payload.status === 'published' ? 'Published' : 'Last updated') + ': ' + fmtDateTime(when)
      );

      // attachments
      const attachments = payload.attachments || [];
      $('#modal_attachments').empty();
      $('#modal_attachments_count_label').text('(' + (attachments.length || 0) + ')');

      if(attachments.length){
        attachments.forEach(att => {
          const attRow = $(`
            <div class="attachment-row" data-att-id="${escapeHtml(String(att.id || ''))}">
              <div class="name">${escapeHtml(att.file_name || att.name || 'attachment')}</div>
              <div class="d-flex gap-2 align-items-center">
                ${att.url ? `<a href="${escapeHtml(att.url)}" target="_blank" class="btn btn-sm btn-outline-primary btn-attachment">Open</a>` : ''}
                <button class="btn btn-sm btn-outline-success btn-attachment download-attachment" data-id="${escapeHtml(String(att.id || ''))}">Download</button>
              </div>
            </div>
          `);
          $('#modal_attachments').append(attRow);
        });
      } else {
        $('#modal_attachments').html('<div class="text-muted">No attachments</div>');
      }

      // store last notice id for "download all"
      $('#noticeModal').data('last-notice-id', payload.id || id);

      if(noticeModal) noticeModal.show();

      // refresh list since the endpoint may mark as read
      fetchNotices();
    } catch(err) {
      console.error('fetch single notice', err);
      if(err && err.status === 403){
        showToast('error', err.responseJSON?.message || 'Access denied to this notice.');
      } else if(err && err.status === 401){
        showToast('error', 'Unauthorized. Token may have expired.');
      } else {
        showToast('error', 'Failed to fetch notice details.');
      }
    }
  });

  // Download single attachment
  $(document).on('click', '.download-attachment', async function(){
    const attId = $(this).data('id');
    if(!attId) return;
    const noticeId = $('#noticeModal').data('last-notice-id');
    try {
      // if API provided a direct url, opening that is fine
      const $row = $(this).closest('.attachment-row');
      const openLink = $row.find('a[target="_blank"]');
      if(openLink.length){
        window.open(openLink.attr('href'), '_blank');
        return;
      }

      // Otherwise fetch binary: /api/notices/{noticeId}/attachments/{attId}
      const t = localStorage.getItem('api_token') || null;
      const endpoint = `${API_BASE}/api/notices/${encodeURIComponent(noticeId)}/attachments/${encodeURIComponent(attId)}`;

      const res = await fetch(endpoint, {
        headers: t ? { 'Authorization': 'Bearer ' + t } : {}
      });

      await downloadFetchResponse(res, `attachment-${attId}`);
    } catch(err){
      console.error('download single attachment', err);
      showToast('error','Attachment download failed');
    }
  });

  // Download all attachments (supports JSON {url} OR direct file)
  $('#btn-download-all').on('click', async function(){
    const lastId = $('#noticeModal').data('last-notice-id');
    if(!lastId){
      showToast('error','No notice selected');
      return;
    }

    const endpoint = `${API_BASE}/api/notices/${encodeURIComponent(lastId)}/attachments`;
    const t = localStorage.getItem('api_token') || null;

    try {
      const res = await fetch(endpoint, {
        headers: t ? { 'Authorization': 'Bearer ' + t, 'X-Requested-With': 'XMLHttpRequest' } : { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if(ct.includes('application/json')){
        const j = await res.json();
        const url = j?.data?.url || j?.url;
        if(!url) throw new Error('no url in json');
        const a = document.createElement('a');
        a.href = url; a.target = '_blank';
        document.body.appendChild(a); a.click(); a.remove();
        return;
      }

      await downloadFetchResponse(res, `notice-${lastId}-attachments.zip`);
    } catch(err){
      console.error('download all attachments', err);
      showToast('error','Failed to download all attachments');
    }
  });

  // Controls
  $('#perPage').on('change', function(){ perPage = Number($(this).val()); currentPage = 1; fetchNotices(); });
  $('#filterStatus').on('change', function(){ filterStatus = $(this).val(); currentPage = 1; fetchNotices(); });
  $('#refreshBtn').on('click', fetchNotices);

  // When opening modal store last id (defensive)
  $(document).on('click', '.view-notice', function(){
    const id = $(this).data('id');
    $('#noticeModal').data('last-notice-id', id);
  });

  // Initial load
  fetchNotices();
});
</script>

@endsection
