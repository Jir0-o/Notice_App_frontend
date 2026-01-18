@extends('layouts.master')

@section('content')

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Users</h3>
    <div> <button id="btnShowCreate" class="btn btn-primary">+ Add User</button> </div>
  </div>

  <div class="card">
    <div class="card-body p-3">

      {{-- Filters --}}
      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <label class="form-label">Search</label>
          <input id="f_search" class="form-control" placeholder="Name / Email / Phone">
        </div>

        <div class="col-md-2">
          <label class="form-label">Department</label>
          <select id="f_department" class="form-select">
            <option value="">All</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Designation</label>
          <select id="f_designation" class="form-select">
            <option value="">All</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Role</label>
          <select id="f_role" class="form-select">
            <option value="">All</option>
          </select>
        </div>

        <div class="col-md-2 d-flex align-items-end gap-2">
          <button id="btnReload" class="btn btn-outline-secondary w-50">Reload</button>
          <button id="btnResetFilters" class="btn btn-outline-secondary w-50">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered bg-white text-black w-100" id="usersTable">
          <thead>
            <tr>
              <th style="width:60px">#</th>
              <th style="width:70px">Photo</th>
              <th>Name</th>
              <th>Email</th>
              <th>Department</th>
              <th>Designation</th>
              <th style="width:120px">Signature</th>
              <th>Roles</th>
              <th style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      {{-- modal --}}
      <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <form id="userForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="userModalTitle">Add User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <input type="hidden" id="user_id" name="id">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input name="name" id="user_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" id="user_email" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Password
                    <small class="text-muted">(leave empty when editing)</small>
                  </label>
                  <input type="password" name="password" id="user_password" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input name="phone" id="user_phone" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Department</label>
                  <select id="user_department" name="department_id" class="form-select" required>
                    <option value="">-- Select department --</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Designation</label>
                  <select id="user_designation" name="designation_id" class="form-select" required>
                    <option value="">-- Select designation --</option>
                  </select>
                </div>
                <div class="col-md-12">
                  <label class="form-label">Roles</label>
                  <select id="user_roles" name="roles[]" multiple class="form-select select2" required>
                    <option value="">-- Select user roles --</option>
                  </select>
                </div>

                {{-- signature --}}
                <div class="col-md-12">
                  <label class="form-label">Signature (optional)</label>
                  <input type="file" id="user_signature" name="signature" class="form-control"
                    accept=".png,.jpg,.jpeg,.pdf">
                  <small class="text-muted">PNG/JPG/JPEG/PDF</small>
                  <div id="signaturePreview" class="mt-2"></div>
                </div>

                {{-- profile photo --}}
                <div class="col-md-12">
                  <label class="form-label">Profile photo (optional)</label>
                  <input type="file" id="user_profile_photo" name="profile_photo" class="form-control"
                    accept=".png,.jpg,.jpeg">
                  <small class="text-muted">Square 1:1 recommended.</small>
                  <div id="profilePhotoPreview" class="mt-2"></div>
                </div>

              </div>

              <div id="userFormMsg" class="mt-2"></div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" id="userSaveBtn" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <style>
    #usersTable { background:#fff!important; color:#000!important; }
    #usersTable thead th { background:#fff!important; color:#000!important; font-weight:600; }
    .modal-content { background:#fff; color:#000; }

    /* Select2 looks like BS5 */
    .select2-container .select2-selection--multiple{
      min-height:38px;border:1px solid #ced4da;
    }
  </style>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  {{-- Select2 --}}
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  {{-- DataTables (Bootstrap 5) --}}
  <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

  <script>
  (function(){
    const API_BASE = '{{ rtrim(config("app.url") ?: url("/"), "/") }}/api';
    const BASE_URL  = '{{ url("") }}'.replace(/\/+$/,'');
    const tokenKey = 'api_token';

    toastr.options = { positionClass: 'toast-top-right', timeOut: 2500 };

    function getToken(){ return localStorage.getItem(tokenKey); }
    function authHeaders(){ const t = getToken(); return t ? {Authorization:'Bearer '+t} : {}; }
    function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }

    const modalEl = document.getElementById('userModal');
    const modal = new bootstrap.Modal(modalEl, {backdrop: 'static'});

    let dt = null;

    function buildUrlFromPath(path){
      if (!path) return null;
      return BASE_URL + '/' + String(path).replace(/^\/+/,'');
    }

    // signature preview
    $('#user_signature').on('change', function () {
      const file = this.files && this.files[0];
      const $preview = $('#signaturePreview').empty();
      if (!file) return;
      if (file.type && file.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = e => $preview.html(`<img src="${e.target.result}" style="max-height:120px;border:1px solid #ddd;padding:4px;border-radius:4px;">`);
        r.readAsDataURL(file);
      } else {
        $preview.html('<div class="small text-muted">File selected.</div>');
      }
    });

    // profile photo preview
    $('#user_profile_photo').on('change', function(){
      const file = this.files && this.files[0];
      const $preview = $('#profilePhotoPreview').empty();
      if (!file) return;
      if (file.type && file.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = e => $preview.html(`<img src="${e.target.result}" style="max-height:70px;border-radius:50%;border:1px solid #ddd;padding:2px;">`);
        r.readAsDataURL(file);
      } else {
        $preview.html('<div class="small text-muted">File selected.</div>');
      }
    });

    // select2 init
    function initRolesSelect2() {
      const $roles = $('#user_roles');
      if (!$roles.length) return;
      if ($roles.data('select2')) $roles.select2('destroy');
      $roles.select2({
        width:'100%',
        placeholder:'Select roles',
        allowClear:true,
        dropdownParent:$('#userModal')
      });
    }
    $('#userModal').on('shown.bs.modal', initRolesSelect2);

    // meta fetch (fills modal selects + filter selects)
    function loadMetaLists() {
      const depReq = $.ajax({
        url: API_BASE + '/departments-data',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const list = resp?.data ?? resp ?? [];
        const $sel = $('#user_department').empty().append('<option value="">-- Select department --</option>');
        const $f   = $('#f_department').empty().append('<option value="">All</option>');
        list.forEach(d => {
          $sel.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`);
          $f.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`);
        });
      });

      const desigReq = $.ajax({
        url: API_BASE + '/designations-data',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const list = resp?.data ?? resp ?? [];
        const $sel = $('#user_designation').empty().append('<option value="">-- Select designation --</option>');
        const $f   = $('#f_designation').empty().append('<option value="">All</option>');
        list.forEach(d => {
          $sel.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`);
          $f.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`);
        });
      });

      const rolesReq = $.ajax({
        url: API_BASE + '/roles',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const payload = resp?.data ?? resp;
        const rows = payload?.data ?? payload ?? [];
        const $sel = $('#user_roles').empty();
        const $f   = $('#f_role').empty().append('<option value="">All</option>');
        rows.forEach(r => {
          const val = r.name ?? String(r);
          $sel.append(`<option value="${escapeHtml(val)}">${escapeHtml(val)}</option>`);
          $f.append(`<option value="${escapeHtml(val)}">${escapeHtml(val)}</option>`);
        });
        $sel.trigger('change.select2');
      });

      return $.when(depReq, desigReq, rolesReq);
    }

    function initUsersDataTable(){
      if (dt) dt.destroy();

      dt = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        pageLength: 10,
        lengthMenu: [[5,10,20,50,100],[5,10,20,50,100]],
        order: [[2,'asc']], // Name
        ajax: function(dtReq, callback){
          const length = dtReq.length || 10;
          const start  = dtReq.start || 0;
          const page   = Math.floor(start / length) + 1;

          // map DT order -> backend sort
          let sort_by = 'id';
          let sort_dir = 'desc';
          if (dtReq.order && dtReq.order.length) {
            const col = dtReq.order[0].column;
            sort_dir = (dtReq.order[0].dir === 'asc') ? 'asc' : 'desc';
            if (col === 2) sort_by = 'name';
            else if (col === 3) sort_by = 'email';
            else sort_by = 'id';
          }

          $.ajax({
            url: API_BASE + '/users',
            method: 'GET',
            data: {
              per_page: length,
              page: page,

              // filters (controller may ignore if not added)
              search_term: $('#f_search').val(),
              department_id: $('#f_department').val(),
              designation_id: $('#f_designation').val(),
              role: $('#f_role').val(),
              sort_by: sort_by,
              sort_dir: sort_dir,
            },
            headers: {Accept:'application/json', ...authHeaders()}
          }).done(resp => {
            const paginator = resp?.data;
            const rows = paginator?.data ?? [];

            callback({
              draw: dtReq.draw,
              recordsTotal: paginator?.total ?? rows.length,
              recordsFiltered: paginator?.total ?? rows.length,
              data: rows
            });
          }).fail(() => {
            callback({ draw: dtReq.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
          });
        },
        columns: [
          { data: null, orderable:false, render: (d,t,row,meta) => meta.row + meta.settings._iDisplayStart + 1 },

          { data: null, orderable:false, render: function(d,t,row){
              const photoUrl = row.profile_photo_url
                ? row.profile_photo_url
                : (row.profile_photo_path ? buildUrlFromPath(row.profile_photo_path) : null);

              return photoUrl
                ? `<img src="${escapeHtml(photoUrl)}" style="width:42px;height:42px;object-fit:cover;border-radius:50%;border:1px solid #ddd;">`
                : `<span class="badge bg-secondary">N/A</span>`;
            }
          },

          { data: 'name', render: d => escapeHtml(d ?? '') },
          { data: 'email', render: d => escapeHtml(d ?? '') },

          { data: null, orderable:false, render: (d,t,row) => escapeHtml(row.department?.name ?? '-') },
          { data: null, orderable:false, render: (d,t,row) => escapeHtml(row.designation?.name ?? '-') },

          { data: null, orderable:false, render: function(d,t,row){
              const sigUrl = row.signature_url
                ? row.signature_url
                : (row.signature_path ? buildUrlFromPath(row.signature_path) : null);

              if (!sigUrl) return `<span class="text-muted">—</span>`;
              const isImg = /\.(png|jpe?g|gif|webp)$/i.test(sigUrl);
              return isImg
                ? `<img src="${escapeHtml(sigUrl)}" style="max-height:32px;">`
                : `<a href="${escapeHtml(sigUrl)}" target="_blank">View</a>`;
            }
          },

          { data: null, orderable:false, render: function(d,t,row){
              const roles = Array.isArray(row.roles) ? row.roles.map(r => r.name ?? r).join(', ') : '';
              return escapeHtml(roles);
            }
          },

          { data: null, orderable:false, render: function(d,t,row){
              return `
                <button class="btn btn-sm btn-info btn-edit" data-id="${row.id}">Edit</button>
                <button class="btn btn-sm btn-danger btn-delete" data-id="${row.id}">Delete</button>
              `;
            }
          },
        ]
      });
    }

    // filters events
    let tmr = null;
    $('#f_search').on('input', function(){
      clearTimeout(tmr);
      tmr = setTimeout(() => dt?.ajax.reload(null, true), 300);
    });
    $('#f_department,#f_designation,#f_role').on('change', () => dt?.ajax.reload(null, true));

    $('#btnReload').on('click', () => dt?.ajax.reload(null, false));
    $('#btnResetFilters').on('click', function(){
      $('#f_search').val('');
      $('#f_department').val('');
      $('#f_designation').val('');
      $('#f_role').val('');
      dt?.ajax.reload(null, true);
    });

    // create
    $('#btnShowCreate').on('click', function(){
      $('#userForm')[0].reset();
      $('#user_id').val('');
      $('#user_password').prop('required', true);
      $('#userModalTitle').text('Add User');
      $('#signaturePreview').empty();
      $('#profilePhotoPreview').empty();
      loadMetaLists().then(() => {
        initRolesSelect2();
        $('#user_roles').val(null).trigger('change.select2');
        modal.show();
      });
    });

    // save
    $('#userForm').on('submit', function(e){
      e.preventDefault();
      const id = $('#user_id').val();

      const fd = new FormData();
      fd.append('name', $('#user_name').val().trim());
      fd.append('email', $('#user_email').val().trim());
      const pw = $('#user_password').val();
      if (pw) fd.append('password', pw);

      fd.append('phone', $('#user_phone').val().trim());
      if ($('#user_department').val()) fd.append('department_id', $('#user_department').val());
      if ($('#user_designation').val()) fd.append('designation_id', $('#user_designation').val());

      const roles = $('#user_roles').val() || [];
      roles.forEach(r => fd.append('roles[]', r));

      const sigFile = $('#user_signature')[0].files[0];
      if (sigFile) fd.append('signature', sigFile);

      const ppFile = $('#user_profile_photo')[0].files[0];
      if (ppFile) fd.append('profile_photo', ppFile);

      if (id) fd.append('_method', 'PUT');

      $('#userSaveBtn').prop('disabled', true).text('Saving...');
      $.ajax({
        url: API_BASE + (id ? '/users/'+id : '/users'),
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        toastr.success(resp?.message || 'Saved');
        modal.hide();
        dt?.ajax.reload(null, false);
      }).fail(xhr => {
        const j = xhr.responseJSON;
        toastr.error(j?.message || 'Error');
      }).always(() => {
        $('#userSaveBtn').prop('disabled', false).text('Save');
      });
    });

    // edit (datatable row buttons)
    $('#usersTable').on('click', '.btn-edit', function(){
      const id = $(this).data('id');

      $.ajax({
        url: API_BASE + '/users/'+id,
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const user = resp?.data ?? resp;

        loadMetaLists().then(() => {
          initRolesSelect2();
          $('#user_id').val(user.id);
          $('#user_name').val(user.name ?? '');
          $('#user_email').val(user.email ?? '');
          $('#user_phone').val(user.phone ?? '');
          $('#user_department').val(user.department_id ?? user.department?.id ?? '');
          $('#user_designation').val(user.designation_id ?? user.designation?.id ?? '');
          $('#user_password').val('').prop('required', false);
          $('#userModalTitle').text('Edit User');

          const rolesArr = (user.roles || []).map(r => (r.name ?? r));
          $('#user_roles').val(rolesArr).trigger('change.select2');

          // signature preview
          const sigUrl = user.signature_url
            ? user.signature_url
            : (user.signature_path ? buildUrlFromPath(user.signature_path) : null);

          if (sigUrl) {
            const isImg = /\.(png|jpe?g|gif|webp)$/i.test(sigUrl);
            $('#signaturePreview').html(
              isImg
                ? `<img src="${escapeHtml(sigUrl)}" style="max-height:120px;border:1px solid #ddd;padding:4px;border-radius:4px;">`
                : `<a href="${escapeHtml(sigUrl)}" target="_blank">View signature</a>`
            );
          } else {
            $('#signaturePreview').empty();
          }

          // photo preview
          const photoUrl = user.profile_photo_url
            ? user.profile_photo_url
            : (user.profile_photo_path ? buildUrlFromPath(user.profile_photo_path) : null);

          if (photoUrl) {
            $('#profilePhotoPreview').html(`<img src="${escapeHtml(photoUrl)}" style="max-height:70px;border-radius:50%;border:1px solid #ddd;padding:2px;">`);
          } else {
            $('#profilePhotoPreview').empty();
          }

          modal.show();
        });
      }).fail(() => toastr.error('Failed to load user'));
    });

    // delete
    $('#usersTable').on('click', '.btn-delete', function(){
      const id = $(this).data('id');
      Swal.fire({title:'Deactivate user?',icon:'warning',showCancelButton:true}).then(res => {
        if (!res.isConfirmed) return;
        $.ajax({
          url: API_BASE+'/users/'+id,
          method:'DELETE',
          headers:{Accept:'application/json', ...authHeaders()}
        }).done(resp => {
          toastr.success(resp?.message || 'User deactivated');
          dt?.ajax.reload(null, false);
        }).fail(xhr => {
          toastr.error(xhr?.responseJSON?.message || 'Failed');
        });
      });
    });

    // boot
    $(function(){
      if (!getToken()) {
        $('#usersTable tbody').html('<tr><td colspan="9" class="text-center p-4">No API token found. Please login.</td></tr>');
        return;
      }
      loadMetaLists().then(() => {
        initUsersDataTable();
      });
    });

  })();
  </script>

</div>
@endsection
