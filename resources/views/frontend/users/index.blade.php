  @extends('layouts.master')

@section('content')

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Users</h3>
    <div> <button id="btnShowCreate" class="btn btn-primary">+ Add User</button> </div>
  </div>
  <div class="card">
    <div class="card-body p-3">
      <div class="row mb-2">
        <div class="col-md-4"> <label class="form-label">Per page</label> <select id="perPage" class="form-select">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select> </div>
        <div class="col-md-4 offset-md-4 text-end"> <label class="form-label d-block">&nbsp;</label> <button
            id="btnReload" class="btn btn-outline-secondary">Reload</button> </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-bordered bg-white text-black" id="usersTable">
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

      <div class="d-flex justify-content-between align-items-center mt-2">
        <div id="usersInfo" class="text-muted"></div>
        <div>
          <button id="prevPage" class="btn btn-sm btn-outline-primary me-1">Prev</button>
          <button id="nextPage" class="btn btn-sm btn-outline-primary">Next</button>
        </div>
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
  <style>
    #usersTable { background:#fff!important; color:#000!important; }
    #usersTable thead th {
      background:#fff!important;
      color:#000!important;
      font-weight:600;
    }
    .modal-content { background:#fff; color:#000; }
  </style>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  (function(){
    const API_BASE = '{{ rtrim(config("app.url") ?: url("/"), "/") }}/api';
    const BASE_URL  = '{{ url("") }}'.replace(/\/+$/,''); // for path→url
    const tokenKey = 'api_token';
    const $tbody = $('#usersTable tbody');
    const $info  = $('#usersInfo');
    const modalEl = document.getElementById('userModal');
    const modal = new bootstrap.Modal(modalEl, {backdrop: 'static'});
    let page = 1, lastPage = 1;

    toastr.options = { positionClass: 'toast-top-right', timeOut: 2500 };

    function getToken(){ return localStorage.getItem(tokenKey); }
    function authHeaders(){ const t = getToken(); return t ? {Authorization:'Bearer '+t} : {}; }
    function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }

    // signature preview (create)
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

    // profile photo preview (create)
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

    // meta fetch
    function loadMetaLists() {
      const depReq = $.ajax({
        url: API_BASE + '/departments-data',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const list = resp?.data ?? resp ?? [];
        const $sel = $('#user_department').empty().append('<option value="">-- Select department --</option>');
        list.forEach(d => $sel.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`));
      });

      const desigReq = $.ajax({
        url: API_BASE + '/designations-data',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const list = resp?.data ?? resp ?? [];
        const $sel = $('#user_designation').empty().append('<option value="">-- Select designation --</option>');
        list.forEach(d => $sel.append(`<option value="${d.id}">${escapeHtml(d.name)}</option>`));
      });

      const rolesReq = $.ajax({
        url: API_BASE + '/roles',
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const payload = resp?.data ?? resp;
        const rows = payload?.data ?? payload ?? [];
        const $sel = $('#user_roles').empty();
        rows.forEach(r => {
          const val = r.name ?? String(r);
          $sel.append(`<option value="${escapeHtml(val)}">${escapeHtml(val)}</option>`);
        });
        $sel.trigger('change.select2');
      });

      return $.when(depReq, desigReq, rolesReq);
    }

    // table row
    function renderRow(user, index, start) {
      const serial = start + index;
      const id = user.id;

      // signature: url -> path -> null
      const sigUrl =
        user.signature_url
          ? user.signature_url
          : (user.signature_path ? (BASE_URL + '/' + user.signature_path.replace(/^\/+/,'')) : null);

      // photo: url -> path -> null
      const photoUrl =
        user.profile_photo_url
          ? user.profile_photo_url
          : (user.profile_photo_path ? (BASE_URL + '/' + user.profile_photo_path.replace(/^\/+/,'')) : null);

      const dept = user.department?.name ?? '-';
      const desig = user.designation?.name ?? '-';
      const roles = Array.isArray(user.roles) ? user.roles.map(r => r.name ?? r).join(', ') : '';
      const name = escapeHtml(user.name ?? '');
      const email = escapeHtml(user.email ?? '');

      const photo = photoUrl
        ? `<img src="${escapeHtml(photoUrl)}" style="width:42px;height:42px;object-fit:cover;border-radius:50%;border:1px solid #ddd;">`
        : `<span class="badge bg-secondary">N/A</span>`;

      const sig = sigUrl
        ? (/\.(png|jpe?g|gif|webp)$/i.test(sigUrl)
            ? `<img src="${escapeHtml(sigUrl)}" style="max-height:32px;">`
            : `<a href="${escapeHtml(sigUrl)}" target="_blank">View</a>`)
        : `<span class="text-muted">—</span>`;

      return $(`
        <tr data-id="${id}">
          <td>${serial}</td>
          <td>${photo}</td>
          <td>${name}</td>
          <td>${email}</td>
          <td>${escapeHtml(dept)}</td>
          <td>${escapeHtml(desig)}</td>
          <td>${sig}</td>
          <td>${escapeHtml(roles)}</td>
          <td>
            <button class="btn btn-sm btn-info btn-edit">Edit</button>
            <button class="btn btn-sm btn-danger btn-delete">Delete</button>
          </td>
        </tr>
      `);
    }

    // load users
    function loadUsers(p=1){
      page = p;
      const per = parseInt($('#perPage').val(),10) || 10;
      $tbody.html('<tr><td colspan="9" class="text-center py-4">Loading...</td></tr>');

      $.ajax({
        url: API_BASE + '/users',
        data: {page, per_page: per},
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const payload = resp?.data ?? resp;
        const rows = payload?.data ?? payload;
        lastPage = payload?.last_page ?? 1;

        $tbody.empty();
        if (!rows || rows.length === 0) {
          $tbody.html('<tr><td colspan="9" class="text-center py-4">No users found.</td></tr>');
          $info.text('');
          return;
        }

        const start = payload?.from ?? ((page-1)*per+1);
        const to    = payload?.to ?? (start + rows.length - 1);
        const total = payload?.total ?? rows.length;

        rows.forEach((u,i) => $tbody.append(renderRow(u,i,start)));
        $info.text(`Showing ${start} - ${to} of ${total} (page ${page} of ${lastPage})`);
      }).fail(() => {
        $tbody.html('<tr><td colspan="9" class="text-center py-4">Failed to load.</td></tr>');
      });
    }

    // create
    $('#btnShowCreate').on('click', function(){
      $('#userForm')[0].reset();
      $('#user_id').val('');
      $('#user_password').prop('required', true);
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
        loadUsers(page);
      }).fail(xhr => {
        const j = xhr.responseJSON;
        toastr.error(j?.message || 'Error');
      }).always(() => {
        $('#userSaveBtn').prop('disabled', false).text('Save');
      });
    });

    // edit
    $tbody.on('click', '.btn-edit', function(){
      const id = $(this).closest('tr').data('id');
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
 
          // signature preview in edit
          const sigUrl = user.signature_url
            ? user.signature_url
            : (user.signature_path ? (BASE_URL + '/' + user.signature_path.replace(/^\/+/,'')) : null);

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

          // photo preview in edit
          const photoUrl = user.profile_photo_url
            ? user.profile_photo_url
            : (user.profile_photo_path ? (BASE_URL + '/' + user.profile_photo_path.replace(/^\/+/,'')) : null);

          if (photoUrl) {
            $('#profilePhotoPreview').html(`<img src="${escapeHtml(photoUrl)}" style="max-height:70px;border-radius:50%;border:1px solid #ddd;padding:2px;">`);
          } else {
            $('#profilePhotoPreview').empty();
          }

          modal.show();
        });
      });
    });

    // delete
    $tbody.on('click','.btn-delete', function(){
      const id = $(this).closest('tr').data('id');
      Swal.fire({title:'Deactivate user?',icon:'warning',showCancelButton:true}).then(res => {
        if (!res.isConfirmed) return;
        $.ajax({
          url: API_BASE+'/users/'+id,
          method:'DELETE',
          headers:{Accept:'application/json', ...authHeaders()}
        }).done(resp => {
          toastr.success(resp?.message || 'User deactivated');
          loadUsers(page);
        });
      });
    });

    // pagination
    $('#prevPage').on('click', () => { if (page>1) loadUsers(page-1); });
    $('#nextPage').on('click', () => { if (page<lastPage) loadUsers(page+1); });
    $('#btnReload').on('click', () => loadUsers(page));
    $('#perPage').on('change', () => loadUsers(1));

    // boot
    $(function(){
      if (!getToken()) {
        $tbody.html('<tr><td colspan="9" class="text-center p-4">No API token found. Please login.</td></tr>');
        return;
      }
      loadMetaLists().then(() => {
        loadUsers(1);
      });
    });

  })();
  </script>

</div>
</div> 
</div> 
@endsection
