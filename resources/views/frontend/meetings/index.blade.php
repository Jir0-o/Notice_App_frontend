@extends('layouts.master')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/main.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />
<style>
  /* small helpers */
  .modal-panel { max-width: 900px; width: 95%; max-height: 90vh; overflow:auto; }
  .side-box { max-height: 32vh; overflow:auto; padding: .5rem; border:1px solid #e3e3e3; border-radius:6px; }
  .badge-ext { background:#333;color:#fff;padding:2px 6px;border-radius:8px;font-size:12px; }
</style>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Meeting Scheduler</h5>
    <div>
      <button id="btn-refresh" class="btn btn-sm btn-outline-primary">Refresh</button>
    </div>
  </div>
  <div class="card-body">
    <div id="calendar"></div>
  </div>
</div>

<!-- Create / Update Modal (same markup; script toggles mode) -->
<div id="meetingModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-panel">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="meetingModalTitle" class="modal-title">Create Meeting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="meetingForm">
        <input type="hidden" name="meeting_id" id="form_meeting_record_id" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input name="title" id="title" required class="form-control" placeholder="Enter meeting title" />
          </div>

          <div class="row g-2">
            <div class="col-md-4 mb-3">
              <label class="form-label">Date</label>
              <input name="date" id="date" class="form-control" readonly />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Start time</label>
              <input type="time" name="start_time" id="start_time" class="form-control" required />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">End time</label>
              <input type="time" name="end_time" id="end_time" class="form-control" required />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Room (available after start/end)</label>
            <select name="room_id" id="room_id" class="form-select">
              <option value="">Select room</option>
            </select>
            <div class="form-text" id="roomInfo">Enter start & end to load rooms</div>
          </div>

          <div class="d-flex gap-2 mb-2 align-items-center">
            <div><strong>Add participants</strong></div>
            <div class="ms-auto">
              <button type="button" id="btn-toggle-external" class="btn btn-sm btn-outline-secondary">External user</button>
            </div>
          </div>

          <div id="externalForm" style="display:none;" class="mb-3">
            <div class="row g-2">
              <div class="col-md-5"><input id="ext_name" placeholder="Name" class="form-control" /></div>
              <div class="col-md-5"><input id="ext_email" placeholder="Email" class="form-control" /></div>
              <div class="col-md-2"><button type="button" id="btn-add-external" class="btn btn-primary w-100">Add</button></div>
            </div>
          </div>

          <div class="row gy-3">
            <div class="col-md-3">
              <label class="form-label">Departments</label>
              <div id="departmentsBox" class="side-box"></div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Internal users</label>
              <div id="usersBox" class="side-box">Select departments to load users</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Selected recipients (<span id="selectedCount">0</span>)</label>
              <div id="selectedPreview" class="side-box"></div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" id="btn-delete" class="btn btn-danger me-auto" style="display:none;">Delete</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" id="btn-save" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Event details modal - upgraded layout -->
<div id="eventDetailsModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
            <i class="bx bx-calendar-event text-white fs-5"></i>
          </div>
          <div>
            <h5 id="detailTitle" class="modal-title mb-0">Meeting details</h5>
            {{-- <small id="detailStatusBadge" class="badge bg-secondary mt-1" style="font-size:.75rem; display:inline-block;">Status</small> --}}
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row gx-4">
          <!-- left: main info -->
          <div class="col-lg-7">
            {{-- <div class="mb-3">
              <h4 id="detail_title" class="fw-bold mb-1">—</h4>
              <div class="text-muted small" id="detail_organizer">Organizer: —</div>
            </div> --}}

            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="p-2 border rounded">
                  <small class="text-muted">Start</small>
                  <div id="detail_start" class="fw-medium">—</div>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 border rounded">
                  <small class="text-muted">End</small>
                  <div id="detail_end" class="fw-medium">—</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <small class="text-muted">Room</small>
              <div class="p-2 border rounded d-flex justify-content-between align-items-center">
                <div>
                  <div id="detail_room" class="fw-medium">—</div>
                  <small id="detail_room_meta" class="text-muted">Capacity: —</small>
                </div>
                <div id="detail_room_badge"></div>
              </div>
            </div>

            {{-- <div class="mb-3">
              <small class="text-muted">Description</small>
              <div id="detail_description" class="border rounded p-3 mt-1" style="max-height:240px; overflow:auto; background:#fafafa;"></div>
            </div> --}}

          </div>

          <!-- right: attendees + stats -->
          <div class="col-lg-5">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <small class="text-muted">Attendees</small>
                  <div id="detail_count" class="fw-bold">0</div>
                </div>
                <div>
                  <small class="text-muted">Propagation</small>
                </div>
              </div>
              <div id="detail_attendees" class="mt-2 border rounded p-2" style="max-height:320px; overflow:auto;">
                <div class="text-muted small">No attendees</div>
              </div>
            </div>

            <div class="mb-3">
              <small class="text-muted">Quick actions</small>
              <div class="d-grid gap-2 mt-2">
                <button id="detail_edit" class="btn btn-outline-primary btn-sm">Edit meeting</button>
                <button id="detail_delete" class="btn btn-outline-danger btn-sm">Delete meeting</button>
                <button id="detail_export" class="btn btn-outline-secondary btn-sm">Export .ics</button>
              </div>
            </div>

            {{-- <div class="small text-muted border-top pt-2">
              <div>Created: <span id="detail_created_at">—</span></div>
              <div>Last updated: <span id="detail_updated_at">—</span></div>
            </div> --}}
          </div>
        </div>
      </div>

      <div class="modal-footer border-top">
        <button id="btn-close-detail" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- jQuery first (some of your UI code uses it) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- FullCalendar global bundle (exposes window.FullCalendar) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/locales-all.global.min.js"></script>

<!-- Helpers -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function(){
  // config
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}/api";
  const token = localStorage.getItem('api_token') || null;

  // setup AJAX headers
  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  // Bootstrap modal instances (Bootstrap 5)
  const meetingModalEl = document.getElementById('meetingModal');
  const meetingModal = meetingModalEl ? new bootstrap.Modal(meetingModalEl, { backdrop: 'static' }) : null;
  const eventDetailsModalEl = document.getElementById('eventDetailsModal');
  const eventDetailsModal = eventDetailsModalEl ? new bootstrap.Modal(eventDetailsModalEl) : null;

  // state
  let calendar = null, currentEvent = null;
  let departments = [];           // list of {id, name}
  let departmentUsers = {};       // map depId => [users]
  let selectedUsers = [];         // ids (Number)
  let finalSelectedUsers = [];    // {id?, name, email}
  let externalUsers = [];         // {name, email}

  // helpers
  function showToast(type, msg){ if (window.toastr) toastr[type](msg); else console[type==='error'?'error':'log'](msg); }
  function safeData(res){
    if (!res) return null;
    if (typeof res === 'object' && ('data' in res)) return res.data;
    return res;
  }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function escapeAttr(s){ return String(s || '').replace(/"/g,'&quot;'); }

  // ---------- NEW: showEventDetails helper (professional modal population + actions) ----------
  function showEventDetails(event) {
    const ext = event.extendedProps || {};

    function formatDateTime(dt){
      if(!dt) return '—';
      try {
        const d = (typeof dt === 'string') ? new Date(dt) : dt;
        return d.toLocaleString();
      } catch(e) { return dt; }
    }

    // Title & status
    $('#detailTitle').text(event.title || (ext.title || 'Meeting details'));
    $('#detail_title').text(event.title || (ext.title || 'Untitled meeting'));

    // Organizer
    const organizer = ext.organizer || ext.owner || (ext.meeting && ext.meeting.organizer) || (ext.user && ext.user.name) || '';
    $('#detail_organizer').text(organizer ? `Organizer: ${organizer}` : 'Organizer: —');

    // times
    const start = event.start || ext.start || (ext.date && (ext.start_time ? (ext.date + ' ' + ext.start_time) : ext.date));
    const end = event.end || ext.end || (ext.date && (ext.end_time ? (ext.date + ' ' + ext.end_time) : ext.date));
    $('#detail_start').text(formatDateTime(start));
    $('#detail_end').text(formatDateTime(end));

    // room and capacity
    const roomTitle = (ext.meeting?.title || ext.meetingInfo?.title || ext.room_title || ext.room || '—');
    $('#detail_room').text(roomTitle);
    const capacity = ext.meeting?.capacity || ext.room?.capacity || ext.capacity || '';
    $('#detail_room_meta').text(capacity ? `Capacity: ${capacity}` : 'Capacity: —');

    // status badge
    const status = (ext.status || ext.meeting?.status || (ext.published ? 'published' : 'draft')) || 'N/A';
    const $badge = $('#detailStatusBadge').removeClass().addClass('badge');
    if(status === 'published' || status === 'confirmed') $badge.addClass('bg-success').text(String(status).toUpperCase());
    else if(status === 'cancelled') $badge.addClass('bg-danger').text(String(status).toUpperCase());
    else $badge.addClass('bg-secondary').text(String(status).toUpperCase());

    // description (sanitized lightly - using text for safety if needed)
    const descHtml = ext.description || ext.meeting?.description || ext.excerpt || '';
    $('#detail_description').html(descHtml ? descHtml : '<div class="text-muted small">No description provided</div>');

    // attendees / propagations
    const props = Array.isArray(ext.propagations) ? ext.propagations : (ext.propagation || ext.attendees || ext.participants || []);
    const attendeesBox = $('#detail_attendees').empty();
    if(Array.isArray(props) && props.length){
      props.forEach(p => {
        const name = p.user?.name || p.user_name || p.name || p.full_name || 'N/A';
        const email = p.user?.email || p.user_email || p.email || '';
        const readStatus = (typeof p.is_read !== 'undefined') ? (p.is_read ? '<span class="text-success small">Read</span>' : '<span class="text-danger small">Unread</span>') : '';
        const typeBadge = (p.user_id === null) ? '<span class="badge bg-dark ms-2">External</span>' : '<span class="badge bg-light text-dark ms-2">Internal</span>';
        attendeesBox.append(`
          <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
            <div>
              <div class="fw-medium">${escapeHtml(name)} ${typeBadge}</div>
              <div class="small text-muted">${escapeHtml(email)}</div>
            </div>
            <div class="text-end small">${readStatus}</div>
          </div>
        `);
      });
    } else {
      attendeesBox.html('<div class="text-muted small">No attendees</div>');
    }
    $('#detail_count').text(Array.isArray(props) ? props.length : 0);

    // created/updated
    $('#detail_created_at').text(ext.created_at ? new Date(ext.created_at).toLocaleString() : (ext.created_at || '—'));
    $('#detail_updated_at').text(ext.updated_at ? new Date(ext.updated_at).toLocaleString() : (ext.updated_at || '—'));

    // wire Edit - reuse your openEditModalFromEvent
    $('#detail_edit').off('click').on('click', function(){
      if(eventDetailsModal) eventDetailsModal.hide();
      if (typeof openEditModalFromEvent === 'function') {
        openEditModalFromEvent(event);
      } else {
        showToast('error', 'Edit action not available.');
      }
    });

    // wire Delete
    $('#detail_delete').off('click').on('click', function(){
      if(!confirm('Delete this meeting?')) return;
      const id = event.id || ext.id || ext.meeting?.id;
      if(!id) { alert('No ID for this meeting'); return; }
      $.ajax({
        url: `${API_BASE}/meetings-details/${id}`,
        method: 'DELETE',
        beforeSend: function(xhr){ if(token) xhr.setRequestHeader('Authorization','Bearer '+token); }
      }).done(function(){
        if(eventDetailsModal) eventDetailsModal.hide();
        showToast('success', 'Meeting deleted');
        if(typeof fetchMeetings === 'function') fetchMeetings().then(loadEventsToCalendar);
      }).fail(function(){
        alert('Failed to delete meeting');
      });
    });

    // Export .ics (basic)
    $('#detail_export').off('click').on('click', function(){
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const startISO = (event.start && event.start.toISOString()) || ext.start || '';
      const endISO = (event.end && event.end.toISOString()) || ext.end || '';
      const icsLines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//YourApp//EN',
        'BEGIN:VEVENT',
        `UID:${event.id || ext.id || Date.now()}@yourapp`,
        `DTSTAMP:${(new Date()).toISOString().replace(/[-:]/g,'').split('.')[0]}Z`,
        startISO ? `DTSTART:${startISO.replace(/[-:]/g,'')}` : '',
        endISO ? `DTEND:${endISO.replace(/[-:]/g,'')}` : '',
        `SUMMARY:${(event.title || ext.title || '').replace(/\n/g,' ')}`,
        `DESCRIPTION:${(descHtml || '').replace(/\n/g,' ')}`,
        `LOCATION:${roomTitle}`,
        'END:VEVENT',
        'END:VCALENDAR'
      ].filter(Boolean).join('\r\n');
      const blob = new Blob([icsLines], { type: 'text/calendar;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = `${(event.title||'meeting')}.ics`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    });

    // show modal
    if(eventDetailsModal) eventDetailsModal.show();
    else $('#eventDetailsModal').modal('show');
  }
  // ---------- END showEventDetails helper ----------

  // preview renderer (used by meeting modal)
  function refreshSelectedPreview(){
    const merged = [...finalSelectedUsers, ...externalUsers];
    $('#selectedCount').text(merged.length);
    const box = $('#selectedPreview').empty();
    if(merged.length === 0){ box.html('<small class="text-muted">No recipients selected</small>'); return; }
    merged.forEach(u => {
      const isInternal = typeof u.id !== 'undefined' && u.id !== null;
      const idAttr = isInternal ? `data-id="${String(u.id)}"` : `data-email="${escapeAttr(u.email)}"`;
      box.append(`
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <div>
            <div><strong>${escapeHtml(u.name)}</strong> <small class="text-muted ms-2">${isInternal ? 'internal' : 'external'}</small></div>
            <div class="small">${escapeHtml(u.email || '')}</div>
          </div>
          <div>
            <button class="btn btn-sm btn-outline-danger btn-remove" ${idAttr}>Remove</button>
          </div>
        </div>
      `);
    });
  }

  function addExternalUser(name, email){
    if(!name || !email) { showToast('error','Name & email required'); return; }
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ showToast('error','Invalid email'); return; }
    if(externalUsers.some(u=>u.email.toLowerCase()===email.toLowerCase())) { showToast('warning','External user already added'); return; }
    externalUsers.push({name, email});
    $('#ext_name').val(''); $('#ext_email').val('');
    refreshSelectedPreview();
  }

  function toggleInternalUser(user){
    const idNum = Number(user.id);
    const idx = finalSelectedUsers.findIndex(u => Number(u.id) === idNum);
    if(idx >= 0){
      finalSelectedUsers.splice(idx, 1);
      selectedUsers = selectedUsers.filter(id => Number(id) !== idNum);
    } else {
      finalSelectedUsers.push({ id: idNum, name: user.name || user.full_name || user.name || '', email: user.email || '' });
      selectedUsers.push(idNum);
    }
    refreshSelectedPreview();
    $(`#user-checkbox-${idNum}`).prop('checked', finalSelectedUsers.some(u => Number(u.id) === idNum));
  }

  // fetch departments & render
  function fetchDepartments(){
    return $.ajax({ url: `${API_BASE}/departments-data`, method: 'GET', dataType: 'json' })
      .then(res => {
        const data = safeData(res) || [];
        departments = Array.isArray(data) ? data : [];
        renderDepartments();
        return departments;
      }).fail(err => {
        console.error('fetchDepartments', err);
        showToast('error','Could not load departments');
        departments = [];
        renderDepartments();
        return [];
      });
  }
  function renderDepartments(){
    const box = $('#departmentsBox').empty();
    if(!departments.length){ box.html('<small class="text-muted">No departments found</small>'); return; }
    departments.forEach(d=> {
      box.append(`
        <div class="form-check">
          <input class="form-check-input dept-checkbox" type="checkbox" value="${String(d.id)}" id="dept-${d.id}">
          <label class="form-check-label" for="dept-${d.id}">${escapeHtml(d.name || d.title || '')}</label>
        </div>
      `);
    });
  }

  // fetch users (primary endpoint then fallback)
  function filterUsersByDept(users, depId) {
    if (!Array.isArray(users)) return [];
    const depStr = String(depId);
    return users.filter(u => {
      if (!u) return false;
      if (u.department_id && String(u.department_id) === depStr) return true;
      if (u.department && String(u.department.id || u.department.department_id || '') === depStr) return true;
      if (Array.isArray(u.departments) && u.departments.some(d => String(d.id || d.department_id || '') === depStr)) return true;
      if (u.dept_id && String(u.dept_id) === depStr) return true;
      return false;
    });
  }

  function fetchDepartmentUsers(depId){
    return $.ajax({ url: `${API_BASE}/notices/department-users/${depId}`, method: 'GET', dataType: 'json' })
      .then(res => {
        const data = safeData(res) || [];
        departmentUsers[depId] = Array.isArray(data) ? data : [];
        return departmentUsers[depId];
      }).fail(err => {
        console.warn('Primary department-users failed', err && err.status);
        return $.ajax({ url: `${API_BASE}/users`, method: 'GET', dataType: 'json' })
          .then(uRes => {
            const all = safeData(uRes) || [];
            const filtered = filterUsersByDept(all, depId);
            departmentUsers[depId] = filtered;
            return departmentUsers[depId];
          }).fail(uErr => {
            console.error('Fallback /api/users failed', uErr);
            departmentUsers[depId] = [];
            return departmentUsers[depId];
          });
      });
  }

  function renderUsersBox(){
    const box = $('#usersBox').empty();
    const users = Object.values(departmentUsers).flat();
    if(users.length === 0) { box.html('<small class="text-muted">No users for selected departments</small>'); return; }
    const map = new Map(); users.forEach(u => { if(u && typeof u.id !== 'undefined') map.set(String(u.id), u); });
    Array.from(map.values()).forEach(u => {
      const uid = String(u.id);
      const checked = finalSelectedUsers.some(f => String(f.id) === uid) ? 'checked' : '';
      const html = $(`<div class="form-check">
          <input class="form-check-input user-checkbox" type="checkbox" id="user-checkbox-${uid}" data-id="${uid}">
          <label class="form-check-label" for="user-checkbox-${uid}">${escapeHtml(u.name || u.full_name || 'User')} <small class="text-muted">(${escapeHtml(u.email||'')})</small></label>
        </div>`);
      if(checked) html.find('input').prop('checked', true);
      box.append(html);
    });
  }

  // fetch rooms
  function fetchRooms(date, start, end){
    if(!date || !start || !end) { $('#roomInfo').text('Enter start & end to load rooms'); return Promise.resolve([]); }
    $('#roomInfo').text('Loading rooms...');
    return $.ajax({ url: `${API_BASE}/v1/meetings/free`, method:'GET', data: { date, start_time: start, end_time: end }, dataType: 'json' })
      .then(res => {
        const data = Array.isArray(safeData(res) ? safeData(res) : res) ? safeData(res) : (res.data || []);
        const sel = $('#room_id').empty().append('<option value="">Select room</option>');
        (Array.isArray(data) ? data : []).forEach(r => sel.append(`<option value="${String(r.id)}">${escapeHtml(r.title || r.name || '')} (cap: ${r.capacity || 'N/A'})</option>`));
        $('#roomInfo').text('');
        return data || [];
      }).fail(err => {
        console.error('fetchRooms', err);
        $('#roomInfo').text('Could not fetch rooms');
        return [];
      });
  }

  // Meetings fetch + calendar loader
  function fetchMeetings(){
    return $.ajax({ url: `${API_BASE}/meetings-details?include=true`, method: 'GET', dataType: 'json' })
      .then(res => {
        const data = safeData(res) || [];
        return Array.isArray(data) ? data : [];
      }).fail(err => {
        console.error('fetchMeetings', err);
        showToast('error','Could not fetch meetings');
        return [];
      });
  }

  const calendarEl = document.getElementById('calendar');
  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    dateClick: function(info){ openCreateModal(info.dateStr); },
    eventClick: function(info){
      currentEvent = info.event;
      // use improved details modal
      showEventDetails(info.event);
    },
    events: [],
    eventDidMount: function(info){
      info.el.style.backgroundColor = '#3788d8';
      info.el.style.borderColor = '#3788d8';
      info.el.style.color = '#fff';
    }
  });
  calendar.render();

  function loadEventsToCalendar(events){
    calendar.removeAllEvents();
    (events || []).forEach(m => {
      const startStr = (m.date && m.start_time) ? `${m.date}T${(m.start_time||'').slice(0,8)}` : (m.start || m.start_time || null);
      const endStr = (m.date && m.end_time) ? `${m.date}T${(m.end_time||'').slice(0,8)}` : (m.end || m.end_time || null);
      calendar.addEvent({
        id: String(m.id),
        title: m.title || (m.meeting?.title || 'Meeting'),
        start: startStr,
        end: endStr,
        extendedProps: m
      });
    });
  }

  function reloadAll(){
    Promise.all([ fetchDepartments(), fetchMeetings() ])
      .then(([deps, meetings]) => {
        loadEventsToCalendar(meetings || []);
      }).catch(err => {
        console.error('reloadAll err', err);
      });
  }
  reloadAll();

  $('#btn-refresh').on('click', reloadAll);

  // open create modal
  function openCreateModal(dateStr){
    currentEvent = null;
    $('#meetingModalTitle').text('Create Meeting');
    $('#meetingForm')[0].reset();
    $('#form_meeting_record_id').val('');
    finalSelectedUsers = []; externalUsers = []; selectedUsers = []; departmentUsers = {};
    $('#room_id').empty().append('<option value="">Select room</option>');
    $('#roomInfo').text('Enter start & end to load rooms');
    $('#btn-delete').hide();
    $('#date').val(dateStr);
    if(meetingModal) meetingModal.show();
    fetchDepartments();
    refreshSelectedPreview();
    renderUsersBox();
  }

  // open edit modal
  function openEditModalFromEvent(eventObj){
    const m = eventObj.extendedProps || {};

    $('#meetingModalTitle').text('Update Meeting');
    $('#form_meeting_record_id').val(eventObj.id);
    $('#title').val(eventObj.title || (m.title || ''));
    $('#date').val(m.date || m.meeting_date || (eventObj.startStr ? eventObj.startStr.split('T')[0] : ''));

    // normalize time picking
    $('#start_time').val(m.start_time || (eventObj.start ? formatTime(eventObj.start) : ''));
    $('#end_time').val(m.end_time || (eventObj.end ? formatTime(eventObj.end) : ''));

    // reset and populate recipients
    finalSelectedUsers = [];
    selectedUsers = [];
    externalUsers = [];

    if(Array.isArray(m.propagations)){
      m.propagations.forEach(p=>{
        if(p.user_id){
          const uid = Number(p.user_id);
          finalSelectedUsers.push({
            id: uid,
            name: (p.user && p.user.name) || p.user_name || p.user?.full_name || 'User',
            email: (p.user && p.user.email) || p.user_email || ''
          });
          selectedUsers.push(uid);
        } else {
          externalUsers.push({ name: p.user_name || p.name || '', email: p.user_email || p.email || '' });
        }
      });
    }

    refreshSelectedPreview();

    $('#btn-delete').show();
    if(meetingModal) meetingModal.show();

    const currentRoomId = m.meeting?.id || m.meeting_id || m.meetingInfo?.id || m.room_id || null;
    const currentRoomTitle = m.meeting?.title || m.meetingInfo?.title || m.room_title || '';

    fetchDepartments().then(()=> {
      fetchRooms($('#date').val(), $('#start_time').val(), $('#end_time').val())
        .then(rooms => {
          if(currentRoomId){
            if($('#room_id option[value="' + String(currentRoomId) + '"]').length === 0){
              const t = currentRoomTitle || ('Room #' + String(currentRoomId));
              $('#room_id').append(`<option value="${String(currentRoomId)}">${escapeHtml(t)} (assigned)</option>`);
            }
            $('#room_id').val(String(currentRoomId));
          } else {
            $('#room_id').val('');
          }
        })
        .catch(() => {
          if(currentRoomId && $('#room_id option[value="' + String(currentRoomId) + '"]').length === 0){
            const t = currentRoomTitle || ('Room #' + String(currentRoomId));
            $('#room_id').append(`<option value="${String(currentRoomId)}">${escapeHtml(t)} (assigned)</option>`);
            $('#room_id').val(String(currentRoomId));
          }
        })
        .finally(()=> {
          renderUsersBox();
          refreshSelectedPreview();
        });
    }).catch(()=> {
      renderUsersBox();
      refreshSelectedPreview();
    });
  }

  function formatTime(d){
    if(!d) return '';
    const date = (d instanceof Date) ? d : new Date(d);
    const hh = String(date.getHours()).padStart(2,'0');
    const mm = String(date.getMinutes()).padStart(2,'0');
    return `${hh}:${mm}`;
  }

  // date/time change -> fetch rooms
  $('#start_time, #end_time').on('change', function(){
    const date = $('#date').val();
    const start = $('#start_time').val();
    const end = $('#end_time').val();
    if(date && start && end){ fetchRooms(date, start, end); }
  });

  // toggle external
  $('#btn-toggle-external').on('click', function(){ $('#externalForm').toggle(); });

  $('#btn-add-external').on('click', function(){
    const name = ($('#ext_name').val() || '').toString().trim();
    const email = ($('#ext_email').val() || '').toString().trim();
    addExternalUser(name, email);
  });

  // department checkbox -> fetch users
  $(document).on('change', '.dept-checkbox', function(){
    const depId = $(this).val();
    const checked = $(this).is(':checked');
    if(checked){
      fetchDepartmentUsers(depId).then(()=> renderUsersBox());
    } else {
      delete departmentUsers[depId];
      renderUsersBox();
    }
  });

  // user checkbox toggle
  $(document).on('change', '.user-checkbox', function(){
    const id = Number($(this).data('id'));
    let found = null;
    Object.values(departmentUsers).flat().forEach(u => { if(u && Number(u.id) === id) found = u; });
    if(found) toggleInternalUser(found);
    renderUsersBox();
  });

  // remove in preview
  $(document).on('click', '.btn-remove', function(){
    const idAttr = $(this).attr('data-id');
    const emailAttr = $(this).attr('data-email');
    if(idAttr){
      const id = Number(idAttr);
      finalSelectedUsers = finalSelectedUsers.filter(u => Number(u.id) !== id);
      selectedUsers = selectedUsers.filter(x => Number(x) !== id);
    } else if(emailAttr){
      externalUsers = externalUsers.filter(u => u.email !== emailAttr);
    }
    refreshSelectedPreview();
    renderUsersBox();
  });

  // Save (create or update)
  $('#meetingForm').on('submit', function(e){
    e.preventDefault();
    $('#btn-save').prop('disabled', true);
    const recordId = $('#form_meeting_record_id').val();
    const payload = {
      title: $('#title').val(),
      date: $('#date').val(),
      start_time: $('#start_time').val(),
      end_time: $('#end_time').val(),
      meeting_id: parseInt($('#room_id').val()) || null,
      internal_users: selectedUsers || [],
      external_users: externalUsers || []
    };

    const url = recordId ? `${API_BASE}/meetings-details/${recordId}?include=true` : `${API_BASE}/meetings-details?include=true`;
    const method = recordId ? 'PUT' : 'POST';

    $.ajax({
      url, method,
      data: JSON.stringify(payload),
      contentType: 'application/json; charset=utf-8',
      dataType: 'json'
    }).done(function(res){
      const ok = (res && (res.success === true || res.status === 'success')) || (res && (res.data || res.id));
      if(ok){
        showToast('success', res.message || 'Saved');
        if(meetingModal) meetingModal.hide();
        fetchMeetings().then(loadEventsToCalendar);
      } else {
        showToast('error', res.message || 'Failed');
      }
    }).fail(function(xhr){
      const msg = xhr.responseJSON?.message || xhr.responseText || 'Server error';
      showToast('error', msg);
      console.error('Save meeting failed', xhr);
    }).always(function(){ $('#btn-save').prop('disabled', false); });

  });

  // delete via modal button
  $('#btn-delete').on('click', function(){
    const id = $('#form_meeting_record_id').val();
    if(!id) return;
    Swal.fire({
      title: 'Delete meeting?',
      text: "This cannot be undone.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete'
    }).then(result=>{
      if(result.isConfirmed){
        $.ajax({ url: `${API_BASE}/meetings-details/${id}`, method: 'DELETE' })
        .done(res=>{
          showToast('success', res.message || 'Deleted');
          if(meetingModal) meetingModal.hide();
          fetchMeetings().then(loadEventsToCalendar);
        }).fail(err=>{
          showToast('error', 'Delete failed');
          console.error('Delete failed', err);
        });
      }
    });
  });

  // details modal -> open edit
  $('#btn-open-edit').on('click', function(){
    if(eventDetailsModal) eventDetailsModal.hide();
    if(currentEvent) openEditModalFromEvent(currentEvent);
  });

  $('#btn-close-detail').on('click', function(){ currentEvent = null; });

});
</script>

@endsection
