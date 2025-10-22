@extends('layouts.master')

<style>
/* force white table + black text */
#archUsersTable {
  background-color: #ffffff !important;
  color: #000000 !important;
}
#archUsersTable th {
  background-color: #ffffff !important;
  color: #000000 !important;
  font-weight: 600;
}
#archUsersTable td {
  color: #000000 !important;
}

/* optional niceties */
#archUsersTable tbody tr:hover { background-color: #f8f9fa; }
#archUsersTable { border-radius: 6px; overflow: hidden; }
</style>

@section('content')
    <div class="container-xxl py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Archived Users</h3>
        <div><button id="btnReload" class="btn btn-outline-secondary">Reload</button></div>
      </div>

      <div class="card">
        <div class="card-body p-3">
          <div class="table-responsive">
            <table class="table table-striped table-bordered" id="archUsersTable">
              <thead class="table-dark">
                <tr>
                  <th style="width:60px">#</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Department</th>
                  <th>Designation</th>
                  <th style="width:160px">Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-2">
            <div id="archUsersInfo" class="text-muted"></div>
            <div>
              <button id="prevPage" class="btn btn-sm btn-outline-primary me-1">Prev</button>
              <button id="nextPage" class="btn btn-sm btn-outline-primary">Next</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
    (function(){
      const API_BASE = '{{ rtrim(config('app.url'), '/') }}/api';
      const tokenKey = 'api_token';
      const $tbody = $('#archUsersTable tbody');
      const $info  = $('#archUsersInfo');
      let page = 1, lastPage = 1;

      // lookup maps (fallback when API doesn't eager-load relations)
      const depMap = {};
      const desigMap = {};

      toastr.options = { "positionClass": "toast-top-right", "timeOut": "2200" };

      function getToken(){ return localStorage.getItem(tokenKey); }
      function authHeaders(){ const t = getToken(); return t ? {'Authorization': 'Bearer ' + t} : {}; }
      function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
      function handleAjaxError(xhr){
        if(xhr.status===401){ toastr.error('Unauthorized.'); return; }
        try{ const j=xhr.responseJSON; toastr.error(j?.message||'Request failed'); }catch(e){ toastr.error('Error'); }
      }

      // preload departments + designations to use as fallback labels
      function preloadLookups() {
        const deps = $.ajax({
          url: API_BASE + '/departments-data',
          method: 'GET',
          headers: { 'Accept':'application/json', ...authHeaders() },
          data: { per_page: 1000 }
        }).done(function(res){
          const rows = res?.data ?? res ?? [];
          rows.forEach(d => { if(d?.id) depMap[d.id] = d.name || d.title || ''; });
        });

        const desigs = $.ajax({
          url: API_BASE + '/designations-data',
          method: 'GET',
          headers: { 'Accept':'application/json', ...authHeaders() },
          data: { per_page: 1000 }
        }).done(function(res){
          const rows = res?.data ?? res ?? [];
          rows.forEach(d => { if(d?.id) desigMap[d.id] = d.name || d.title || ''; });
        });

        return $.when(deps, desigs);
      }

      function renderRow(u, index, start) {
        const serial = start + index;
        const id = u.id;
        const name = $('<div>').text(u.name ?? '').html();
        const email = $('<div>').text(u.email ?? '').html();

        // Prefer nested relation; then plain name fields; then lookup by *_id; else '-'
        const deptName =
          (u.department && u.department.name) ??
          u.department_name ??
          depMap[u.department_id] ??
          '-';

        const desigName =
          (u.designation && u.designation.name) ??
          u.designation_name ??
          desigMap[u.designation_id] ??
          '-';

        return $(`
          <tr data-id="${id}">
            <td>${serial}</td>
            <td>${name}</td>
            <td>${email}</td>
            <td>${escapeHtml(deptName)}</td>
            <td>${escapeHtml(desigName)}</td>
            <td><button class="btn btn-sm btn-success btn-restore">Restore</button></td>
          </tr>
        `);
      }

      function loadArchived(p = 1) {
        page = p;
        const per = 10;
        $tbody.html(`<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>`);

        $.ajax({
          url: API_BASE + '/deactivate-users',
          method: 'GET',
          headers: { 'Accept': 'application/json', ...authHeaders() },
          data: { page: page, per_page: per }
        }).done(function (resp) {
          const payload = resp?.data ?? resp;
          const rows = payload?.data ?? payload ?? [];
          lastPage = payload?.last_page ?? 1;

          $tbody.empty();
          if (!rows.length) {
            $tbody.html(`<tr><td colspan="6" class="text-center py-4">No archived users.</td></tr>`);
            $info.text('');
            return;
          }

          const start = payload?.from ?? ((page - 1) * per + 1);
          const to = payload?.to ?? (start + rows.length - 1);
          const total = payload?.total ?? rows.length;

          rows.forEach((r, i) => $tbody.append(renderRow(r, i, start)));

          $info.text(`Showing ${start} - ${to} of ${total} (page ${page} of ${lastPage})`);
        }).fail(handleAjaxError);
      }

      // Restore
      $tbody.on('click', '.btn-restore', function(){
        const id = $(this).closest('tr').data('id');
        Swal.fire({
          title:'Restore user?',
          text:'This will activate the user.',
          icon:'question',
          showCancelButton:true,
          confirmButtonText:'Yes'
        }).then(res => {
          if(!res.isConfirmed) return;
          $.ajax({
            url: API_BASE + '/active-users/' + id,
            method:'POST',
            headers:{ 'Accept':'application/json', ...authHeaders() }
          }).done(function(resp){
            toastr.success(resp?.message || 'Restored');
            loadArchived(page);
          }).fail(handleAjaxError);
        });
      });

      // Pagination & controls
      $('#prevPage').on('click', ()=>{ if(page>1) loadArchived(page-1); });
      $('#nextPage').on('click', ()=>{ if(page<lastPage) loadArchived(page+1); });
      $('#btnReload').on('click', ()=> loadArchived(page));

      // Boot
      $(function(){
        if(!getToken()){
          $tbody.html('<tr><td colspan="6" class="text-center p-4">No API token. Please login.</td></tr>');
          return;
        }
        // Preload lookup maps first, then list
        preloadLookups().always(() => loadArchived(1));
      });
    })();
    </script>
@endsection
