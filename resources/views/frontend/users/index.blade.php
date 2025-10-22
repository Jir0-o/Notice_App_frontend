@extends('layouts.master')

@section('content')
      <div class="container-xxl py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Users</h3>
          <div>
            <button id="btnShowCreate" class="btn btn-primary">+ Add User</button>
          </div>
        </div>

        <div class="card">
          <div class="card-body p-3">
            <div class="row mb-2">
              <div class="col-md-4">
                <label class="form-label">Per page</label>
                <select id="perPage" class="form-select">
                  <option value="5">5</option>
                  <option value="10" selected>10</option>
                  <option value="20">20</option>
                </select>
              </div>
              <div class="col-md-4 offset-md-4 text-end">
                <label class="form-label d-block">&nbsp;</label>
                <button id="btnReload" class="btn btn-outline-secondary">Reload</button>
              </div>
            </div>

              <div class="table-responsive">
                  <table class="table table-striped table-bordered bg-white text-black" id="usersTable">
                      <thead class="table-dark text-black bg-white">
                          <tr>
                              <th style="width:60px">#</th>
                              <th>Name</th>
                              <th>Email</th>
                              <th>Department</th>
                              <th>Designation</th>
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

              <!-- Create / Edit Modal -->
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
                                      <input name="name" id="user_name" class="form-control" required
                                          placeholder="Enter user full name">
                                  </div>

                                  <div class="col-md-6">
                                      <label class="form-label">Email</label>
                                      <input type="email" name="email" id="user_email" class="form-control" required
                                          placeholder="Enter user email (e.g., john@domain.com)">
                                  </div>

                                  <div class="col-md-6">
                                      <label class="form-label">Password
                                          <small class="text-muted">(leave empty when editing)</small>
                                      </label>
                                      <input type="password" name="password" id="user_password" class="form-control"
                                          placeholder="Enter password (only for new user)">
                                  </div>

                                  <div class="col-md-6">
                                      <label class="form-label">Phone</label>
                                      <input name="phone" id="user_phone" class="form-control"
                                          placeholder="Enter phone number (optional)">
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

                                <div class="col-md-12">
                                  <label class="form-label">Signature (optional)</label>
                                  <input type="file" id="user_signature" name="signature" class="form-control" accept=".png,.jpg,.jpeg,">
                                  <small class="text-muted">Upload PNG, JPG or JPEG file (max. 5MB)</small>

                                  <!-- preview area -->
                                  <div id="signaturePreview" class="mt-2"></div>
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

              <!-- Custom Table & Modal Styling -->
              <style>
                  #usersTable {
                      background-color: #ffffff !important;
                      color: #000000 !important;
                  }

                  #usersTable th {
                      background-color: #ffffff !important;
                      color: #000000 !important;
                      font-weight: 600;
                  }

                  #usersTable td {
                      color: #000000 !important;
                  }

                  .modal-content {
                      background-color: #fff;
                      color: #000;
                  }
              </style>
  <!-- ONE jQuery only -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <!-- Toastr & SweetAlert2 (optional) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>

  <!-- Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    /* keep Select2 dropdown above the modal */
    .select2-container .select2-dropdown { z-index: 2000; }
  </style>

  <script>
  (function(){

    // ------- Config -------
    const API_BASE = '{{ rtrim(config("app.url") ?: url("/"), "/") }}/api';
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

    // -- Signature preview: show local preview on file select
    $('#user_signature').on('change', function () {
      const file = this.files && this.files[0];
      const $preview = $('#signaturePreview');
      $preview.empty();

      if (!file) return;

      if (file.type && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (ev) {
          $preview.html(`<img src="${ev.target.result}" alt="signature" style="max-height:120px; border:1px solid #ddd; padding:4px; border-radius:4px;">`);
        };
        reader.readAsDataURL(file);
      } else {
        // non-image (pdf etc.) — show filename
        $preview.html(`<div class="small text-muted">File selected: ${escapeHtml(file.name)}</div>`);
      }
    });

    // Clear preview when creating new user / resetting form
    $('#btnShowCreate').on('click', function () {
      // reset native form fields
      $('#userForm')[0].reset();
      $('#user_id').val('');
      $('#user_password').prop('required', true);
      $('#userModalTitle').text('Add User');
      $('#userFormMsg').text('');
      $('#user_department').val('');
      $('#user_designation').val('');
      // clear file + preview
      $('#user_signature').val('');
      $('#signaturePreview').empty();

      // Ensure select2 is initialized and then clear selection.
      // If select2 already exists, clearing immediately may not update UI due to timing,
      // so we do: destroy-if-needed -> init -> clear -> trigger change.
      const $roles = $('#user_roles');

      // destroy old instance if exists (safe)
      if ($roles.data('select2')) {
        $roles.select2('destroy');
      }

      // init select2 fresh (ensures proper DOM placement in modal)
      $roles.select2({
        width: '100%',
        placeholder: 'Select roles',
        allowClear: true,
        dropdownParent: $('#userModal')
      });

      // clear value reliably
      setTimeout(() => {
        $roles.val(null).trigger('change.select2'); // null is more reliable than []
      }, 0);

      // show modal after reset & select2 cleared
      modal.show();
    });

    // ------- Select2 init (safe) -------
    function initRolesSelect2() {
      const $roles = $('#user_roles');
      if (!$roles.length) return;
      if (!$.fn.select2) { console.error('Select2 not loaded'); return; }
      if ($roles.data('select2')) $roles.select2('destroy');
      $roles.select2({
        width: '100%',
        placeholder: 'Select roles',
        allowClear: true,
        dropdownParent: $('#userModal')
      });
    }
    // ensure it’s correct when modal is visible
    $('#userModal').on('shown.bs.modal', initRolesSelect2);

    // ------- Error handler -------
    function handleAjaxError(xhr) {
      if (xhr.status === 401) { toastr.error('Unauthorized. Login required.'); return; }
      if (xhr.status === 403) { toastr.error('Forbidden.'); return; }
      const json = xhr.responseJSON;
      if (json?.errors) {
        const first = Object.values(json.errors)[0][0];
        toastr.error(first); return;
      }
      toastr.error(json?.message || ('Request failed: ' + xhr.status));
    }

    function rolesToString(roles) {
      if (!roles) return '';
      return Array.isArray(roles) ? roles.map(r => r.name ?? r).join(', ') : (roles.name ?? String(roles));
    }

    function renderRow(user, index, start) {
      const serial = start + index;
      const id = user.id;
      const dept = user.department?.name ?? '-';
      const desig = user.designation?.name ?? '-';
      const roles = rolesToString(user.roles);
      const name = escapeHtml(user.name ?? '');
      const email = escapeHtml(user.email ?? '');
      return $(`
        <tr data-id="${id}">
          <td>${serial}</td>
          <td>${name}<div class="small text-muted">${email}</div></td>
          <td>${email}</td>
          <td>${escapeHtml(dept)}</td>
          <td>${escapeHtml(desig)}</td>
          <td>${escapeHtml(roles)}</td>
          <td>
            <button class="btn btn-sm btn-info btn-edit">Edit</button>
            <button class="btn btn-sm btn-danger btn-delete">Delete</button>
          </td>
        </tr>
      `);
    }

    // ------- Meta lists (deps, desigs, roles) -------
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
        // notify select2 that options changed (if already initialized)
        $sel.trigger('change.select2');
      });

      return $.when(depReq, desigReq, rolesReq);
    }

    // ------- Load users -------
    function loadUsers(p = 1) {
      page = p;
      const per = parseInt($('#perPage').val(), 10) || 10;
      $tbody.html('<tr><td colspan="7" class="text-center py-4">Loading...</td></tr>');

      $.ajax({
        url: API_BASE + '/users',
        data: { page, per_page: per },
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const payload = resp?.data ?? resp;
        const rows = payload?.data ?? payload;
        lastPage = payload?.last_page ?? 1;

        $tbody.empty();
        if (!rows || rows.length === 0) {
          $tbody.html('<tr><td colspan="7" class="text-center py-4">No users found.</td></tr>');
          $info.text(''); return;
        }

        const start = payload?.from ?? ((page - 1) * per + 1);
        const to    = payload?.to ?? (start + rows.length - 1);
        const total = payload?.total ?? rows.length;

        rows.forEach((u, i) => $tbody.append(renderRow(u, i, start)));
        $info.text(`Showing ${start} - ${to} of ${total} (page ${page} of ${lastPage})`);
      }).fail(handleAjaxError);
    }

    // ------- Add User -------
    $('#btnShowCreate').on('click', function () {
      // reset form fields
      $('#userForm')[0].reset();
      $('#user_id').val('');
      $('#user_password').prop('required', true);
      $('#userModalTitle').text('Add User');
      $('#userFormMsg').text('');
      $('#user_department').val('');
      $('#user_designation').val('');
      $('#user_signature').val('');

      // load select options, init Select2, then open
      loadMetaLists().then(function() {
        initRolesSelect2();
        $('#user_roles').val(null).trigger('change.select2');
        modal.show();
      });
    });

    // ------- Save (Create/Update) -------
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
      const file = $('#user_signature')[0].files[0];
      if (file) fd.append('signature', file);
      if (id) fd.append('_method', 'PUT');

      $('#userSaveBtn').prop('disabled', true).text('Saving...');

      $.ajax({
        url: API_BASE + (id ? '/users/' + id : '/users'),
        method: 'POST',
        data: fd, processData: false, contentType: false,
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        toastr.success(resp?.message || (id ? 'User updated' : 'User created'));
        modal.hide();
        loadUsers(page);
      }).fail(xhr => {
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
          const first = Object.values(xhr.responseJSON.errors)[0][0];
          $('#userFormMsg').text(first).addClass('text-danger');
          toastr.error(first);
        } else handleAjaxError(xhr);
      }).always(() => $('#userSaveBtn').prop('disabled', false).text('Save'));
    });

    // ------- Edit -------
    $tbody.on('click', '.btn-edit', function(){
      const id = $(this).closest('tr').data('id');

      $.ajax({
        url: API_BASE + '/users/' + id,
        headers: {Accept:'application/json', ...authHeaders()}
      }).done(resp => {
        const user = resp?.data ?? resp;

        // ensure options exist, then init select2, then fill values
        loadMetaLists().then(function () {
          initRolesSelect2();

          $('#user_id').val(user.id);
          $('#user_name').val(user.name ?? '');
          $('#user_email').val(user.email ?? '');
          $('#user_phone').val(user.phone ?? '');
          $('#user_department').val(user.department_id ?? user.department?.id ?? '');
          $('#user_designation').val(user.designation_id ?? user.designation?.id ?? '');
          $('#user_password').val('').prop('required', false);
          $('#userModalTitle').text('Edit User');
          $('#userFormMsg').text('');

          const rolesArr = (user.roles || []).map(r => (r.name ?? r));
          $('#user_roles').val(rolesArr).trigger('change.select2');

          // Use signature_url if API returns it; fallback to signature_path if present.
          const sigUrl = user.signature_url ?? (user.signature_path ? (location.origin + '/' + user.signature_path.replace(/^\/+/,'') ) : null);

          if (sigUrl) {
            // show image preview if image, otherwise show link
            const isImage = /\.(png|jpe?g|gif|webp)$/i.test(sigUrl);
            if (isImage) {
              $('#signaturePreview').html(`<img src="${escapeHtml(sigUrl)}" alt="signature" style="max-height:120px; border:1px solid #ddd; padding:4px; border-radius:4px;">`);
            } else {
              $('#signaturePreview').html(`<div class="small"><a href="${escapeHtml(sigUrl)}" target="_blank">Existing signature (open)</a></div>`);
            }
          } else {
            $('#signaturePreview').empty();
          }

          modal.show();
        });
      }).fail(handleAjaxError);
    });

    // ------- Delete -------
    $tbody.on('click', '.btn-delete', function(){
      const id = $(this).closest('tr').data('id');
      Swal.fire({
        title: 'Deactivate user?',
        text: 'This will mark the user as inactive.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        reverseButtons: true
      }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
          url: API_BASE + '/users/' + id,
          method: 'DELETE',
          headers: {Accept:'application/json', ...authHeaders()}
        }).done(resp => {
          toastr.success(resp?.message || 'User deactivated');
          loadUsers(page);
        }).fail(handleAjaxError);
      });
    });

    // ------- Pagination & controls -------
    $('#prevPage').on('click', () => { if (page > 1) loadUsers(page - 1); });
    $('#nextPage').on('click', () => { if (page < lastPage) loadUsers(page + 1); });
    $('#btnReload').on('click', () => loadUsers(page));
    $('#perPage').on('change', () => loadUsers(1));

    // ------- Boot -------
    $(function () {
      if (!getToken()) {
        $tbody.html('<tr><td colspan="7" class="text-center p-4">No API token found. Please login.</td></tr>');
        return;
      }
      // load meta lists first, init Select2 once, then load users
      loadMetaLists().then(function () {
        initRolesSelect2();
        loadUsers(1);
      });
    });
  })();
  </script>

@endsection
