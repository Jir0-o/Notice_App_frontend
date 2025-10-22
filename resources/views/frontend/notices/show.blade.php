@extends('layouts.master')

@section('content')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
<!-- DOMPurify for safe innerHTML -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>

<style>
/* small styling to mimic your React layout */
.notice-card {
  max-width: 980px;
  margin: 0 auto;
  border-radius: 14px;
  background: linear-gradient(135deg,#ffffff 0%, #f5f7fb 100%);
  padding: 22px;
  border: 1px solid #e6e9ef;
  box-shadow: 0 6px 18px rgba(18,38,63,0.06);
}
.notice-attachments { display:flex; gap:12px; flex-wrap:wrap; }
.attachment-card {
  width:160px;
  border-radius:8px;
  border:1px solid #eef2f6;
  padding:8px;
  background:#fff;
  text-align:center;
}
.attachment-thumb { width:100%; height:94px; object-fit:cover; border-radius:6px; margin-bottom:6px; }
.badge-read { background:#16a34a; color:#fff; padding:6px 10px; border-radius:999px; font-weight:600; font-size:0.85rem; }
.badge-unread { background:#ef4444; color:#fff; padding:6px 10px; border-radius:999px; font-weight:600; font-size:0.85rem; }
.table-scroll { max-height: 46vh; overflow:auto; }
.small-muted { color:#6b7280; }
.detail-pill { padding:6px 10px; background:#f3f4f6; border-radius:8px; display:inline-block; font-size:0.9rem; color:#111827; }
@media (max-width:767px){ .attachment-card { width:120px } }
</style>

<div class="container py-4">
  <div id="noticeWrap" class="notice-card">
    <!-- content injected by JS -->
    <div class="text-center py-5 text-muted">Loading notice...</div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
$(function(){
  const id = @json($id);
  const API = '{{ url("/") }}/api';
  const token = localStorage.getItem('api_token') || null;

  // set headers for all ajax
  $.ajaxSetup({
    beforeSend: function(xhr) {
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    },
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '' }
  });

  if(!id){
    $('#noticeWrap').html('<div class="text-danger">Notice id missing.</div>');
    return;
  }

  function niceDate(dStr){
    if(!dStr) return '-';
    try {
      const d = new Date(dStr);
      return d.toLocaleString();
    } catch(e){
      return dStr;
    }
  }

  function isImage(name){
    const ext = (name || '').toLowerCase().split('.').pop();
    return ['jpg','jpeg','png','gif','webp','bmp'].includes(ext);
  }

  function renderNotice(it){
    const updated = it.updated_at ? niceDate(it.updated_at) : '-';
    const statusLabel = (it.status === 'published') ? 'Published at' : 'Last updated';
    const attachments = it.attachments || [];
    const propagations = it.propagations || [];

    // attachments HTML
    let attachmentsHtml = '';
    if(attachments.length){
      attachmentsHtml = '<div class="notice-attachments">';
      attachments.forEach(a => {
        const fname = a.file_name || a.name || 'file';
        const url = a.file_url || a.url || a.file_path || (a.file_name ? ('/storage/' + a.file_name) : '#');
        if (isImage(fname)) {
          attachmentsHtml += `<div class="attachment-card">
            <a href="${url}" target="_blank" rel="noopener">
              <img class="attachment-thumb" src="${url}" alt="${fname}" onerror="this.src='https://via.placeholder.com/160x94?text=No+Image'"/>
            </a>
            <div style="font-size:.85rem; margin-top:6px;"><a href="${url}" target="_blank" rel="noopener">${fname}</a></div>
          </div>`;
        } else {
          // non-image
          attachmentsHtml += `<div class="attachment-card">
            <div class="file-icon" style="font-size:34px;line-height:94px">📄</div>
            <div style="font-size:.85rem; margin-top:6px;"><a href="${url}" target="_blank" rel="noopener">${fname}</a></div>
          </div>`;
        }
      });
      attachmentsHtml += '</div>';
    } else {
      attachmentsHtml = '<div class="small-muted">No attachments added.</div>';
    }

    // propagations rows
    let rowsHtml = '';
    if(propagations.length){
      propagations.forEach((p, idx) => {
        const name = (p.user && p.user.name) || p.name || 'N/A';
        const email = (p.user && p.user.email) || p.user_email || 'N/A';
        const isRead = p.is_read === 1 || p.is_read === true;
        const readHtml = p.user_id === null ? 'No status' : (isRead ? `<span class="badge-read">Read</span>` : `<span class="badge-unread">Unread</span>`);
        const typeHtml = p.user_id === null ? `<span class="detail-pill">External</span>` : `<span class="detail-pill">Internal</span>`;

        rowsHtml += `<tr>
          <td class="p-3">${escapeHtml(name)}</td>
          <td class="p-3">${escapeHtml(email)}</td>
          <td class="p-3">${readHtml}</td>
          <td class="p-3">${typeHtml}</td>
        </tr>`;
      });
    } else {
      rowsHtml = `<tr><td colspan="4" class="p-3 text-muted">No user selected in this notice.</td></tr>`;
    }

    // sanitize description using DOMPurify (available via CDN)
    const rawDesc = it.description || '';
    const cleanDesc = (typeof DOMPurify !== 'undefined') ? DOMPurify.sanitize(rawDesc) : rawDesc;

    const html = `
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h2 style="margin:0;font-size:1.6rem;font-weight:700;color:#0f172a">${escapeHtml(it.title)}</h2>
          <div class="small-muted mt-1">${statusLabel}: <strong style="margin-left:6px">${escapeHtml(updated)}</strong></div>
        </div>
        <div>
          ${it.status !== 'published' ? `<button id="publishBtn" class="btn btn-success">Publish Notice</button>` : `<span class="badge-read">Published</span>`}
        </div>
      </div>

      <hr style="margin:16px 0"/>

      <div style="display:flex;gap:18px;flex-wrap:wrap">
        <div style="flex:1 1 60%; min-width:280px; background:#fff; padding:14px; border-radius:10px; border:1px solid #f0f2f5">
          <h5 style="margin:0 0 8px 0;font-weight:600">📝 Description</h5>
          <div style="color:#0f172a; line-height:1.6; margin-top:8px">${cleanDesc}</div>
        </div>

        <div style="flex:1 1 35%; min-width:220px">
          <div style="background:#fff; padding:14px; border-radius:10px; border:1px solid #f0f2f5">
            <h5 style="margin:0 0 8px 0;font-weight:600">📎 Attachments</h5>
            <div style="margin-top:8px">${attachmentsHtml}</div>
          </div>

          <div style="background:#fff; padding:12px; border-radius:10px; border:1px solid #f0f2f5; margin-top:12px">
            <div style="font-weight:600">👥 Total Recipients</div>
            <div style="font-size:1.3rem; font-weight:700; margin-top:6px">${propagations.length || 0}</div>
          </div>
        </div>
      </div>

      <hr style="margin:18px 0"/>

      <div>
        <h5 style="font-weight:700;margin-bottom:10px">📖 Notice Read List</h5>
        <div class="table-scroll" style="border-radius:10px; border:1px solid #eef2f6; background:#fff; padding:0; overflow:auto;">
          <table class="table mb-0" style="min-width:720px; border-collapse:collapse;">
            <thead style="background:#f3f4f6;">
              <tr>
                <th class="p-3">Name</th>
                <th class="p-3">Email</th>
                <th class="p-3">Read Status</th>
                <th class="p-3">User Status</th>
              </tr>
            </thead>
            <tbody>
              ${rowsHtml}
            </tbody>
          </table>
        </div>
      </div>
    `;

    $('#noticeWrap').html(html);
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // load notice
  function load(){
    $('#noticeWrap').html('<div class="text-center py-5 text-muted">Loading notice...</div>');
    $.get(`${API}/notices/${id}`).done(function(res){
      const it = res.data || res;
      renderNotice(it);
    }).fail(function(xhr){
      console.error(xhr);
      $('#noticeWrap').html('<div class="text-danger">Failed to load notice. Please check console for details.</div>');
    });
  }

  // publish handler (delegated because render replaces DOM)
  $(document).on('click', '#publishBtn', function(){
    if(!confirm('Publish this notice?')) return;
    const $btn = $(this);
    $btn.prop('disabled', true).text('Publishing...');
    $.ajax({
      url: `${API}/notices/${id}/publish`,
      method: 'POST'
    }).done(function(){
      // redirect or reload to show updated state
      window.location.href = '/notices';
    }).fail(function(xhr){
      alert(xhr?.responseJSON?.message || 'Failed to publish');
      $btn.prop('disabled', false).text('Publish Notice');
    });
  });

  load();
});
</script>
@endsection
