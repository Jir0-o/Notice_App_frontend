@extends('layouts.master')

@section('content')
<!-- CSS: Bootstrap + Toastr -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Rooms</h3>
    <div>
      <button id="btn-add" class="btn btn-primary">Add Room</button>
    </div>
  </div>

  <div id="alertBox"></div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <label class="me-2">Per page:</label>
          <select id="perPage" class="form-select d-inline-block w-auto">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select>
        </div>
        <div>
          <button id="btn-refresh" class="btn btn-outline-secondary btn-sm">Refresh</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped" id="roomsTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Capacity</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- populated by JS -->
          </tbody>
        </table>
      </div>

      <nav>
        <ul class="pagination" id="pagination"></ul>
      </nav>
    </div>
  </div>
</div>

<!-- Modal (Add / Edit) -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="roomForm" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="roomModalTitle">Add Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="room_id" />

        <div class="mb-3">
          <label class="form-label">Room Title</label>
          <input name="title" id="title" type="text" class="form-control" required />
          <div class="invalid-feedback" id="err_title"></div>
        </div>

        <div class="mb-3">
          <label class="form-label">Capacity</label>
          <input name="capacity" id="capacity" type="number" class="form-control" />
          <div class="invalid-feedback" id="err_capacity"></div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" id="deleteRoomBtn" class="btn btn-danger me-auto" style="display:none;">Delete</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" id="saveRoomBtn" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- JS libs: jQuery, Bootstrap, Toastr -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function(){
  // API base (same approach as your other pages)
  const API = '{{ rtrim(config("app.url") ?: request()->getSchemeAndHttpHost(), "/") }}/api';
  const token = localStorage.getItem('api_token') || null;

  // Ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '',
      'X-Requested-With': 'XMLHttpRequest'
    },
    beforeSend(xhr){
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  // State
  let currentPage = 1;
  let perPage = Number($('#perPage').val() || 10);
  let lastPage = 1;
  const roomModalEl = document.getElementById('roomModal');
  const roomModal = roomModalEl ? new bootstrap.Modal(roomModalEl, { backdrop: 'static' }) : null;

  function showToast(type, msg){ if(window.toastr) toastr[type](msg); else alert(msg); }

  // load rooms
  function loadRooms(page = 1){
    $('#roomsTable tbody').html('<tr><td colspan="5">Loading...</td></tr>');
    $.getJSON(`${API}/meetings?page=${page}&per_page=${perPage}`).done(res=>{
      // API may return wrapped {data:..., last_page:...} or similar
      const body = res.data || res;
      const meta = res;
      const rows = Array.isArray(body) ? body : (body.data || body);
      const data = Array.isArray(rows) ? rows : (res.data?.data || []);

      // try multiple shapes
      const list = Array.isArray(res.data) ? res.data : (Array.isArray(res) ? res : (res.data?.data || res.data || res));
      // Best effort: if response has data & last_page
      const items = res.data && Array.isArray(res.data) ? res.data : (Array.isArray(res) ? res : (res.data?.data || res.data || res));
      // Try to extract proper list & pagination
      let listItems = [];
      if (Array.isArray(res.data)) listItems = res.data;
      else if (Array.isArray(res)) listItems = res;
      else if (res.data && Array.isArray(res.data.data)) listItems = res.data.data;
      else if (res.data && Array.isArray(res.data)) listItems = res.data;
      else if (res.data && res.data.data) listItems = res.data.data;
      else if (Array.isArray(res)) listItems = res;
      else listItems = res.data || res;

      // Fallback if still not array
      if(!Array.isArray(listItems)) listItems = [];

      // pagination meta
      lastPage = res.last_page || (res.data && res.data.last_page) || 1;
      currentPage = page;

      renderTable(listItems);
      renderPagination(lastPage, currentPage);
    }).fail(xhr=>{
      $('#roomsTable tbody').html('<tr><td colspan="5">Failed to load rooms</td></tr>');
      console.error('Load rooms failed', xhr);
      showToast('error', 'Failed to load rooms');
    });
  }

  function renderTable(items){
    const tbody = $('#roomsTable tbody').empty();
    if(!items || items.length === 0){
      tbody.append('<tr><td colspan="5" class="text-center text-muted">No rooms found</td></tr>');
      return;
    }
    items.forEach((r, i) => {
      const idx = ((currentPage - 1) * perPage) + i + 1;
      const status = r.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
      const tr = $(`
        <tr data-id="${r.id}">
          <td>${idx}</td>
          <td>${escapeHtml(r.title)}</td>
          <td>${r.capacity ?? '-'}</td>
          <td>${status}</td>
          <td>
            <button class="btn btn-sm btn-primary btn-edit">Edit</button>
            <button class="btn btn-sm btn-danger btn-delete">Delete</button>
          </td>
        </tr>
      `);
      tbody.append(tr);
    });
  }

  function renderPagination(last, current){
    const ul = $('#pagination').empty();
    last = last || 1;
    current = current || 1;

    // prev
    const prevLi = $(`<li class="page-item ${current===1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current-1}">Previous</a></li>`);
    ul.append(prevLi);

    // simple numeric pages
    const start = Math.max(1, current - 2);
    const end = Math.min(last, start + 4);
    for(let p = start; p <= end; p++){
      const li = $(`<li class="page-item ${p===current ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`);
      ul.append(li);
    }

    const nextLi = $(`<li class="page-item ${current >= last ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current+1}">Next</a></li>`);
    ul.append(nextLi);
  }

  // helpers
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // open modal for add
  $('#btn-add').on('click', function(){
    $('#roomModalTitle').text('Add Room');
    $('#room_id').val('');
    $('#title').val('');
    $('#capacity').val('');
    $('#err_title').text('').hide();
    $('#err_capacity').text('').hide();
    $('#deleteRoomBtn').hide();
    if(roomModal) roomModal.show();
  });

  // refresh
  $('#btn-refresh').on('click', function(){ loadRooms(currentPage); });

  // per page change
  $('#perPage').on('change', function(){
    perPage = Number($(this).val());
    currentPage = 1;
    loadRooms(currentPage);
  });

  // edit click
  $('#roomsTable').on('click', '.btn-edit', function(){
    const tr = $(this).closest('tr');
    const id = tr.data('id');
    // fetch single resource or use already loaded list by calling API single GET if available
    $.getJSON(`${API}/meetings/${id}`).done(res=>{
      const data = res.data || res;
      $('#roomModalTitle').text('Edit Room');
      $('#room_id').val(data.id);
      $('#title').val(data.title);
      $('#capacity').val(data.capacity);
      $('#err_title').text('').hide();
      $('#err_capacity').text('').hide();
      $('#deleteRoomBtn').show();
      if(roomModal) roomModal.show();
    }).fail(xhr=>{
      // fallback: try to use row values
      const title = tr.find('td:eq(1)').text().trim();
      const capacity = tr.find('td:eq(2)').text().trim();
      $('#roomModalTitle').text('Edit Room');
      $('#room_id').val(id);
      $('#title').val(title);
      $('#capacity').val(capacity === '-' ? '' : capacity);
      $('#deleteRoomBtn').show();
      if(roomModal) roomModal.show();
    });
  });

  // delete click (soft toggle) from table
  $('#roomsTable').on('click', '.btn-delete', function(){
    const tr = $(this).closest('tr');
    const id = tr.data('id');
    if(!confirm('Delete (toggle) this room?')) return;
    $.ajax({ url: `${API}/meetings/${id}/toggle`, method: 'PATCH' })
      .done(res=>{
        showToast('success', res.message || 'Toggled successfully');
        loadRooms(currentPage);
      }).fail(xhr=>{
        console.error('Delete failed', xhr);
        showToast('error', 'Delete failed');
      });
  });

  // Delete button inside modal (for editing)
  $('#deleteRoomBtn').on('click', function(){
    const id = $('#room_id').val();
    if(!id) return;
    if(!confirm('Delete (toggle) this room?')) return;
    $.ajax({ url: `${API}/meetings/${id}/toggle`, method: 'PATCH' })
      .done(res=>{
        showToast('success', res.message || 'Toggled successfully');
        if(roomModal) roomModal.hide();
        loadRooms(1);
      }).fail(xhr=>{
        console.error('Delete failed', xhr);
        showToast('error', 'Delete failed');
      });
  });

  // pagination click
  $(document).on('click', '#pagination a.page-link', function(e){
    e.preventDefault();
    const page = Number($(this).data('page'));
    if(!page || page < 1) return;
    loadRooms(page);
  });

  // submit add/edit
  $('#roomForm').on('submit', function(e){
    e.preventDefault();
    $('#err_title').text('').hide();
    $('#err_capacity').text('').hide();
    $('#saveRoomBtn').prop('disabled', true).text('Saving...');
    const id = $('#room_id').val();
    const payload = {
      title: $('#title').val(),
      capacity: $('#capacity').val() || null,
    };

    if(id){
      // update
      $.ajax({
        url: `${API}/meetings/${id}`,
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(payload)
      }).done(res=>{
        showToast('success', res.message || 'Updated successfully');
        if(roomModal) roomModal.hide();
        loadRooms(currentPage);
      }).fail(xhr=>{
        if(xhr.status === 422 && xhr.responseJSON?.errors){
          const errors = xhr.responseJSON.errors;
          if(errors.title) { $('#err_title').text(errors.title[0]).show(); }
          if(errors.capacity) { $('#err_capacity').text(errors.capacity[0]).show(); }
        } else {
          showToast('error', xhr.responseJSON?.message || 'Update failed');
        }
      }).always(()=> $('#saveRoomBtn').prop('disabled', false).text('Save'));
    } else {
      // create
      $.ajax({
        url: `${API}/meetings`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload)
      }).done(res=>{
        showToast('success', res.message || 'Added successfully');
        if(roomModal) roomModal.hide();
        loadRooms(1);
      }).fail(xhr=>{
        if(xhr.status === 422 && xhr.responseJSON?.errors){
          const errors = xhr.responseJSON.errors;
          if(errors.title) { $('#err_title').text(errors.title[0]).show(); }
          if(errors.capacity) { $('#err_capacity').text(errors.capacity[0]).show(); }
        } else {
          showToast('error', xhr.responseJSON?.message || 'Create failed');
        }
      }).always(()=> $('#saveRoomBtn').prop('disabled', false).text('Save'));
    }
  });

  // initial load
  loadRooms(1);
});
</script>
@endsection
