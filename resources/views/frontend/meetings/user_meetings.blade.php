@extends('layouts.master')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/main.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<style>
  /* general look */
  #calendar { min-height: 75vh; }
  .side-box { max-height: 32vh; overflow:auto; padding: .5rem; border:1px solid #e3e3e3; border-radius:6px; background:#fafafa; }
  .modal-panel { max-width: 900px; width: 95%; max-height: 90vh; overflow:auto; }
  .meeting-badge { font-size: 12px; border-radius: 6px; padding: 2px 6px; text-transform: uppercase; }
  .meeting-label { font-weight: 600; color:#555; font-size: 14px; }
  .meeting-value { color:#000; font-weight: 500; }

  /* modal header color (optional) */
.modal-header-brand {
  background: linear-gradient(90deg,#0062ff,#0038ff);
  color:#fff;
}
</style>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center bg-gradient">
    <h5 class="mb-0 fw-bold text-primary">📅 My Meeting Schedule</h5>
    <button id="btn-refresh" class="btn btn-sm btn-outline-primary">↻ Refresh</button>
  </div>
  <div class="card-body">
    <div id="calendar"></div>
  </div>
</div>

<!-- Meeting Details Modal -->
<div id="meetingDetailsModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-panel">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-white border-bottom">
        <div>
          <h5 id="detailTitle" class="modal-title fw-bold mb-0">Meeting Details</h5>
          <small class="text-light-50">For your schedule</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body bg-light">
        <div class="row g-4">
          <!-- LEFT: info + agenda + attachments -->
          <div class="col-md-7">
            <div class="p-3 bg-white rounded shadow-sm">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 id="detail_title" class="fw-bold text-dark mb-0">—</h5>
                <span id="meeting_status" class="badge meeting-badge bg-secondary">Upcoming</span>
              </div>

              <div class="mb-2">
                <span class="meeting-label">🕓 Start:</span>
                <span id="detail_start" class="meeting-value"></span>
              </div>
              <div class="mb-2">
                <span class="meeting-label">🕞 End:</span>
                <span id="detail_end" class="meeting-value"></span>
              </div>
              <div class="mb-3">
                <span class="meeting-label">🏢 Room:</span>
                <span id="detail_room" class="meeting-value"></span>
              </div>

              {{-- NEW: Agenda --}}
              <div class="border-top pt-2 mt-3">
                <div class="meeting-label mb-1">📝 Agenda</div>
                <div id="detail_agenda" class="text-muted small" style="max-height:150px; overflow:auto;">
                  No agenda provided.
                </div>
              </div>

              {{-- NEW: Attachments --}}
              <div class="border-top pt-2 mt-3">
                <div class="meeting-label mb-1">📎 Attachments</div>
                <div id="detail_attachments" class="text-muted small">
                  No attachments.
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT: participants -->
          <div class="col-md-5">
            <div class="p-3 bg-white rounded shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="meeting-label">👥 Participants</div>
                <span class="badge bg-info text-dark">Total: <span id="participants_count">0</span></span>
              </div>
              <div id="participants_list" class="side-box"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-white border-top">
        <button id="btn-close-detail" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/locales-all.global.min.js"></script>
<!-- Toastr & SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<!-- Bootstrap JS (needed for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.requiredRoles = ['user'];</script>
<script>
$(function(){
  // -----------------------
  // CONFIG / STATE
  // -----------------------
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
  const token = localStorage.getItem('api_token') || null;

  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){
      if (token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  const detailsModalEl = document.getElementById('meetingDetailsModal');
  const detailsModal = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;

  // -----------------------
  // FullCalendar Setup
  // -----------------------
  const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView: 'dayGridMonth',
    height: 'auto',
    locale: 'en',
    eventDisplay: 'block',
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: true },
    eventClick: function(info){ showMeetingDetails(info.event); },
    events: [],
    eventDidMount: function(info){
      info.el.classList.add('shadow-sm', 'rounded', 'p-1');
      info.el.style.backgroundColor = '#007bff';
      info.el.style.color = '#fff';
    }
  });
  calendar.render();

  // -----------------------
  // Helpers
  // -----------------------
  function showToast(type, msg){ toastr[type](msg); }
  function safeData(res){ return (res && typeof res === 'object' && ('data' in res)) ? res.data : res; }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function formatDateTime(d){ return d ? new Date(d).toLocaleString() : '—'; }

  // -----------------------
  // Fetch Meetings (user)
  // -----------------------
  async function fetchMeetingsForUser(){
    if(!token){
      showToast('warning', 'Please login first.');
      return [];
    }
    try {
      const res = await $.ajax({
        url: `${API_BASE}/api/meeting-details/by-user`,
        method: 'GET',
        dataType: 'json',
        data: { include: true }
      });
      return Array.isArray(safeData(res)) ? safeData(res) : [];
    } catch (err) {
      console.error('fetchMeetingsForUser', err);
      if (err.status === 401) showToast('error', 'Unauthorized. Please login again.');
      else showToast('error', 'Failed to load meetings.');
      return [];
    }
  }

  function loadMeetingsToCalendar(meetings){
    calendar.removeAllEvents();
    (meetings || []).forEach(m => {
      const start = (m.date && m.start_time) ? `${m.date}T${(m.start_time||'').slice(0,8)}` : m.start;
      const end = (m.date && m.end_time) ? `${m.date}T${(m.end_time||'').slice(0,8)}` : m.end;
      calendar.addEvent({
        id: m.id,
        title: m.title || m.meeting?.title || 'Meeting',
        start,
        end,
        extendedProps: m
      });
    });
  }

  async function reloadMeetings(){
    const meetings = await fetchMeetingsForUser();
    loadMeetingsToCalendar(meetings);
  }

  // initial
  reloadMeetings();

  // refresh btn
  $('#btn-refresh').on('click', reloadMeetings);

  // -----------------------
  // Meeting details modal
  // -----------------------
  function showMeetingDetails(event){
    const m = event.extendedProps || {};

    // title & time
    $('#detailTitle').text('Meeting Details');
    $('#detail_title').text(event.title || m.title || 'Untitled Meeting');
    $('#detail_start').text(formatDateTime(event.start || (m.date + ' ' + m.start_time)));
    $('#detail_end').text(formatDateTime(event.end || (m.date + ' ' + m.end_time)));
    $('#detail_room').text(
      m.meeting?.title ||
      m.meetingInfo?.title ||
      m.room_title ||
      'N/A'
    );

    // status
    const now = new Date();
    const start = event.start ? new Date(event.start) : null;
    const $badge = $('#meeting_status');
    if (start && start > now) {
      $badge.removeClass().addClass('badge meeting-badge bg-info').text('Upcoming');
    } else if (start && start < now) {
      $badge.removeClass().addClass('badge meeting-badge bg-success').text('Completed');
    } else {
      $badge.removeClass().addClass('badge meeting-badge bg-secondary').text('Ongoing');
    }

    // -------- AGENDA ----------
    const agendaHtml =
      m.agenda ||
      m.meeting?.agenda ||
      m.meeting?.description || // fallback if API used description
      '<small class="text-muted">No agenda provided.</small>';
    $('#detail_agenda').html(agendaHtml);

    // -------- ATTACHMENTS ----------
    // We check both meeting_attachments and attachments
    const atts = Array.isArray(m.meeting_attachments)
      ? m.meeting_attachments
      : (Array.isArray(m.attachments) ? m.attachments : []);
    const $attBox = $('#detail_attachments').empty();
    if (atts.length) {
      atts.forEach(a => {
        const url = a.file_path || a.file_url || a.url || '#';
        const name = a.file_name || a.file_name_original || a.name || 'attachment';
        $attBox.append(
          `<div><a href="${url}" target="_blank" rel="noopener">${escapeHtml(name)}</a></div>`
        );
      });
    } else {
      $attBox.html('<small class="text-muted">No attachments.</small>');
    }

    // -------- PARTICIPANTS ----------
    const props = m.propagations || m.propagations_data || [];
    const $list = $('#participants_list').empty();
    if (Array.isArray(props) && props.length){
      $('#participants_count').text(props.length);
      props.forEach((p, i) => {
        const name = p.user?.name || p.user_name || p.name || 'Unknown';
        const email = p.user?.email || p.user_email || p.email || '';
        const type = p.user_id
          ? '<span class="badge bg-light text-dark">Internal</span>'
          : '<span class="badge bg-dark">External</span>';
        $list.append(`
          <div class="py-2 border-bottom">
            <div class="fw-bold">${i+1}. ${escapeHtml(name)} ${type}</div>
            <div class="small text-muted">${escapeHtml(email)}</div>
          </div>
        `);
      });
    } else {
      $('#participants_count').text(0);
      $list.html('<small class="text-muted">No participants added.</small>');
    }

    detailsModal.show();
  }

  $('#btn-close-detail').on('click', () => detailsModal.hide());
});
</script>
@endsection
