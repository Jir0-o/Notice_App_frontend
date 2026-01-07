@extends('layouts.master')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/main.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />
<style>
  .modal-panel { max-width: 1000px; width: 98%; max-height: 92vh; margin: 0 auto; }
  .modal-panel .modal-content { max-height: 92vh; overflow: auto; }
  .side-box {
    max-height: 32vh;
    overflow:auto;
    padding:.5rem .7rem;
    border:1px solid #e3e3e3;
    border-radius:6px;
    background:#fff;
  }
  .badge-ext { background:#333;color:#fff;padding:2px 6px;border-radius:8px;font-size:12px; }
  .badge-busy { background:#dc3545; color:#fff; border-radius:6px; padding:2px 6px; font-size:12px; }
  .file-chip { border:1px solid #ddd; border-radius:8px; padding:.25rem .5rem; display:inline-flex; gap:.5rem; align-items:center; margin:.25rem .25rem 0 0; }
  .file-chip button { border:none; background:none; color:#dc3545; }
  #meetingModal .modal-body { background: #f7f8f9; }
  .update-note { font-size: .8rem; color: #b30000; text-align: center; }

  /* Bangladesh weekend highlight: Friday(5) + Saturday(6) */
  .fc .bd-weekend-cell{
    background: rgba(220, 53, 69, .08) !important; /* light red */
  }
  .fc .bd-weekend-cell .fc-daygrid-day-number{
    color: #dc3545 !important;
    font-weight: 700;
  }
  .fc .bd-weekend-header{
    background: rgba(220, 53, 69, .12) !important;
    color: #b30000 !important;
    font-weight: 700;
  }
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

<!-- Create / Update Modal -->
<div id="meetingModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-panel">
    <div class="modal-content">
      <div class="modal-header flex-column align-items-stretch">
        <div class="d-flex w-100 align-items-center">
          <h5 id="meetingModalTitle" class="modal-title flex-grow-1">Create Meeting</h5>
          <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div id="meetingUpdateNote" class="update-note mt-1" style="display:none;">
          একই তারিখ ও স্মারকে স্থলাভিষিক্ত হবে।
        </div>
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
              <input type="date" name="date" id="date" class="form-control" />
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

          {{-- meeting chair userlist --}}
          <div class="mb-3">
            <label class="form-label">Meeting Chair</label>
            <select name="meeting_chair_id" id="meeting_chair_id" class="form-select">
              <option value="">Select meeting chair</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Room (available after start/end)</label>
            <select name="room_id" id="room_id" class="form-select">
              <option value="">Select room</option>
            </select>
            <div class="form-text" id="roomInfo">Enter start & end to load rooms</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Agenda</label>
            <div id="agendaEditor" style="height:180px; background:#fff;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Attachments</label>
            <input id="attachments" type="file" class="form-control" multiple
                   accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.jpeg,.png" />
            <small class="text-muted">Allowed: pdf, doc/docx, xls/xlsx, jpg/jpeg/png. Max 10MB per file.</small>
            <div id="attachmentsPreview" class="mt-2"></div>
            <div id="existingAttachments" class="mt-2"></div>
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
            <!-- Departments box with select-all -->
            <div class="col-md-3">
              <label class="form-label mb-1 d-flex justify-content-between align-items-center">
                <span>Departments</span>
                <span class="form-check m-0">
                  <input type="checkbox" class="form-check-input" id="dept-all">
                  <label for="dept-all" class="form-check-label small">All</label>
                </span>
              </label>
              <div id="departmentsBox" class="side-box"></div>
            </div>

            <!-- Users -->
            <div class="col-md-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label mb-0">Internal users</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="selectAllNonBusy">
                  <label class="form-check-label small" for="selectAllNonBusy">Select all (skip busy)</label>
                </div>
              </div>
              <div id="usersBox" class="side-box">Select departments to load users</div>
            </div>

            <!-- Selected preview -->
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

<!-- Event details modal -->
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
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row gx-4">
          <div class="col-lg-7">
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
              <small class="text-muted">Meeting Chair</small>
              <div class="p-2 border rounded">
                <div id="detail_chair" class="fw-medium">—</div>
                <small id="detail_chair_email" class="text-muted">—</small>
              </div>
            </div>

            <div class="mb-3">
              <small class="text-muted">Room</small>
              <div class="p-2 border rounded">
                <div id="detail_room" class="fw-medium">—</div>
                <small id="detail_room_meta" class="text-muted">Capacity: —</small>
              </div>
            </div>

            <div class="mb-3">
              <small class="text-muted">Agenda</small>
              <div id="detail_agenda" class="border rounded p-3 mt-1" style="max-height:240px; overflow:auto; background:#fafafa;"></div>
            </div>

            <div class="mb-2">
              <small class="text-muted">Attachments</small>
              <div id="detail_attachments" class="mt-1"></div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <small class="text-muted">Attendees</small>
                  <div id="detail_count" class="fw-bold">0</div>
                </div>
                <div><small class="text-muted">Propagation</small></div>
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

          </div>
        </div>
      </div>

      <div class="modal-footer border-top">
        <button id="btn-close-detail" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- libs -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/locales-all.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function(){
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}/api";
  const token = localStorage.getItem('api_token') || null;

  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){ if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token); }
  });

  const meetingModalEl = document.getElementById('meetingModal');
  const meetingModal = meetingModalEl ? new bootstrap.Modal(meetingModalEl, { backdrop: 'static' }) : null;
  const eventDetailsModalEl = document.getElementById('eventDetailsModal');
  const eventDetailsModal = eventDetailsModalEl ? new bootstrap.Modal(eventDetailsModalEl) : null;
  const meetingUpdateNoteEl = document.getElementById('meetingUpdateNote');

  let calendar = null, currentEvent = null;
  let departments = [];
  let departmentUsers = {};       // {deptId: [users]}
  let selectedUsers = [];         // pure ids for payload
  let finalSelectedUsers = [];    // [{id,name,email}]
  let externalUsers = [];
  let existingAtts = [];
  let agendaQuill = null;
  let userChangedEndTime = false;

  // IMPORTANT:
  // - currentEditingMeetingId = the meeting we are editing right now
  // - freedUsersFromCurrent   = users that were in this meeting but we removed them this time,
  //                             so they must be selectable again
  let currentEditingMeetingId = null;
  let freedUsersFromCurrent = new Set();

  function t(type,msg){ if(window.toastr) toastr[type](msg); else console[type==='error'?'error':'log'](msg); }
  function safeData(res){ if(!res) return null; return (typeof res === 'object' && 'data' in res) ? res.data : res; }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function addMinutesToTime(hhmm, mins) {
    if (!hhmm) return '';
    const parts = hhmm.split(':');
    const h = Number(parts[0] || 0);
    const m = Number(parts[1] || 0);
    let total = h * 60 + m + mins;
    if (total < 0) total = 0;
    const hh = String(Math.floor(total / 60) % 24).padStart(2,'0');
    const mm = String(total % 60).padStart(2,'0');
    return `${hh}:${mm}`;
  }

  agendaQuill = new Quill('#agendaEditor', { theme: 'snow' });

  const calendarEl = document.getElementById('calendar');
  // Bangladesh weekend: Friday(5), Saturday(6)
  const BD_WEEKEND_DAYS = [5, 6];

  function isBdWeekend(dateLike) {
    if (!dateLike) return false;

    // if string "YYYY-MM-DD"
    if (typeof dateLike === 'string') {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dateLike)) return false;
      dateLike = new Date(dateLike + 'T00:00:00'); // local parse
    }

    const d = (dateLike instanceof Date) ? dateLike : new Date(dateLike);
    if (isNaN(d.getTime())) return false;

    return BD_WEEKEND_DAYS.includes(d.getDay());
  }

  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 'auto',

    // optional: week starts Sunday (common in BD)
    firstDay: 0,

    // ✅ paint BD weekend header red
    dayHeaderClassNames: function(arg){
      return BD_WEEKEND_DAYS.includes(arg.date.getDay()) ? ['bd-weekend-header'] : [];
    },

    // ✅ paint BD weekend day cells red (month/week/day views)
    dayCellClassNames: function(arg){
      return isBdWeekend(arg.date) ? ['bd-weekend-cell'] : [];
    },

    // ✅ block creating meeting on BD weekend
    dateClick: function(info){
      if (isBdWeekend(info.date)) {
        t('warning', 'Weekend (Friday/Saturday) — meeting create করা যাবে না.');
        return;
      }
      openCreateModal(info.dateStr);
    },

    eventClick: function(info){
      currentEvent = info.event;
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

  function fetchMeetings(){
    return $.get(`${API_BASE}/meetings-details?include=true`)
      .then(res => {
        let payload = res;
        if (payload && typeof payload === 'object' && Array.isArray(payload.data)) {
          payload = payload.data;
        } else if (payload && typeof payload === 'object' && payload.data && Array.isArray(payload.data.data)) {
          payload = payload.data.data;
        }
        if (!Array.isArray(payload)) payload = [];
        const clean = payload.filter(it => {
          const d  = it?.date;
          const st = it?.start_time;
          const et = it?.end_time;
          const okDate = typeof d === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(d);
          const okTime = t => typeof t === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(t);
          return okDate && okTime(st || '') && okTime(et || '');
        });
        return clean;
      })
      .catch(() => {
        t('error','Could not fetch meetings');
        return [];
      });
  }

  function loadEventsToCalendar(events){
    calendar.removeAllEvents();
    (events || []).forEach(m => {
      try {
        const date = m.date;
        const st = (m.start_time || '').slice(0,8);
        const et = (m.end_time   || '').slice(0,8);
        const startStr = (date && st) ? `${date}T${st}` : null;
        const endStr   = (date && et) ? `${date}T${et}` : null;
        if (!startStr) return;
        calendar.addEvent({
          id: String(m.id ?? Math.random()),
          title: m.title || (m.meeting?.title || 'Meeting'),
          start: startStr,
          end: endStr,
          extendedProps: m
        });
      } catch (e) {}
    });
  }

  function fetchDepartments(){
    return $.get(`${API_BASE}/departments-data`)
      .then(res => {
        departments = Array.isArray(safeData(res)) ? safeData(res) : [];
        renderDepartments();
        return departments;
      })
      .catch(() => {
        departments = [];
        renderDepartments();
        return [];
      });
  }

  function renderDepartments(){
    const box = $('#departmentsBox').empty();
    $('#dept-all').prop('checked', false);

    if (!departments.length) {
      box.html('<small class="text-muted">No departments found</small>');
      return;
    }

    departments.forEach(d => {
      box.append(`
        <div class="form-check mb-1">
          <input class="form-check-input dept-checkbox" type="checkbox" value="${String(d.id)}" id="dept-${d.id}">
          <label class="form-check-label" for="dept-${d.id}">${escapeHtml(d.name || d.title || '')}</label>
        </div>
      `);
    });
  }

  function fetchDepartmentUsers(depId){
    const date  = $('#date').val();
    const start = $('#start_time').val();
    const end   = $('#end_time').val();
    if (!date || !start || !end) {
      t('error', 'Select date, start & end first.');
      return Promise.resolve([]);
    }
    return $.get(`${API_BASE}/departments/${depId}/users-availability`, {
        date,
        start_time: start,
        end_time: end,
        meeting_id: currentEditingMeetingId || ''
      })
      .then(res => {
        const data = safeData(res);
        const arr = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
        departmentUsers[depId] = arr;
        return arr;
      })
      .catch(() => {
        return $.get(`${API_BASE}/notices/department-users/${depId}`, {
            date,
            start_time: start,
            end_time: end,
            meeting_id: currentEditingMeetingId || ''
          })
          .then(res => {
            const data = safeData(res);
            const arr = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
            departmentUsers[depId] = arr;
            return arr;
          })
          .catch(() => {
            departmentUsers[depId] = [];
            return [];
          });
      });
  }
 
  function loadUsersForCheckedDepartments(){
    const checkedIds = $('.dept-checkbox:checked').map(function(){ return $(this).val(); }).get();

    if (!checkedIds.length) {
      departmentUsers = {};
      renderUsersBox();
      $('#dept-all').prop('checked', false);
      return;
    }

    const allChecked = checkedIds.length === departments.length;
    $('#dept-all').prop('checked', allChecked);

    const jobs = checkedIds.map(id => fetchDepartmentUsers(id));
    Promise.all(jobs).then(() => {
      renderUsersBox();
    });
  }

  // ------------ CORE FIX: renderUsersBox ------------
  function renderUsersBox(){
    const box = $('#usersBox').empty();
    const users = Object.values(departmentUsers).flat();
    if (!users.length) {
      box.html('<small class="text-muted">No users for selected departments</small>');
      return;
    }

    const map = new Map();
    users.forEach(u => {
      if (u && typeof u.id !== 'undefined') map.set(String(u.id), u);
    });
    const list = [...map.values()];

    list.forEach(u => {
      const uid = String(u.id);

      const isAlreadySelected = finalSelectedUsers.some(x => Number(x.id) === Number(u.id));

      // user is from this meeting according to API
      const apiSaysSameMeeting =
        currentEditingMeetingId &&
        String(u.meeting_id || u.busy_meeting_id || u.current_meeting_id || '') === String(currentEditingMeetingId);

      // user we removed in this edit — we want him selectable
      const isFreed = freedUsersFromCurrent.has(Number(u.id));

      // final rule: if he is in this meeting OR already selected OR freed => force selectable
      const isForceSelectable = isAlreadySelected || apiSaysSameMeeting || isFreed;

      // only truly busy if API says busy AND not force-selectable
      const actuallyBusy = (u.is_busy === true) && !isForceSelectable;

      const disabled = actuallyBusy ? 'disabled' : '';
      const isChecked = isAlreadySelected;

      const badgeHtml = actuallyBusy
        ? `<span class="badge-busy ms-2" title="${escapeHtml(u.busy_msg || 'Busy')}">Busy</span>`
        : (apiSaysSameMeeting && !isFreed ? '<span class="badge bg-info ms-2">This meeting</span>' : '');

      const lblMuted = actuallyBusy ? 'style="opacity:.6;"' : '';

      const item = $(`
        <div class="form-check">
          <input class="form-check-input user-checkbox"
                type="checkbox"
                id="user-${uid}"
                data-id="${uid}"
                ${isChecked ? 'checked' : ''}
                ${disabled}>
          <label class="form-check-label" for="user-${uid}" ${lblMuted}>
            ${escapeHtml(u.name || 'User')}
            <small class="text-muted">(${escapeHtml(u.email || '')})</small>
            ${badgeHtml}
          </label>
        </div>
      `);

      box.append(item);
    });
  }
  // ---------------------------------------------------

  // ------------ Select all (skip busy) ------------
  $('#selectAllNonBusy').on('change', function(){
    const check = this.checked;
    const all = Object.values(departmentUsers).flat();
    const map = new Map();
    all.forEach(u => { if(u && typeof u.id !== 'undefined') map.set(String(u.id), u); });
    const list = [...map.values()];

    if (check) {
      list.forEach(u => {
        const isAlreadySelected = finalSelectedUsers.some(x => Number(x.id) === Number(u.id));
        const apiSaysSameMeeting =
          currentEditingMeetingId &&
          String(u.meeting_id || u.busy_meeting_id || u.current_meeting_id || '') === String(currentEditingMeetingId);
        const isFreed = freedUsersFromCurrent.has(Number(u.id));
        const isForceSelectable = isAlreadySelected || apiSaysSameMeeting || isFreed;

        // if busy but NOT force-selectable -> skip
        if (u.is_busy && !isForceSelectable) return;

        if (!isAlreadySelected) {
          finalSelectedUsers.push({
            id: Number(u.id),
            name: u.name || 'User',
            email: u.email || ''
          });
        }
        if (!selectedUsers.includes(Number(u.id))) {
          selectedUsers.push(Number(u.id));
        }
      });
    } else {
      // unselect all we loaded
      list.forEach(u => {
        finalSelectedUsers = finalSelectedUsers.filter(x => Number(x.id) !== Number(u.id));
        selectedUsers = selectedUsers.filter(x => Number(x) !== Number(u.id));
      });
    }

    refreshSelectedPreview();
    renderUsersBox();
  });
  // ---------------------------------------------------

  function refreshSelectedPreview(){
    const merged = [...finalSelectedUsers, ...externalUsers];
    $('#selectedCount').text(merged.length);
    const box = $('#selectedPreview').empty();
    if (!merged.length) {
      box.html('<small class="text-muted">No recipients selected</small>');
      return;
    }
    merged.forEach(u => {
      const isInternal = typeof u.id !== 'undefined' && u.id !== null;
      const attr = isInternal ? `data-id="${u.id}"` : `data-email="${u.email}"`;
      box.append(`
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <div>
            <div><strong>${escapeHtml(u.name)}</strong> <small class="text-muted ms-2">${isInternal ? 'internal' : 'external'}</small></div>
            <div class="small">${escapeHtml(u.email || '')}</div>
          </div>
          <div><button class="btn btn-sm btn-outline-danger btn-remove" ${attr}>Remove</button></div>
        </div>
      `);
    });
  }

  function addExternalUser(name, email){
    if (!name || !email) { t('error','Name & email required'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { t('error','Invalid email'); return; }
    if (externalUsers.some(u => u.email.toLowerCase() === email.toLowerCase())) { t('warning','External user already added'); return; }
    externalUsers.push({ name, email });
    $('#ext_name').val(''); $('#ext_email').val('');
    refreshSelectedPreview();
  }

  $('#attachments').on('change', function(){
    const files = Array.from(this.files || []);
    const pv = $('#attachmentsPreview').empty();
    files.forEach((f,idx) => {
      pv.append(`<span class="file-chip" data-idx="${idx}"><span>${escapeHtml(f.name)}</span><button type="button" class="btn-file-remove">&times;</button></span>`);
    });
  });
  $('#attachmentsPreview').on('click', '.btn-file-remove', function(){
    $(this).closest('.file-chip').remove();
  });

  $(document).on('change', '.dept-checkbox', function(){
    loadUsersForCheckedDepartments();
  });

  $('#dept-all').on('change', function(){
    const checked = $(this).is(':checked');
    $('.dept-checkbox').prop('checked', checked);
    if (checked) {
      loadUsersForCheckedDepartments();
    } else {
      departmentUsers = {};
      renderUsersBox();
    }
  });

  // ------------ user checkbox ------------
  $(document).on('change', '.user-checkbox', function(){
    const id = Number($(this).data('id'));
    let found = null;
    Object.values(departmentUsers).flat().forEach(u => { if (u && Number(u.id) === id) found = u; });
    if (!found) return;

    const isAlreadySelected = finalSelectedUsers.some(x => Number(x.id) === id);

    const apiSaysSameMeeting =
      currentEditingMeetingId &&
      String(found.meeting_id || found.busy_meeting_id || found.current_meeting_id || '') === String(currentEditingMeetingId);

    const isFreed = freedUsersFromCurrent.has(id);

    const isForceSelectable = isAlreadySelected || apiSaysSameMeeting || isFreed;

    // block only truly busy
    if (found.is_busy && !isForceSelectable) {
      $(this).prop('checked', false);
      return;
    }

    const idx = finalSelectedUsers.findIndex(u => Number(u.id) === id);
    if (idx >= 0) {
      finalSelectedUsers.splice(idx,1);
      selectedUsers = selectedUsers.filter(x => Number(x) !== id);
    } else {
      finalSelectedUsers.push({ id, name: found.name || 'User', email: found.email || '' });
      selectedUsers.push(id);
    }
    refreshSelectedPreview();
  });
  // --------------------------------------

  // ------------ remove chip ------------
  $(document).on('click', '.btn-remove', function(){
    const id = $(this).data('id');
    const email = $(this).data('email');
    if (typeof id !== 'undefined') {
      finalSelectedUsers = finalSelectedUsers.filter(u => Number(u.id) !== Number(id));
      selectedUsers = selectedUsers.filter(x => Number(x) !== Number(id));

      // very important: if we are editing a meeting, user is now "freed"
      if (currentEditingMeetingId) {
        freedUsersFromCurrent.add(Number(id));
      }
    } else if (email) {
      externalUsers = externalUsers.filter(u => u.email !== email);
    }
    refreshSelectedPreview();
    renderUsersBox();
  });
  // -------------------------------------

  $('#btn-toggle-external').on('click', function(){ $('#externalForm').toggle(); });
  $('#btn-add-external').on('click', function(){
    addExternalUser(($('#ext_name').val() || '').trim(), ($('#ext_email').val() || '').trim());
  });

  function fetchRooms(date, start, end){
    if (!date || !start || !end) {
      $('#roomInfo').text('Enter start & end to load rooms');
      return Promise.resolve([]);
    }
    $('#roomInfo').text('Loading rooms...');
    return $.get(`${API_BASE}/v1/meetings/free`, {
        date,
        start_time: start,
        end_time: end,
        meeting_id: currentEditingMeetingId || ''
      })
      .then(res => {
        const data = Array.isArray(safeData(res)) ? safeData(res) : (res.data || []);
        const sel = $('#room_id').empty().append('<option value="">Select room</option>');
        (data || []).forEach(r => sel.append(`<option value="${String(r.id)}">${escapeHtml(r.title || r.name || '')} (cap: ${r.capacity || 'N/A'})</option>`));
        $('#roomInfo').text('');
        return data;
      })
      .catch(() => {
        $('#roomInfo').text('Could not fetch rooms');
        return [];
      });
  }

  $('#start_time').on('change', function(){
    const start = $(this).val();
    const date  = $('#date').val();
    if (!userChangedEndTime && start) {
      $('#end_time').val(addMinutesToTime(start, 60));
    }
    const end = $('#end_time').val();
    if (date && start && end) {
      fetchRooms(date, start, end);
    }
  });

  $('#end_time').on('change', function(){
    userChangedEndTime = true;
    const date  = $('#date').val();
    const start = $('#start_time').val();
    const end   = $('#end_time').val();
    if (date && start && end) {
      fetchRooms(date, start, end);
    }
  });

  $('#date').on('change', function(){
    const date  = $('#date').val();
    const start = $('#start_time').val();
    const end   = $('#end_time').val();
    if (date && start && end) {
      fetchRooms(date, start, end);
    }
  });

  function formatTime(d){
    if (!d) return '';
    const date = (d instanceof Date) ? d : new Date(d);
    const hh = String(date.getHours()).padStart(2,'0');
    const mm = String(date.getMinutes()).padStart(2,'0');
    return `${hh}:${mm}`;
  }

  function openCreateModal(dateStr){
   // ✅ stop if weekend (Fri/Sat)
    if (dateStr && isBdWeekend(dateStr)) {
      t('warning', 'Weekend (Friday/Saturday) — meeting create করা যাবে না.');
      return;
    }

    currentEvent = null;
    $('#meetingModalTitle').text('Create Meeting');
    if (meetingUpdateNoteEl) meetingUpdateNoteEl.style.display = 'none';
    currentEditingMeetingId = null;
    freedUsersFromCurrent = new Set();
    $('#meetingForm')[0].reset();
    agendaQuill?.setContents?.([]);
    existingAtts = [];
    $('#existingAttachments').empty();
    finalSelectedUsers = [];
    selectedUsers = [];
    externalUsers = [];
    departmentUsers = {};
    $('#room_id').empty().append('<option value="">Select room</option>');
    $('#roomInfo').text('Enter start & end to load rooms');
    $('#btn-delete').hide();
    $('#dept-all').prop('checked', false);

    if (dateStr) {
      $('#date').val(dateStr);
    } else {
      const today = new Date();
      const y = today.getFullYear();
      const m = String(today.getMonth() + 1).padStart(2, '0');
      const d = String(today.getDate()).padStart(2, '0');
      $('#date').val(`${y}-${m}-${d}`);
    }

    const now = new Date();
    const hh  = String(now.getHours()).padStart(2, '0');
    const mm  = String(now.getMinutes()).padStart(2, '0');
    const currentTime = `${hh}:${mm}`;
    $('#start_time').val(currentTime);
    $('#end_time').val(addMinutesToTime(currentTime, 60));
    userChangedEndTime = false;

    fetchRooms($('#date').val(), currentTime, addMinutesToTime(currentTime, 60));

    if (meetingModal) meetingModal.show();
    initChairSelect2();

    // Clear value AFTER init
    $('#meeting_chair_id').val(null).trigger('change');
    fetchDepartments();
    refreshSelectedPreview();
    renderUsersBox();
  }

  function showEventDetails(event){
    const ext = event.extendedProps || {};
    function fmt(dt){
      try {
        const d = (typeof dt === 'string') ? new Date(dt) : dt;
        return d ? d.toLocaleString() : '—';
      } catch(e) { return dt || '—'; }
    }

    $('#detailTitle').text(event.title || ext.title || 'Meeting details');

    const start = event.start || (ext.date && (ext.start_time ? `${ext.date}T${ext.start_time}` : ext.date));
    const end   = event.end   || (ext.date && (ext.end_time   ? `${ext.date}T${ext.end_time}`   : ext.date));
    $('#detail_start').text(fmt(start));
    $('#detail_end').text(fmt(end));

    const roomTitle = (ext.meeting?.title || ext.meetingInfo?.title || ext.room_title || ext.room || '—');
    $('#detail_room').text(roomTitle);
    const capacity = ext.meeting?.capacity || ext.room?.capacity || ext.capacity || '';
    $('#detail_room_meta').text(capacity ? `Capacity: ${capacity}` : 'Capacity: —');

    const agendaHtml = ext.agenda || '';
    $('#detail_agenda').html(agendaHtml || '<div class="text-muted small">No agenda provided</div>');

    const meetingChair = ext.meeting_chair; 
      if (meetingChair && meetingChair.name) {
          $('#detail_chair').text(meetingChair.name);
          $('#detail_chair_email').text(meetingChair.email || '');
      } else {
          $('#detail_chair').text('—');
          $('#detail_chair_email').text('—');
    }

    const atts = Array.isArray(ext.meeting_attachments) ? ext.meeting_attachments : (Array.isArray(ext.attachments) ? ext.attachments : []);
    const $att = $('#detail_attachments').empty();
    if (atts.length) {
      atts.forEach(a => {
        const url  = a.file_path || a.url || a.file_url || '#';
        const name = a.file_name || a.file_name_original || 'file';
        $att.append(`<div><a href="${url}" target="_blank" rel="noopener">${escapeHtml(name)}</a></div>`);
      });
    } else {
      $att.html('<div class="text-muted small">No attachments</div>');
    }

    const props = Array.isArray(ext.propagations) ? ext.propagations : [];
    const box = $('#detail_attendees').empty();
    if (props.length) {
      props.forEach(p => {
        const name  = p.user?.name || p.user_name || p.name || 'N/A';
        const email = p.user?.email || p.user_email || p.email || '';
        const typeBadge = (p.user_id === null)
          ? '<span class="badge bg-dark ms-2">External</span>'
          : '<span class="badge bg-light text-dark ms-2">Internal</span>';
        const read = (typeof p.is_read !== 'undefined')
          ? (p.is_read ? '<span class="text-success small">Read</span>' : '<span class="text-danger small">Unread</span>')
          : '';
        box.append(`
          <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
            <div>
              <div class="fw-medium">${escapeHtml(name)} ${typeBadge}</div>
              <div class="small text-muted">${escapeHtml(email)}</div>
            </div>
            <div class="text-end small">${read}</div>
          </div>
        `);
      });
    } else {
      box.html('<div class="text-muted small">No attendees</div>');
    }
    $('#detail_count').text(props.length);

    $('#detail_edit').off('click').on('click', function(){
      if (eventDetailsModal) eventDetailsModal.hide();
      openEditModalFromEvent(event);
    });

    $('#detail_delete').off('click').on('click', function(){
      const id = event.id || ext.id;
      if (!id) return;
      Swal.fire({title:'Delete meeting?', text:'This cannot be undone.', icon:'warning', showCancelButton:true})
        .then(r => {
          if (!r.isConfirmed) return;
          $.ajax({ url: `${API_BASE}/meetings-details/${id}`, method: 'DELETE' })
            .done(() => {
              t('success', 'Deleted');
              if (eventDetailsModal) eventDetailsModal.hide();
              fetchMeetings().then(loadEventsToCalendar);
            })
            .fail(() => t('error','Delete failed'));
        });
    });

    $('#detail_export').off('click').on('click', function(){
      const s = (event.start && event.start.toISOString()) || ext.start || '';
      const e = (event.end && event.end.toISOString())   || ext.end   || '';
      const lines = [
        'BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//YourApp//EN','BEGIN:VEVENT',
        `UID:${event.id || Date.now()}@yourapp`,
        `DTSTAMP:${(new Date()).toISOString().replace(/[-:]/g,'').split('.')[0]}Z`,
        s ? `DTSTART:${s.replace(/[-:]/g,'')}` : '',
        e ? `DTEND:${e.replace(/[-:]/g,'')}`   : '',
        `SUMMARY:${(event.title || 'Meeting').replace(/\n/g,' ')}`,
        `DESCRIPTION:${(agendaHtml || '').replace(/\n/g,' ')}`,
        `LOCATION:${roomTitle}`,
        'END:VEVENT','END:VCALENDAR'
      ].filter(Boolean).join('\r\n');
      const blob = new Blob([lines], { type:'text/calendar;charset=utf-8' });
      const url  = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${(event.title || 'meeting')}.ics`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    });

    if (eventDetailsModal) eventDetailsModal.show();
  }

  function openEditModalFromEvent(eventObj){
    const m = eventObj.extendedProps || {};

    currentEditingMeetingId = eventObj.id ? Number(eventObj.id) : null;
    freedUsersFromCurrent = new Set(); // reset per edit

    $('#meetingModalTitle').text('Update Meeting');
    if (meetingUpdateNoteEl) meetingUpdateNoteEl.style.display = 'block';
    $('#form_meeting_record_id').val(eventObj.id);
    $('#title').val(eventObj.title || (m.title || ''));
    $('#date').val(m.date || (eventObj.startStr ? eventObj.startStr.split('T')[0] : ''));
    $('#start_time').val(m.start_time || (eventObj.start ? formatTime(eventObj.start) : ''));
    $('#end_time').val(m.end_time || (eventObj.end ? formatTime(eventObj.end) : ''));

    agendaQuill.root.innerHTML = m.agenda || '';

    const atts = Array.isArray(m.meeting_attachments) ? m.meeting_attachments : (Array.isArray(m.attachments) ? m.attachments : []);
    existingAtts = atts;
    const $ex = $('#existingAttachments').empty();
    if (atts.length) {
      $ex.append('<div class="mb-1"><small class="text-muted">Existing attachments</small></div>');
      atts.forEach(a => {
        const href = a.file_path || a.url || a.file_url || '#';
        const label = a.file_name || a.file_name_original || 'file';
        $ex.append(`<div><a target="_blank" href="${href}">${escapeHtml(label)}</a></div>`);
      });
    }

    finalSelectedUsers = [];
    selectedUsers = [];
    externalUsers = [];
    if (Array.isArray(m.propagations)) {
      m.propagations.forEach(p => {
        if (p.user_id) {
          const uid = Number(p.user_id);
          finalSelectedUsers.push({
            id: uid,
            name: (p.user && p.user.name) || p.user_name || 'User',
            email: (p.user && p.user.email) || p.user_email || ''
          });
          selectedUsers.push(uid);
        } else {
          externalUsers.push({
            name: p.user_name || p.name || '',
            email: p.user_email || p.email || ''
          });
        }
      });
    }
    refreshSelectedPreview();

    $('#btn-delete').show();
    if (meetingModal) meetingModal.show();
    
    initChairSelect2();
    
    const chairId = m.meeting_chair_id || null;
    

    setTimeout(() => {
      if (chairId) {

        $.get(`${API_BASE}/users/select2`, { id: chairId })
          .then(function(resp) {
            const item = resp?.results?.[0];
            if (item) {
              // Create a new option and append it
              const option = new Option(item.text, item.id, true, true);
              $('#meeting_chair_id').append(option).trigger('change');
            } else {

              $('#meeting_chair_id').val(chairId).trigger('change');
            }
          })
          .fail(function() {
            // If the AJAX fails, still try to set the value
            $('#meeting_chair_id').val(chairId).trigger('change');
          });
      } else {
        $('#meeting_chair_id').val(null).trigger('change');
      }
    }, 100);

    const currentRoomId = m.meeting?.id || m.meeting_id || m.room_id || null;
    const currentRoomTitle = m.meeting?.title || m.room_title || '';

    fetchDepartments().then(() => {
      fetchRooms($('#date').val(), $('#start_time').val(), $('#end_time').val())
        .then(() => {
          if (currentRoomId) {
            if ($('#room_id option[value="'+String(currentRoomId)+'"]').length === 0) {
              const t = currentRoomTitle || ('Room #'+String(currentRoomId));
              $('#room_id').append(`<option value="${String(currentRoomId)}">${escapeHtml(t)} (assigned)</option>`);
            }
            $('#room_id').val(String(currentRoomId));
          } else {
            $('#room_id').val('');
          }
        })
        .finally(() => {
          renderUsersBox();
          refreshSelectedPreview();
        });
    });

    userChangedEndTime = true;
  }

  function initChairSelect2() {
    const $chair = $('#meeting_chair_id');
    if (!$chair.length) return;

    if ($chair.hasClass('select2-hidden-accessible')) {
      $chair.select2('destroy');
    }

    $chair.select2({
      width: '100%',
      placeholder: 'Select meeting chair',
      allowClear: true,
      dropdownParent: $('#meetingModal'),
      ajax: {
        url: `${API_BASE}/users/select2`,
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return { 
            q: params.term || '',
          };
        },
        processResults: function (data) {
          return { results: data.results || [] }; 
        }
      }
    });
  }


  $('#meetingForm').on('submit', function(e){
    e.preventDefault();
    $('#btn-save').prop('disabled', true);
    const dateVal = $('#date').val();
    if (isBdWeekend(dateVal)) {
      t('error', 'Weekend (Friday/Saturday) এ meeting save করা যাবে না.');
      $('#btn-save').prop('disabled', false);
      return;
    }
    
    const recordId = $('#form_meeting_record_id').val();
    const fd = new FormData();
    fd.append('title', $('#title').val() || '');
    fd.append('date', $('#date').val() || '');
    fd.append('start_time', $('#start_time').val() || '');
    fd.append('end_time', $('#end_time').val() || '');
    const roomId = $('#room_id').val();
    if (roomId) fd.append('meeting_id', roomId);
    const agendaHtml = agendaQuill?.root?.innerHTML || '';
    fd.append('agenda', agendaHtml);

    // internal
    (selectedUsers || []).forEach((uid, i) => fd.append(`internal_users[${i}]`, uid));

    // external
    (externalUsers || []).forEach((u, i) => {
      fd.append(`external_users[${i}][name]`, u.name || '');
      fd.append(`external_users[${i}][email]`, u.email || '');
    });

    const chairId = $('#meeting_chair_id').val();
    if (chairId) fd.append('meeting_chair_id', chairId);
    else fd.append('meeting_chair_id', '')

    //force the key to exist
    // if (!externalUsers || externalUsers.length === 0) {
    //   fd.append('external_users', '[]');
    // }

    // files
    const files = $('#attachments')[0]?.files || [];
    Array.from(files).forEach((f, i) => fd.append(`attachments[${i}]`, f));

    const url = recordId
      ? `${API_BASE}/meetings-details/${recordId}?include=true`
      : `${API_BASE}/meetings-details?include=true`;
    if (recordId) fd.append('_method','PUT');

    $.ajax({
      url,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json'
    })
    .done(res => {
      const ok = res?.success === true || res?.status === 'success' || !!res?.data;
      if (ok) {
        t('success', res.message || 'Saved');
        if (meetingModal) meetingModal.hide();
        fetchMeetings().then(loadEventsToCalendar);
      } else {
        t('error', res?.message || 'Failed');
      }
    })
    .fail(xhr => {
      const msg = xhr.responseJSON?.message || xhr.responseText || 'Server error';
      t('error', msg);
    })
    .always(() => $('#btn-save').prop('disabled', false));
  });


  $('#btn-delete').on('click', function(){
    const id = $('#form_meeting_record_id').val();
    if (!id) return;
    Swal.fire({
      title: 'Delete meeting?',
      text: 'This cannot be undone.',
      icon: 'warning',
      showCancelButton: true
    }).then(r => {
      if (!r.isConfirmed) return;
      $.ajax({ url: `${API_BASE}/meetings-details/${id}`, method: 'DELETE' })
        .done(() => {
          t('success', 'Deleted');
          if (meetingModal) meetingModal.hide();
          fetchMeetings().then(loadEventsToCalendar);
        }).fail(() => t('error', 'Delete failed'));
    });
  });

  $('#btn-close-detail').on('click', function(){
    currentEvent = null;
  });

  function reloadAll(){
    Promise.all([fetchDepartments(), fetchMeetings()])
      .then(([_, meetings]) => {
        loadEventsToCalendar(meetings);
      })
      .catch(() => {});
  }
  $('#btn-refresh').on('click', reloadAll);

  // boot
  reloadAll();
});
</script>


@endsection
