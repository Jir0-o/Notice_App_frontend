@extends('layouts.master')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}" />

<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
 
<style>
  .side-box { max-height: 28vh; overflow:auto; padding:.5rem; border:1px solid #e3e3e3; border-radius:6px; }
  .modal-panel { max-width: 1000px; width: 95%; max-height: 90vh; overflow:auto; }
  .badge-ext { background:#333;color:#fff;padding:2px 6px;border-radius:8px;font-size:12px; }
  .small-muted { color:#6b7280; font-size: .9rem; }

  /* Modal sizing & inner scroll */
  .modal-panel { max-width: 1100px; width: 95%; }
  #noticeModal .modal-dialog { max-width: 1100px; }
  #noticeModal .modal-content { border-radius: 10px; overflow: hidden; }
  #noticeModal .modal-body {
    max-height: 72vh;
    overflow-y: auto;
    padding-right: 1rem;
  }

  /* Quill editor: keep fixed height but allow modal scroll */
  #quillEditor { min-height: 180px; max-height: 320px; overflow:auto; background:#fff; }

  /* selected recipients (split) */
  .selected-box { min-height: 6rem; }
  .recipient-row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #eee; align-items:center;}
  .recipient-meta { color:#6b7280; font-size:.85rem; margin-left:8px; }
  .badge-role { font-size:11px; padding:3px 6px; border-radius:6px; background:#eef2ff; color:#3730a3; margin-left:8px; }

</style>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Generated Notices</h5>
    <div>
      <button id="btn-refresh" class="btn btn-sm btn-outline-primary" type="button">Refresh</button>
      <button id="btn-create" class="btn btn-sm btn-primary ms-2" type="button">Create Notice</button>
      {{-- <button id="btn-add-room" class="btn btn-sm btn-secondary ms-2" type="button">Add Room</button> --}}
    </div>
  </div>

  <div class="card-body">
    <div id="noticesContainer">
      <table class="table table-striped" id="noticesTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Memo No</th>
            <th>Date</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>

      <nav aria-label="paging">
        <ul class="pagination" id="pagination"></ul>
      </nav>
    </div>
  </div>
</div>

{{-- Create / Edit Notice Modal --}}
<div id="noticeModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-panel">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="noticeModalTitle" class="modal-title">Create Notice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="noticeForm" enctype="multipart/form-data">
        <input type="hidden" id="notice_id" name="notice_id" value="">
        <div class="modal-body">
          <div id="updateNoticeWarning" class="text-center mb-3" style="display:none; font-weight:600; color:#b02a37;">
            একই তারিখ ও স্মারকে স্থলাভিষিক্ত হবে।
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Memo No</label>
              <input name="memorial_no" id="memorial_no" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input name="date" id="notice_date" type="date" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label class="form-label">Subject</label>
              <input name="subject" id="subject" class="form-control" required />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Body</label>
            <div id="quillEditor" style="height:200px; background:#fff;"></div>
            <input type="hidden" id="bodyInput" name="body" />
          </div>

          <div class="mb-3">
            <label class="form-label">Signature (short)</label>
            <div id="signatureEditor" style="height:70px; background:#fff;"></div>
            <input type="hidden" id="signatureInput" name="signature_body" />
          </div>

          <div class="row gy-3">
            <div class="col-md-4">
              <label class="form-label">Departments</label>
              <div id="departmentsBox" class="side-box small-muted">Loading...</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Internal users (select -> click target below)</label>
              <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <input type="checkbox" id="selectAllUsers" />
                <label for="selectAllUsers" class="ms-1 small-muted">Select all users</label>
              </div>
              <div>
                <button type="button" id="clearUserSelection" class="btn btn-sm btn-link small-muted">Clear</button>
              </div>
            </div>
              <div id="usersBox" class="side-box small-muted">Select departments to load users</div>
              <div class="mt-2">
                <button type="button" id="btn-add-to-distributions" class="btn btn-sm btn-outline-primary me-2">Add selected → Distributions</button>
                <button type="button" id="btn-add-to-regards" class="btn btn-sm btn-outline-secondary">Add selected → Regards</button>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Selected recipients (internal / external)</label>
              <div class="mb-2">
                <div class="small text-muted">Internal Recipients</div>
                <div id="selectedInternalPreview" class="side-box selected-box small-muted">No internal recipients</div>
              </div>
              <div class="mb-2">
                <div class="small text-muted">External Recipients</div>
                <div id="selectedExternalPreview" class="side-box selected-box small-muted">No external recipients</div>
              </div>
              <div class="mt-2 small text-muted">
                Internal users -> use the left panel. External users -> add below in Distributions/Regards.
              </div>
            </div>
          </div>

          <hr class="my-3" />

          <!-- Distributions external users (name + designation) -->
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div><strong>Distributions (External)</strong></div>
              <div><small class="text-muted">Name & designation</small></div>
            </div>
            <div class="row g-2 align-items-center">
              <div class="col-md-5"><input id="dist_ext_name" class="form-control" placeholder="Name" /></div>
              <div class="col-md-5"><input id="dist_ext_designation" class="form-control" placeholder="Designation" /></div>
              <div class="col-md-2"><button type="button" id="btn-add-dist-ext" class="btn btn-primary w-100">Add</button></div>
            </div>
            <div id="distExternalList" class="mt-2 small-muted"></div>
          </div>

          <hr class="my-3" />

          <!-- Regards external users (name + designation + note) -->
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div><strong>Regards (External)</strong></div>
              <div><small class="text-muted">Name, designation & optional note</small></div>
            </div>
            <div class="row g-2">
              <div class="col-md-4"><input id="reg_ext_name" class="form-control" placeholder="Name" /></div>
              <div class="col-md-4"><input id="reg_ext_designation" class="form-control" placeholder="Designation" /></div>
              <div class="col-md-3"><input id="reg_ext_note" class="form-control" placeholder="Note (optional)" /></div>
              <div class="col-md-1"><button type="button" id="btn-add-reg-ext" class="btn btn-primary w-100">Add</button></div>
            </div>
            <div id="regExternalList" class="mt-2 small-muted"></div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" id="btn-delete-notice" class="btn btn-danger me-auto" style="display:none;">Delete</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" id="btn-save-draft" class="btn btn-outline-secondary">Save Draft</button>
          <button id="btn-publish" type="submit" class="btn btn-primary">Publish</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Add Room modal --}}
<div id="roomModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="roomForm">
        <div class="modal-body">
          <label class="form-label">Room Name</label>
          <input name="title" id="room_title" class="form-control mb-2" required>
          <label class="form-label">Capacity</label>
          <input name="capacity" id="room_capacity" type="number" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add Room</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- libs --}}
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function($){
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}/api";
  const token = localStorage.getItem('api_token') || null;

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '',
      'X-Requested-With': 'XMLHttpRequest',
      ...(token ? { 'Authorization': 'Bearer ' + token } : {})
    }
  });

  // DOM shortcuts
  const $noticesTbody = $('#noticesTable tbody');
  const $pagination = $('#pagination');

  // state
  let departments = [];
  let departmentUsers = {}; // depId => users[]
  let allUsersCache = null;

  // distributions/regards arrays (permanent)
  let distributionsInternalIds = []; // numbers
  let regardsInternalIds = []; // numbers
  let distributionsExternal = []; // { _uid, name, designation }
  let regardsExternal = []; // { _uid, name, designation, note }

  // legacy arrays for compatibility / UI hints
  let finalSelectedUsers = [];
  let externalUsers = [];
  let selectedUserIds = []; 

  let tempSelectedIds = [];

  // editors
  let quill = null;
  let quillSignature = null;

  let isBulkSelecting = false;

  // init editors
  try { if (document.querySelector('#quillEditor')) quill = new Quill('#quillEditor', { theme: 'snow' }); } catch(e){ console.warn('quill body init failed', e); quill = null; }
  try { if (document.querySelector('#signatureEditor')) quillSignature = new Quill('#signatureEditor', { theme: 'snow', modules: { toolbar: [['bold','italic'], [{ 'header': [1,2,false] }]] }}); } catch(e){ console.warn('signature quill init failed', e); quillSignature = null; }

  function t(msg, type='success'){ toastr[type](msg); }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function safeData(res) {
    if (!res) return null;
    if (res.data !== undefined && res.data !== null && typeof res.data !== 'object') return res.data;
    if (res.data && res.data.data !== undefined) return res.data.data;
    if (res.data) return res.data;
    return res;
  }

  // ---------- Departments ----------
  function loadDepartments(){
    $('#departmentsBox').html('Loading...');
    $.get(API_BASE + '/departments-data')
      .done(function(res){
        const data = safeData(res) || [];
        departments = Array.isArray(data) ? data : [];
        renderDepartments();
      })
      .fail(function(err){
        $('#departmentsBox').html('<small class="text-danger">Failed to load</small>');
        console.error('loadDepartments', err);
      });
  }
  function renderDepartments(){
    const $box = $('#departmentsBox').empty();
    if (!departments.length) { $box.html('<small class="text-muted">No departments</small>'); return; }
    departments.forEach(function(d){
      const item = $(
        '<div class="form-check">' +
          '<input class="form-check-input dept-checkbox" type="checkbox" id="dept-' + d.id + '" value="' + d.id + '"/>' +
          '<label class="form-check-label" for="dept-' + d.id + '">' + escapeHtml(d.name || d.title || '') + '</label>' +
        '</div>'
      );
      $box.append(item);
    });
  }

  // ---------- Department users ----------
  function fetchDepartmentUsers(depId){
    return $.get(API_BASE + '/notices/department-users/' + depId)
      .then(function(res){
        const data = safeData(res) || [];
        departmentUsers[depId] = Array.isArray(data) ? data : [];
        return departmentUsers[depId];
      })
      .catch(function(err){
        console.warn('Primary dept-users failed, falling back to /users', err);
        return $.get(API_BASE + '/users')
          .then(function(r){
            const all = safeData(r) || [];
            const arr = Array.isArray(all) ? all : [];
            const filtered = arr.filter(function(u){
              return (u.department_id && String(u.department_id) === String(depId))
                || (u.department && String(u.department.id || u.department.department_id || '') === String(depId))
                || (u.departments && Array.isArray(u.departments) && u.departments.some(function(d){ return String(d.id || d.department_id || '') === String(depId); }));
            });
            departmentUsers[depId] = filtered;
            return filtered;
          })
          .catch(function(){
            departmentUsers[depId] = [];
            return [];
          });
      });
  }

  // fetch all users once (cache)
  function fetchAllUsers(){
    if (allUsersCache) return $.Deferred().resolve(allUsersCache).promise();
    return $.get(API_BASE + '/users')
      .then(function(res){
        const arr = safeData(res) || [];
        allUsersCache = Array.isArray(arr) ? arr : [];
        return allUsersCache;
      })
      .fail(function(err){
        console.warn('fetchAllUsers failed', err);
        allUsersCache = [];
        return allUsersCache;
      });
  }

  // ---------- distributions/regards external renderers ----------
  function uid(){ return Date.now().toString(36) + Math.random().toString(36).slice(2,8); }

  function renderDistExternalList(){
    const $l = $('#distExternalList').empty();
    if (!distributionsExternal.length) return $l.html('<small class="text-muted">No external distributions</small>');
    distributionsExternal.forEach((u) => {
      $l.append(`<div class="d-flex justify-content-between align-items-center py-1 border-bottom">
        <div>${escapeHtml(u.name)} <small class="text-muted">(${escapeHtml(u.designation||'')})</small></div>
        <div><button type="button" class="btn btn-sm btn-link text-danger btn-remove-dist-ext" data-uid="${u._uid}">Remove</button></div>
      </div>`);
    });
  }
  function renderRegExternalList(){
    const $l = $('#regExternalList').empty();
    if (!regardsExternal.length) return $l.html('<small class="text-muted">No external regards</small>');
    regardsExternal.forEach((u) => {
      $l.append(`<div class="d-flex justify-content-between align-items-center py-1 border-bottom">
        <div><strong>${escapeHtml(u.name)}</strong> <div class="small text-muted">${escapeHtml(u.designation||'')} ${u.note ? '— ' + escapeHtml(u.note) : ''}</div></div>
        <div><button type="button" class="btn btn-sm btn-link text-danger btn-remove-reg-ext" data-uid="${u._uid}">Remove</button></div>
      </div>`);
    });
  }

  // ---------- selected recipients preview (split internal / external) ----------
  function renderSelectedInternalPreview(){
    const $box = $('#selectedInternalPreview');
    if (!$box.length) return;
    $box.empty();
    const seen = {};

    function findUserById(id){
      const allDeptUsers = Object.values(departmentUsers).flat();
      let found = allDeptUsers.find(u => String(u.id) === String(id));
      if (!found && Array.isArray(allUsersCache)) found = allUsersCache.find(u => String(u.id) === String(id));
      return found;
    }

    distributionsInternalIds.forEach(id => {
      const found = findUserById(id);
      seen[id] = seen[id] || { id, name: found?.name || ('User #' + id), email: found?.email || '', roles: new Set() };
      seen[id].roles.add('Distribution');
    });
    regardsInternalIds.forEach(id => {
      const found = findUserById(id);
      seen[id] = seen[id] || { id, name: found?.name || ('User #' + id), email: found?.email || '', roles: new Set() };
      seen[id].roles.add('Regards');
    });

    const keys = Object.keys(seen);
    if (!keys.length) { $box.html('<div class="small text-muted">No internal recipients selected</div>'); return; }

    keys.forEach(k => {
      const it = seen[k];
      const roles = Array.from(it.roles).join(', ');
      const $row = $(`<div class="recipient-row" data-int-id="${escapeHtml(String(it.id))}">
        <div>
          <div><strong>${escapeHtml(it.name)}</strong> <small class="text-muted ms-2">${escapeHtml(roles)}</small></div>
          ${it.email ? `<div class="small text-muted">${escapeHtml(it.email)}</div>` : ''}
        </div>
        <div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-internal" data-id="${escapeHtml(String(it.id))}">Remove</button>
        </div>
      </div>`);
      $box.append($row);
    });
  }

  function renderSelectedExternalPreview(){
    const $box = $('#selectedExternalPreview');
    if (!$box.length) return;
    $box.empty();
    const items = [];

    distributionsExternal.forEach(u => items.push({ uid: u._uid, name: u.name, meta: u.designation || '', role: 'Distribution' }));
    regardsExternal.forEach(u => items.push({ uid: u._uid, name: u.name, meta: (u.designation || '') + (u.note ? ' — ' + u.note : ''), role: 'Regards' }));

    if (!items.length) { $box.html('<div class="small text-muted">No external recipients selected</div>'); return; }

    items.forEach(it => {
      const $row = $(`<div class="recipient-row" data-ext-uid="${escapeHtml(it.uid)}" data-ext-role="${escapeHtml(it.role.toLowerCase())}">
        <div>
          <div><strong>${escapeHtml(it.name)}</strong> <small class="text-muted ms-2">${escapeHtml(it.role)}</small></div>
          ${it.meta ? `<div class="small text-muted">${escapeHtml(it.meta)}</div>` : ''}
        </div>
        <div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-external" data-uid="${escapeHtml(it.uid)}">Remove</button>
        </div>
      </div>`);
      $box.append($row);
    });
  }

  function renderSelectedTargetPreview(){
    renderSelectedInternalPreview();
    renderSelectedExternalPreview();
  }

  // ---------- small utilities ----------
  function formatIsoToYMD(iso){
    if(!iso) return '';
    try {
      const d = new Date(iso);
      if (isNaN(d.getTime())) return String(iso).split('T')[0] || String(iso);
      const yyyy = d.getFullYear();
      const mm = String(d.getMonth()+1).padStart(2,'0');
      const dd = String(d.getDate()).padStart(2,'0');
      return `${yyyy}-${mm}-${dd}`;
    } catch(e) { return String(iso).split('T')[0] || String(iso); }
  }

  // ---------- CRUD list / table ----------
  function loadNotices(page=1, per_page=10){
    $noticesTbody.html('<tr><td colspan="5">Loading...</td></tr>');
    $.get(API_BASE + '/notice-templates?page=' + page + '&per_page=' + per_page)
      .done(function(res){
        const payload = (res && res.data) ? res.data : res;
        const data = safeData(res) || payload.data || payload;
        const items = Array.isArray(data) ? data : [];
        renderNotices(items);
        renderPagination(res || {});
      })
      .fail(function(err){
        console.error('loadNotices', err);
        $noticesTbody.html('<tr><td colspan="5" class="text-danger">Failed to load</td></tr>');
      });
  }
  function renderNotices(items){
    $noticesTbody.empty();
    if (!items.length){
      $noticesTbody.html('<tr><td colspan="6"><small class="text-muted">No notices</small></td></tr>');
      return;
    }

    items.forEach(function(n, idx){
      const status = escapeHtml(n.status || (n.data && n.data.status) || '—');
      const viewUrl = "{{ url('/generate/view/notice') }}/" + encodeURIComponent(n.id);

      const tr = $(
        '<tr>' +
          '<td>' + (idx+1) + '</td>' +
          '<td>' + escapeHtml(n.memorial_no || '') + '</td>' +
          '<td>' + escapeHtml(formatIsoToYMD(n.date || '')) + '</td>' +
          '<td>' + escapeHtml(n.subject || '') + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' +
            '<a class="btn btn-sm btn-outline-secondary btn-view me-1" href="' + viewUrl + '" target="_blank">View</a>' +
            '<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-id="' + n.id + '">Edit</button> ' +
            '<button type="button" class="btn btn-sm btn-outline-danger btn-delete ms-1" data-id="' + n.id + '">Delete</button>' +
          '</td>' +
        '</tr>'
      );

      $noticesTbody.append(tr);
    });
  }


  function renderPagination(apiResp){
    const $p = $pagination.empty();
    if (!apiResp || !apiResp.last_page) return;
    const last = apiResp.last_page;
    const current = apiResp.current_page || 1;
    for (let i=1;i<=last;i++){
      const li = $('<li class="page-item ' + (i===current ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>');
      $p.append(li);
    }
  }
  $pagination.on('click', '.page-link', function(e){ e.preventDefault(); const page = $(this).data('page') || 1; loadNotices(page); });

  // ---------- modal helpers (create / edit) ----------
  function resetNoticeFormState(){
    try { $('#noticeForm')[0].reset(); } catch(e){}
    if (quill) quill.setContents([{ insert: '\n' }]);
    if (quillSignature) quillSignature.setContents([{ insert: '\n' }]);
    finalSelectedUsers = [];
    externalUsers = [];
    selectedUserIds = [];
    tempSelectedIds = [];
    departmentUsers = {};
    if ($('#existingAttachments').length) $('#existingAttachments').empty();
    $('#notice_id').val('');
    $('#noticeModalTitle').text('Create Notice');
    $('#btn-delete-notice').hide();

    distributionsInternalIds = [];
    regardsInternalIds = [];
    distributionsExternal = [];
    regardsExternal = [];
    renderDistExternalList();
    renderRegExternalList();
    renderSelectedTargetPreview();
    renderUsersBox();
    renderExternalList();
    $('#btn-save-draft').show();
  }

  function fetchNoticeById(id){
    return $.get(API_BASE + '/notice-templates/' + id + '?include=true')
      .then(function(r){ return safeData(r) || r.data || r; })
      .catch(function(err){
        console.warn('Primary GET failed', err && err.status);
        return $.get(API_BASE + '/notice-templates/' + id)
          .then(function(r2){ return safeData(r2) || r2.data || r2; })
          .fail(function(err2){ err2._primary = err; return $.Deferred().reject(err2).promise(); });
      });
  }

  // map entry -> internal or external object
  function mapToInternalOrExternal(entry, allUsers){
    if (!entry) return { type: 'external', data: { name: '', designation:'', note: '' } };
    if (entry.user_id) return { type: 'internal', id: Number(entry.user_id), data: null };
    let mapped = null;
    if (entry.email && Array.isArray(allUsers) && allUsers.length) {
      mapped = allUsers.find(u => u.email && entry.email && u.email.toLowerCase() === String(entry.email).toLowerCase());
    }
    if (!mapped && entry.name && Array.isArray(allUsers) && allUsers.length) {
      const nameNorm = String(entry.name).trim().toLowerCase();
      mapped = allUsers.find(u => u.name && String(u.name).trim().toLowerCase() === nameNorm);
    }
    if (mapped) return { type: 'internal', id: Number(mapped.id), data: null };
    return { type: 'external', data: { name: entry.name || '', designation: entry.designation || null, note: entry.note || null, email: entry.email || '' } };
  }

  // open modal (populate)
  function openNoticeModal(notice){
    resetNoticeFormState();
    const n = (notice && notice.data) ? notice.data : notice || null;

    if (n) {
      $('#updateNoticeWarning').show();
    } else {
      $('#updateNoticeWarning').hide();
    }

    // fill parent fields
    if (n) {
      $('#notice_id').val(n.id || '');
      $('#memorial_no').val(n.memorial_no || '');
      $('#notice_date').val(formatIsoToYMD(n.date || ''));
      $('#subject').val(n.subject || '');
      const bodyHtml = n.body || '';
      if (quill && quill.root) quill.root.innerHTML = bodyHtml; else $('#bodyInput').val(bodyHtml);
      const signatureHtml = n.signature_body || '';
      if (quillSignature && quillSignature.root) quillSignature.root.innerHTML = signatureHtml; else $('#signatureInput').val(signatureHtml);

      $('#noticeModalTitle').text('Update Notice');
      $('#btn-delete-notice').show();

      // Hide "Save Draft" if notice is already published
      if (n.status && n.status.toLowerCase() === 'published') {
        $('#btn-save-draft').hide();
      } else {
        $('#btn-save-draft').show();
      }
    } else {
      $('#notice_id').val('');
      $('#memorial_no').val('');
      $('#notice_date').val('');
      $('#subject').val('');
      if (quill && quill.root) quill.root.innerHTML = '';
      if (quillSignature && quillSignature.root) quillSignature.root.innerHTML = '';
      $('#noticeModalTitle').text('Create Notice');
      $('#btn-delete-notice').hide();
    }

    // ensure departments loaded and users cache ready
    loadDepartments();
    fetchAllUsers().then(function(allUsers){
      // reset internal/external arrays
      distributionsInternalIds = [];
      regardsInternalIds = [];
      distributionsExternal = [];
      regardsExternal = [];
      finalSelectedUsers = [];
      externalUsers = [];
      selectedUserIds = [];
      tempSelectedIds = [];

      // populate from payloads if editing
      if (Array.isArray(n?.distributions)) {
        n.distributions.forEach(function(rec){
          const mapped = mapToInternalOrExternal(rec, allUsers);
          if (mapped.type === 'internal') {
            if (!distributionsInternalIds.includes(mapped.id)) distributionsInternalIds.push(mapped.id);
            if (!finalSelectedUsers.some(u => String(u.id) === String(mapped.id))) {
              const found = (Object.values(departmentUsers).flat().find(u => String(u.id) === String(mapped.id)) || allUsers.find(u => String(u.id) === String(mapped.id)));
              finalSelectedUsers.push({ id: mapped.id, name: found?.name || rec.name || '', email: found?.email || rec.email || '' });
              selectedUserIds.push(mapped.id);
            }
          } else {
            const ext = { _uid: uid(), name: mapped.data.name, designation: mapped.data.designation || '' };
            distributionsExternal.push(ext);
            externalUsers.push({ _uid: ext._uid, name: mapped.data.name, email: mapped.data.email || '' });
          }
        });
      }

      if (Array.isArray(n?.regards)) {
        n.regards.forEach(function(rec){
          const mapped = mapToInternalOrExternal(rec, allUsers);
          if (mapped.type === 'internal') {
            if (!regardsInternalIds.includes(mapped.id)) regardsInternalIds.push(mapped.id);
            if (!finalSelectedUsers.some(u => String(u.id) === String(mapped.id))) {
              const found = (Object.values(departmentUsers).flat().find(u => String(u.id) === String(mapped.id)) || allUsers.find(u => String(u.id) === String(mapped.id)));
              finalSelectedUsers.push({ id: mapped.id, name: found?.name || rec.name || '', email: found?.email || rec.email || '' });
              selectedUserIds.push(mapped.id);
            }
          } else {
            const ext = { _uid: uid(), name: mapped.data.name, designation: mapped.data.designation || '', note: mapped.data.note || '' };
            regardsExternal.push(ext);
            externalUsers.push({ _uid: ext._uid, name: mapped.data.name, email: mapped.data.email || '' });
          }
        });
      }

      // render everything
      renderUsersBox();
      renderDistExternalList();
      renderRegExternalList();
      renderSelectedTargetPreview();
      renderExternalList(); // legacy list if present

      // show modal
      const noticeModalEl = document.getElementById('noticeModal');
      const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl, { backdrop: 'static' }) : null;
      if (instance) instance.show(); else $('#noticeModal').show();

    }).fail(function(){
      // if all users fail, fallback to showing externals only
      if (Array.isArray(n?.distributions)) {
        n.distributions.forEach(r => { if (!r.user_id && r.name) distributionsExternal.push({ _uid: uid(), name: r.name, designation: r.designation || '' }); });
      }
      if (Array.isArray(n?.regards)) {
        n.regards.forEach(r => { if (!r.user_id && r.name) regardsExternal.push({ _uid: uid(), name: r.name, designation: r.designation || '', note: r.note || '' }); });
      }
      renderUsersBox();
      renderDistExternalList();
      renderRegExternalList();
      renderSelectedTargetPreview();
      const noticeModalEl = document.getElementById('noticeModal');
      const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl, { backdrop: 'static' }) : null;
      if (instance) instance.show(); else $('#noticeModal').show();
    });
  }

  // ---------- event handlers ----------
  // add external distribution
  $('#btn-add-dist-ext').on('click', function(){
    const name = ($('#dist_ext_name').val()||'').trim();
    const designation = ($('#dist_ext_designation').val()||'').trim();
    if (!name) { toastr.error('Name required'); return; }
    const obj = { _uid: uid(), name, designation };
    distributionsExternal.push(obj);
    externalUsers.push({ _uid: obj._uid, name: obj.name, email: '' });
    $('#dist_ext_name').val(''); $('#dist_ext_designation').val('');
    renderDistExternalList(); renderSelectedTargetPreview(); renderExternalList();
  });

  // add external regard
  $('#btn-add-reg-ext').on('click', function(){
    const name = ($('#reg_ext_name').val()||'').trim();
    const designation = ($('#reg_ext_designation').val()||'').trim();
    const note = ($('#reg_ext_note').val()||'').trim();
    if (!name) { toastr.error('Name required'); return; }
    const obj = { _uid: uid(), name, designation, note };
    regardsExternal.push(obj);
    externalUsers.push({ _uid: obj._uid, name: obj.name, email: '' });
    $('#reg_ext_name').val(''); $('#reg_ext_designation').val(''); $('#reg_ext_note').val('');
    renderRegExternalList(); renderSelectedTargetPreview(); renderExternalList();
  });

  // remove ext items (lists)
  $(document).on('click', '.btn-remove-dist-ext', function(){
    const u = $(this).attr('data-uid');
    distributionsExternal = distributionsExternal.filter(x => x._uid !== u);
    externalUsers = externalUsers.filter(e => e._uid !== u);
    renderDistExternalList();
    renderSelectedTargetPreview();
    renderExternalList();
    renderSelectedTargetPreview();
  });
  $(document).on('click', '.btn-remove-reg-ext', function(){
    const u = $(this).attr('data-uid');
    regardsExternal = regardsExternal.filter(x => x._uid !== u);
    externalUsers = externalUsers.filter(e => e._uid !== u);
    renderRegExternalList();
    renderSelectedTargetPreview();
    renderExternalList();
    renderSelectedTargetPreview();
  });

  // remove from external preview (separate remove button in split UI)
  $(document).on('click', '.btn-remove-external', function(){
    const u = $(this).attr('data-uid');
    distributionsExternal = distributionsExternal.filter(x => x._uid !== u);
    regardsExternal = regardsExternal.filter(x => x._uid !== u);
    externalUsers = externalUsers.filter(e => e._uid !== u);
    renderDistExternalList();
    renderRegExternalList();
    renderSelectedTargetPreview();
    renderExternalList();
  });

  // add selected internal users to Distributions or Regards (use tempSelectedIds now)
  $('#btn-add-to-distributions').on('click', function(){
    if (!tempSelectedIds.length) { toastr.info('Select internal users first'); return; }
    tempSelectedIds.forEach(function(idStr){
      const uid = Number(idStr);
      if (!uid) return;
      if (!distributionsInternalIds.includes(uid)) distributionsInternalIds.push(uid);
      if (!selectedUserIds.includes(uid)) selectedUserIds.push(uid);
      if (!finalSelectedUsers.some(u => String(u.id) === String(uid))){
        const found = (Object.values(departmentUsers).flat().find(u => String(u.id) === String(uid)) || (Array.isArray(allUsersCache) ? allUsersCache.find(u=>String(u.id)===String(uid)) : null));
        finalSelectedUsers.push({ id: uid, name: found?.name || ('User #' + uid), email: found?.email || '' });
      }
    });
    // clear temp selection and uncheck
    tempSelectedIds = [];
    $('#usersBox input[type=checkbox]').prop('checked', false);
    $('#selectAllUsers').prop('checked', false);
    renderUsersBox();
    renderSelectedTargetPreview();
    refreshSelectedPreview();
  });

  $('#btn-add-to-regards').on('click', function(){
    if (!tempSelectedIds.length) { toastr.info('Select internal users first'); return; }
    tempSelectedIds.forEach(function(idStr){
      const uid = Number(idStr);
      if (!uid) return;
      if (!regardsInternalIds.includes(uid)) regardsInternalIds.push(uid);
      if (!selectedUserIds.includes(uid)) selectedUserIds.push(uid);
      if (!finalSelectedUsers.some(u => String(u.id) === String(uid))){
        const found = (Object.values(departmentUsers).flat().find(u => String(u.id) === String(uid)) || (Array.isArray(allUsersCache) ? allUsersCache.find(u=>String(u.id)===String(uid)) : null));
        finalSelectedUsers.push({ id: uid, name: found?.name || ('User #' + uid), email: found?.email || '' });
      }
    });
    // clear temp selection and uncheck
    tempSelectedIds = [];
    $('#usersBox input[type=checkbox]').prop('checked', false);
    $('#selectAllUsers').prop('checked', false);
    renderUsersBox();
    renderSelectedTargetPreview();
    refreshSelectedPreview();
  });

  // remove recipient from internal preview (separate remove button)
  $(document).on('click', '.btn-remove-internal', function(){
    const id = Number($(this).attr('data-id'));
    if (!id) return;
    distributionsInternalIds = distributionsInternalIds.filter(x => Number(x) !== id);
    regardsInternalIds = regardsInternalIds.filter(x => Number(x) !== id);
    finalSelectedUsers = finalSelectedUsers.filter(u => String(u.id) !== String(id));
    selectedUserIds = selectedUserIds.filter(x => String(x) !== String(id));
    // also clear any temp selection of that id
    tempSelectedIds = tempSelectedIds.filter(x => String(x) !== String(id));
    renderUsersBox();
    renderSelectedTargetPreview();
    refreshSelectedPreview();
  });

  // when department checkbox changes -> load users
  $(document).on('change', '.dept-checkbox', function(){
    const depId = $(this).val();
    const checked = $(this).is(':checked');
    if (checked) {
      fetchDepartmentUsers(depId).then(()=> renderUsersBox());
    } else {
      delete departmentUsers[depId];
      renderUsersBox();
    }
  });

  // when user-checkbox toggled -> update ONLY tempSelectedIds
  $(document).on('change', '.user-checkbox', function(){
    // read id reliably (use attr or value)
    const raw = $(this).attr('data-id') || $(this).val();
    const id = raw == null ? '' : String(raw);
    if (!id) return;
    const isChecked = $(this).is(':checked');

    if (isChecked) {
      if (!tempSelectedIds.includes(id)) tempSelectedIds.push(id);
    } else {
      tempSelectedIds = tempSelectedIds.filter(x => String(x) !== id);
    }

    // update the lightweight preview/count (legacy)
    refreshSelectedPreview();
  });

  // legacy external add (if present)
  $('#btn-add-ext').off('click').on('click', function(){
    const name = ($('#ext_name').val() || '').toString().trim();
    const email = ($('#ext_email').val() || '').toString().trim();
    if (!name || !email) { t('Name & Email required', 'error'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { t('Invalid email', 'error'); return; }
    if (externalUsers.some(x => x.email.toLowerCase() === email.toLowerCase())) { t('Already added', 'warning'); return; }
    externalUsers.push({ name: name, email: email });
    $('#ext_name').val(''); $('#ext_email').val('');
    renderExternalList(); refreshSelectedPreview();
  });

  // ---------- UI pieces used by legacy flows ----------
  function renderUsersBox(){
    const $box = $('#usersBox').empty();
    const users = Object.values(departmentUsers).flat();
    if (!users.length){ $box.html('<small class="text-muted">No users for selected departments</small>'); return; }
    const map = {};
    users.forEach(u => { if (u && u.id !== undefined) map[String(u.id)] = u; });
    Object.keys(map).forEach(k => {
      const u = map[k];
      // checkbox should reflect TEMP selection (what user is currently selecting to add)
      const isChecked = tempSelectedIds.some(x => String(x) === String(u.id));
      // but also visually mark if already added
      const alreadyAdded = distributionsInternalIds.some(x => String(x)===String(u.id)) || regardsInternalIds.some(x => String(x)===String(u.id));
      const addedBadge = alreadyAdded ? ' <span class="badge bg-info ms-2" style="font-size:10px">added</span>' : '';

      // build element with proper attributes and value
      const $input = $('<input>')
        .addClass('form-check-input user-checkbox')
        .attr({ id: 'user-' + u.id, type: 'checkbox', 'data-id': String(u.id), value: String(u.id) })
        .prop('checked', !!isChecked);

      const $label = $('<label>')
        .addClass('form-check-label')
        .attr('for', 'user-' + u.id)
        .html(escapeHtml(u.name || u.full_name || 'User') + ' <small class="text-muted">(' + escapeHtml(u.email||'') + ')</small>' + addedBadge);

      const $div = $('<div>').addClass('form-check').append($input).append($label);
      $box.append($div);
    });
  }

  // Select all / clear handlers
  $(document).on('change', '#selectAllUsers', function(){
    const checked = $(this).is(':checked');
    // guard so individual handler knows it's a bulk operation
    isBulkSelecting = true;

    // toggle visual checkboxes and trigger change handler (it will respect our attr/val reading)
    $('#usersBox .user-checkbox').each(function(){
      $(this).prop('checked', checked).trigger('change');
    });

    isBulkSelecting = false;
  });

  // clear button: uncheck all and reset selectAll checkbox
  $(document).on('click', '#clearUserSelection', function(e){
    e.preventDefault();
    isBulkSelecting = true;
    $('#usersBox .user-checkbox').each(function(){
      $(this).prop('checked', false).trigger('change');
    });
    $('#selectAllUsers').prop('checked', false);
    isBulkSelecting = false;
  });

  function refreshSelectedPreview(){
    // legacy preview (finalSelectedUsers + externalUsers) - only used for count
    const merged = finalSelectedUsers.concat(externalUsers);
    if ($('#selectedCount').length) $('#selectedCount').text(merged.length);
    // not rendering legacy central preview since we have split boxes
  }
  function renderExternalList(){
    const $list = $('#externalList');
    if (!$list.length) return; // element not present — safe no-op
    $list.empty();
    if (!externalUsers.length){ $list.html('<small class="text-muted">No external recipients</small>'); return; }
    externalUsers.forEach(function(u){
      const el = '<div>' + escapeHtml(u.name) + (u.email ? ' &lt;' + escapeHtml(u.email) + '&gt; ' : '') +
        '<button type="button" class="btn btn-sm btn-link text-danger btn-remove-external" data-uid="' + escapeHtml(u._uid || '') + '">Remove</button></div>';
      $list.append(el);
    });
  }

  // missing handler for legacy remove button (.btn-remove)
  $(document).on('click', '.btn-remove', function(){
    const email = $(this).attr('data-email');
    const id = $(this).attr('data-id');
    if (email) {
      externalUsers = externalUsers.filter(u => u.email !== email);
      renderExternalList();
    } else if (id) {
      distributionsInternalIds = distributionsInternalIds.filter(x => String(x) !== String(id));
      regardsInternalIds = regardsInternalIds.filter(x => String(x) !== String(id));
      finalSelectedUsers = finalSelectedUsers.filter(u => String(u.id) !== String(id));
      selectedUserIds = selectedUserIds.filter(x => String(x) !== String(id));
      renderSelectedTargetPreview();
    }
    refreshSelectedPreview();
  });

  // ---------- submit handling ----------
  $('#btn-save-draft').on('click', function(){
    // if creating (no id) allow draft; if editing and you don't want draft behavior, change here
    submitNotice('draft');
  });
  $('#noticeForm').on('submit', function(e){ e.preventDefault(); submitNotice('publish'); });

  function submitNotice(type){
    $('#btn-publish, #btn-save-draft').prop('disabled', true);
    const id = $('#notice_id').val();
    const formData = new FormData();

    formData.append('memorial_no', $('#memorial_no').val() || '');
    formData.append('date', $('#notice_date').val() || '');
    formData.append('subject', $('#subject').val() || '');
    const bodyHtml = (quill && quill.root) ? quill.root.innerHTML : ($('#bodyInput').val() || '');
    const signatureHtml = (quillSignature && quillSignature.root) ? quillSignature.root.innerHTML : ($('#signatureInput').val() || '');
    formData.append('body', bodyHtml);
    formData.append('signature_body', signatureHtml || '');

    formData.append('status', type === 'draft' ? 'draft' : 'published');

    // departments
    $('.dept-checkbox:checked').each(function(i, el){ formData.append('departments['+i+']', $(el).val()); });

    // distributions.internal_user_ids
    distributionsInternalIds.forEach((uid, idx) => {
      formData.append(`distributions[internal_user_ids][${idx}]`, uid);
    });
    // distributions.external_users -> name/designation
    distributionsExternal.forEach((u, idx) => {
      formData.append(`distributions[external_users][${idx}][name]`, u.name);
      formData.append(`distributions[external_users][${idx}][designation]`, u.designation || '');
    });

    // regards.internal_user_ids
    regardsInternalIds.forEach((uid, idx) => {
      formData.append(`regards[internal_user_ids][${idx}]`, uid);
    });
    // regards.external_users -> name/designation/note
    regardsExternal.forEach((u, idx) => {
      formData.append(`regards[external_users][${idx}][name]`, u.name);
      formData.append(`regards[external_users][${idx}][designation]`, u.designation || '');
      formData.append(`regards[external_users][${idx}][note]`, u.note || '');
    });

    // method spoof on update
    if (id) formData.append('_method','PUT');

    const url = id ? API_BASE + '/notice-templates/' + id : API_BASE + '/notice-templates';
    $.ajax({
      url: url,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(res){
        t('Saved','success');
        const noticeModalEl = document.getElementById('noticeModal');
        const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl) : null;
        if (instance) instance.hide(); else $('#noticeModal').hide();
        loadNotices();
      },
        error: function(err){
          console.error('submitNotice failed', err);

          // collect messages from Laravel-style response
          let messages = [];
          if (err && err.responseJSON) {
            const json = err.responseJSON;
            if (json.errors && typeof json.errors === 'object') {
              Object.keys(json.errors).forEach(function(k){
                const arr = json.errors[k];
                if (Array.isArray(arr)) arr.forEach(m => messages.push(m));
                else if (arr) messages.push(arr);
              });
            }
            // fallback top-level message
            if (json.message && !messages.length) messages.push(json.message);
          }

          // fallback to generic body/text
          if (!messages.length) {
            const text = err.responseText || err.statusText || 'Save failed';
            messages.push(text);
          }

          // build html list
          const html = '<div style="text-align:left;">' + messages.map(m => '<div>• ' + escapeHtml(String(m)) + '</div>').join('') + '</div>';

          // ensure modal is visible and stays on top
          const noticeModalEl = document.getElementById('noticeModal');
          const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl) : null;
          if (instance) instance.show();

          // show full error list in SweetAlert
          Swal.fire({
            title: 'Save failed',
            html: html,
            icon: 'error',
            confirmButtonText: 'OK'
          }).then(function(){
            // try to focus the first invalid field (if browser constraints exist) or memorial_no
            const $firstInvalid = $('#noticeForm').find('input, textarea, select').filter(function(){
              try { return !this.checkValidity(); } catch(e){ return false; }
            }).first();
            if ($firstInvalid.length) $firstInvalid.focus();
            else $('#memorial_no').focus();
          });

          // also show the first server message as a toast for quick feedback
          t(messages[0] || 'Save failed', 'error');
        },


      complete: function(){ $('#btn-publish, #btn-save-draft').prop('disabled', false); }
    });
  }

  // ---------- delete handlers ----------
  $(document).on('click', '.btn-delete', function(){
    const id = $(this).data('id');
    if (!id) { t('Invalid id','error'); return; }
    Swal.fire({
      title: 'Delete notice?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      confirmButtonColor: '#d33'
    }).then(function(result){
      if (!result.isConfirmed) return;
      $.ajax({ url: API_BASE + '/notice-templates/' + id, method: 'DELETE' })
        .done(function(res){ t((res && res.message) || 'Deleted', 'success'); loadNotices(); })
        .fail(function(err){
          console.warn('DELETE failed, trying PATCH fallback', err);
          $.ajax({ url: API_BASE + '/notice-templates/' + id, method: 'PATCH' })
            .done(function(r){ t((r && r.message) || 'Deleted (fallback)', 'success'); loadNotices(); })
            .fail(function(err2){
              console.error('Delete failed', err2);
              const serverMsg = (err2 && err2.responseJSON && err2.responseJSON.message) || err2.responseText || err2.statusText || 'Delete failed';
              Swal.fire({ title: 'Delete failed', html: '<pre style="text-align:left;white-space:pre-wrap">' + escapeHtml(String(serverMsg)) + '</pre>', icon: 'error' });
              t('Delete failed','error');
            });
        });
    });
  });

  // modal delete
  $('#btn-delete-notice').on('click', function(){
    const id = $('#notice_id').val();
    if (!id) return;
    Swal.fire({
      title: 'Delete this notice?',
      text: 'This cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      confirmButtonColor: '#d33'
    }).then(function(result){
      if (!result.isConfirmed) return;
      $.ajax({ url: API_BASE + '/notice-templates/' + id, method: 'DELETE' })
        .done(function(res){ t((res && res.message) || 'Deleted', 'success'); const noticeModalEl = document.getElementById('noticeModal'); const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl) : null; if(instance) instance.hide(); loadNotices(); })
        .fail(function(err){
          console.warn('Modal DELETE failed, trying PATCH fallback', err);
          $.ajax({ url: API_BASE + '/notice-templates/' + id, method: 'PATCH' })
            .done(function(r){ t((r && r.message) || 'Deleted (fallback)', 'success'); const noticeModalEl = document.getElementById('noticeModal'); const instance = noticeModalEl ? bootstrap.Modal.getOrCreateInstance(noticeModalEl) : null; if (instance) instance.hide(); loadNotices(); })
            .fail(function(err2){
              console.error('Modal delete failed', err2);
              const serverMsg = (err2 && err2.responseJSON && err2.responseJSON.message) || err2.responseText || err2.statusText || 'Delete failed';
              Swal.fire({ title: 'Delete failed', html: '<pre style="text-align:left;white-space:pre-wrap">' + escapeHtml(String(serverMsg)) + '</pre>', icon: 'error' });
              t('Delete failed','error');
            });
        });
    });
  });

  // wire edit/open/create
  $('#btn-create').on('click', function(){ openNoticeModal(null); });
  $('#btn-add-room').on('click', function(){ const roomModalEl = document.getElementById('roomModal'); const instance = roomModalEl ? bootstrap.Modal.getOrCreateInstance(roomModalEl) : null; if(instance) instance.show(); });

  $(document).on('click', '.btn-edit', function(){
    const $btn = $(this);
    const id = $btn.data('id');
    if(!id) { t('Invalid id','error'); return; }
    $btn.prop('disabled', true);
    fetchNoticeById(id)
      .done(function(data){
        const notice = (data && data.data) ? data.data : (data || {});
        openNoticeModal(notice);
      })
      .fail(function(err){
        console.error('fetchNoticeById failed', err);
        const serverMsg = (err && err.responseJSON && err.responseJSON.message) || err.responseText || err.statusText || 'Failed to load notice';
        Swal.fire({ title: 'Could not load notice', html: '<pre style="text-align:left;white-space:pre-wrap">' + escapeHtml(String(serverMsg)) + '</pre>', icon: 'error' });
        t('Failed to load notice (see details)','error');
      })
      .always(function(){ $btn.prop('disabled', false); });
  });

  // init
  loadNotices();
  loadDepartments();

  // refresh
  $('#btn-refresh').on('click', function(){ loadNotices(); });

})(jQuery);
</script>
@endsection
