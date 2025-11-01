@extends('layouts.master')

@section('content')
<style>
  #noticesTable,
  #noticesTable thead,
  #noticesTable tbody {
    background-color: #ffffff !important;
    color: #000000 !important;
  }
  #noticesTable th {
    background-color: #ffffff !important;
    color: #000000 !important;
    font-weight: 600;
  }
  #noticesTable td { color: #000000 !important; }
  #paginationNav .pagination .page-item .page-link { cursor: pointer; }
</style>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>All Notices</h3>
    <a href="{{ route('notices.create') }}" class="btn btn-primary">Add notice</a>
  </div>

  <div class="row mb-3 g-2">
    <div class="col-md-3">
      <select id="filterStatus" class="form-control">
        <option value="">All notice</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
      </select>
    </div>
    <div class="col-md-2">
      <select id="perPage" class="form-control">
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="20">20</option>
      </select>
    </div>
    <div class="col-md-2">
      <button id="reloadBtn" class="btn btn-outline-secondary">Reload</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped" id="noticesTable">
      <thead>
        <tr>
          <th style="width:70px">Sl</th>
          <th>Title</th>
          <th>Status (Recipients)</th>
          <th style="width:180px">Updated</th>
          <th style="width:230px">Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <nav id="paginationNav" class="mt-3" aria-label="Notices pagination"></nav>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function(){
  const API_BASE = '{{ url('/') }}';
  const token = localStorage.getItem('api_token') || null;

  $.ajaxSetup({
    beforeSend: function(xhr){
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    }
  });

  let currentPage = 1;
  let perPage = Number($('#perPage').val() || 10);

  function escapeHtml(s){
    return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&gt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function showLoading(){
    $('#noticesTable tbody').html('<tr><td colspan="5" class="text-center py-3">Loading...</td></tr>');
    $('#paginationNav').empty();
  }

  function showError(msg){
    $('#noticesTable tbody').html(`<tr><td colspan="5" class="text-center text-danger">${escapeHtml(msg)}</td></tr>`);
    $('#paginationNav').empty();
  }

  function renderTable(items, startIndex){
    const $tbody = $('#noticesTable tbody').empty();
    if(!Array.isArray(items) || items.length === 0){
      $tbody.html('<tr><td colspan="5" class="text-center text-muted">No notices found</td></tr>');
      return;
    }

    items.forEach((it, idx) => {
      const sl = startIndex + idx;
      const updated = it.updated_at ? (new Date(it.updated_at)).toLocaleString() : '-';
      const recipients = (it.propagations && Array.isArray(it.propagations)) ? it.propagations.length : (it.recipients_count ?? 0);
      const statusClass = it.status === 'published' ? 'text-success' : 'text-danger';
      const statusHtml = `<span class="${statusClass}">${escapeHtml(it.status ?? '')}</span>`;

      let actions = '';
      actions += `<a class="btn btn-sm btn-outline-primary me-1" href="/notices/${it.id}">View</a>`;
      if (it.status !== 'published') {
        actions += `<a class="btn btn-sm btn-outline-secondary me-1" href="/notices/${it.id}/edit">Edit</a>`;
      }
      actions += `<button type="button" class="btn btn-sm btn-outline-danger btn-delete-notice" data-id="${it.id}" data-title="${escapeHtml(it.title)}">Delete</button>`;

      const tr = `<tr>
        <td>${sl}</td>
        <td>${escapeHtml(it.title)}</td>
        <td>${statusHtml} (${recipients} recipients)</td>
        <td>${escapeHtml(updated)}</td>
        <td>${actions}</td>
      </tr>`;

      $tbody.append(tr);
    });
  }

  function renderPagination(meta){
    if(!meta) { $('#paginationNav').empty(); return; }

    const last = meta.last_page || 1;
    const current = meta.current_page || currentPage;
    if(last <= 1) { $('#paginationNav').empty(); return; }

    let html = '<ul class="pagination">';
    html += `<li class="page-item ${current === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Previous</a></li>`;

    const maxButtons = 5;
    let start = Math.max(1, current - Math.floor(maxButtons/2));
    let end = Math.min(last, start + maxButtons - 1);
    if(end - start < maxButtons - 1) start = Math.max(1, end - maxButtons + 1);

    for(let p = start; p <= end; p++){
      html += `<li class="page-item ${p === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
    }

    html += `<li class="page-item ${current >= last ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Next</a></li>`;
    html += '</ul>';

    $('#paginationNav').html(html);
  }

  function loadNotices(page = 1){
    currentPage = page;
    perPage = Number($('#perPage').val() || 10);
    const status = $('#filterStatus').val() || undefined;

    showLoading();

    $.ajax({
      url: `${API_BASE}/api/notices`,
      method: 'GET',
      data: { page: currentPage, per_page: perPage, status: status }
    }).done(function(resp){
      let paginator = null;
      let items = [];

      if(resp == null) {
        showError('Empty response');
        return;
      }

      if(resp.data && resp.data.data && Array.isArray(resp.data.data)) {
        paginator = Object.assign({}, resp.data, {
          data: resp.data.data,
          from: resp.data.from,
          last_page: resp.data.last_page,
          current_page: resp.data.current_page
        });
        items = resp.data.data;
      } else if(resp.data && Array.isArray(resp.data)) {
        items = resp.data;
        paginator = { data: items, from: resp.from ?? ((currentPage-1) * perPage + 1), last_page: resp.last_page ?? 1, current_page: resp.current_page ?? currentPage };
      } else if(Array.isArray(resp) && resp.length !== undefined) {
        items = resp;
        paginator = { data: items, from: ((currentPage-1) * perPage + 1), last_page: 1, current_page: currentPage };
      } else {
        items = (resp.data && (resp.data.data || resp.data)) || [];
        if(!Array.isArray(items)) items = Array.isArray(resp) ? resp : [];
        paginator = {
          data: items,
          from: resp.from ?? ((currentPage-1) * perPage + 1),
          last_page: resp.last_page ?? (items.length < perPage ? 1 : currentPage),
          current_page: resp.current_page ?? currentPage
        };
      }

      const startIndex = (paginator.from !== undefined && paginator.from !== null) ? paginator.from : ((paginator.current_page - 1) * perPage + 1);

      renderTable(items, startIndex);
      renderPagination(paginator);

    }).fail(function(xhr){
      console.error('Failed to load notices', xhr);
      const msg = xhr.responseJSON?.message || 'Failed to load notices';
      showError(msg);
    });
  }

  // SweetAlert delete
  $(document).on('click', '.btn-delete-notice', function(){
    const id = $(this).data('id');
    const title = $(this).data('title') || 'this notice';

    Swal.fire({
      title: 'Delete?',
      text: `You are deleting "${title}". This cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `${API_BASE}/api/notices/${id}`,
          method: 'DELETE',
        }).done(function(res){
          Swal.fire({
            icon: 'success',
            title: 'Deleted',
            text: res?.message || 'Notice deleted successfully.',
            timer: 1800,
            showConfirmButton: false
          });
          loadNotices(currentPage);
        }).fail(function(xhr){
          const msg = xhr.responseJSON?.message || 'Failed to delete notice.';
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: msg
          });
        });
      }
    });
  });

  $('#perPage').on('change', function(){ currentPage = 1; loadNotices(1); });
  $('#filterStatus').on('change', function(){ currentPage = 1; loadNotices(1); });
  $('#reloadBtn').on('click', function(){ loadNotices(currentPage); });

  $(document).on('click', '#paginationNav a.page-link', function(e){
    e.preventDefault();
    const p = Number($(this).data('page'));
    if(!p || p < 1) return;
    loadNotices(p);
  });

  loadNotices(1);
});
</script>
@endsection
