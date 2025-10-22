@extends('layouts.master')

@section('content')
    <div class="container-xxl py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Designations</h3>
        <div>
          <button id="btnShowCreate" class="btn btn-primary">+ Add Designation</button>
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
                <table class="table table-striped table-bordered bg-white text-black" id="desigTable">
                    <thead class="table-dark text-black bg-white">
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Name</th>
                            <th>Short Name</th>
                            <th style="width:110px">Active</th>
                            <th style="width:200px">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <div id="desigInfo" class="text-muted"></div>
                <div>
                    <button id="prevPage" class="btn btn-sm btn-outline-primary me-1">Prev</button>
                    <button id="nextPage" class="btn btn-sm btn-outline-primary">Next</button>
                </div>
            </div>

            <!-- Create / Edit Modal -->
            <div class="modal fade" id="desigModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form id="desigForm" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="desigModalTitle">Add Designation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" id="desig_id" name="id">

                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input name="name" id="desig_name" class="form-control" required
                                    placeholder="Enter designation name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Short name</label>
                                <input name="short_name" id="desig_short" class="form-control" required
                                    placeholder="Enter short name (e.g., Engr)">
                            </div>

                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="desig_active" name="is_active" checked>
                                <label class="form-check-label" for="desig_active">Active</label>
                            </div>

                            <div id="desigFormMsg" class="mt-2"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="desigSaveBtn" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Custom Table and Modal Styling -->
            <style>
                #desigTable {
                    background-color: #ffffff !important;
                    color: #000000 !important;
                }

                #desigTable th {
                    background-color: #ffffff !important;
                    color: #000000 !important;
                    font-weight: 600;
                }

                #desigTable td {
                    color: #000000 !important;
                }

                .modal-content {
                    background-color: #fff;
                    color: #000;
                }
            </style>

    {{-- jQuery (if not already in your master) --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    {{-- SweetAlert2 & Toastr --}}
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      (function(){
        // ===== config =====
        const API_BASE = '{{ rtrim(config('app.url'), '/') }}/api';
        const tokenKey = 'api_token'; // change if your login uses different key
        const $tbody = $('#desigTable tbody');
        const $info  = $('#desigInfo');
        const $modal = new bootstrap.Modal(document.getElementById('desigModal'));
        let page = 1, lastPage = 1;

        toastr.options = { "positionClass": "toast-top-right", "timeOut": "2500" };

        function getToken(){ return localStorage.getItem(tokenKey); }
        function authHeaders(){ const t = getToken(); return t ? {'Authorization': 'Bearer ' + t} : {}; }

        function handleAjaxError(xhr) {
          if (xhr.status === 401) { toastr.error('Unauthorized. Please login.'); return; }
          if (xhr.status === 403) { toastr.error('Forbidden. Needs higher role.'); return; }
          try {
            const json = xhr.responseJSON;
            if (json?.errors) {
              const first = Object.values(json.errors)[0][0];
              toastr.error(first); return;
            }
            toastr.error(json?.message || ('Request failed: ' + xhr.status));
          } catch (e) {
            toastr.error('Request failed: ' + xhr.status);
          }
        }

        function renderRow(item, index, start) {
            // Generate serial number based on pagination
            const serial = start + index;
            const name = $('<div>').text(item.name ?? '').html();
            const short = $('<div>').text(item.short_name ?? '').html();
            const active = item.is_active ? 'Yes' : 'No';

            return $(`
            <tr data-id="${item.id}">
            <td>${serial}</td>
            <td>${name}</td>
            <td>${short}</td>
            <td>${active}</td>
            <td>
                <button class="btn btn-sm btn-info btn-edit">Edit</button>
                <button class="btn btn-sm btn-danger btn-delete">Delete</button>
            </td>
            </tr>
        `);
        }

        function loadDesignations(p = 1) {
            page = p;
            const per = parseInt($('#perPage').val(), 10) || 10;
            $tbody.html(`<tr><td colspan="5" class="text-center py-4">Loading...</td></tr>`);

            $.ajax({
                url: API_BASE + '/designations',
                method: 'GET',
                headers: { 'Accept': 'application/json', ...authHeaders() },
                data: { page: page, per_page: per },
            }).done(function (resp) {
                const payload = resp?.data ?? resp;
                const rows = payload?.data ?? payload;
                lastPage = payload?.last_page ?? 1;

                $tbody.empty();
                if (!rows || rows.length === 0) {
                    $tbody.html(`<tr><td colspan="5" class="text-center py-4">No designations found.</td></tr>`);
                    $info.text('');
                    return;
                }

                // Calculate start, to, and total for pagination info
                const start = payload?.from ?? ((page - 1) * per + 1);
                const to = payload?.to ?? (start + rows.length - 1);
                const total = payload?.total ?? rows.length;

                // Render each row with serial number
                rows.forEach((r, i) => $tbody.append(renderRow(r, i, start)));

                $info.text(`Showing ${start} - ${to} of ${total} (page ${page} of ${lastPage})`);
            }).fail(handleAjaxError);
        }


        // Create modal
        $('#btnShowCreate').on('click', function(){
          $('#desigForm')[0].reset();
          $('#desig_id').val('');
          $('#desigModalTitle').text('Add Designation');
          $('#desigFormMsg').text('');
          $('#desig_active').prop('checked', true);
          $modal.show();
        });

        // Save (create/update)
        $('#desigForm').on('submit', function(e){
          e.preventDefault();
          const id = $('#desig_id').val();
          const payload = {
            name: $('#desig_name').val().trim(),
            short_name: $('#desig_short').val().trim(),
            is_active: $('#desig_active').is(':checked') ? 1 : 0
          };
          if (!payload.name || !payload.short_name) {
            toastr.warning('Name and Short name are required');
            return;
          }

          $('#desigSaveBtn').prop('disabled', true).text('Saving...');
          const ajaxOpts = { headers: { 'Accept':'application/json', ...authHeaders() }, data: payload };

          if (!id) {
            ajaxOpts.url = API_BASE + '/designations';
            ajaxOpts.method = 'POST';
          } else {
            ajaxOpts.url = API_BASE + '/designations/' + id;
            ajaxOpts.method = 'PUT';
          }

          $.ajax(ajaxOpts).done(function(resp){
            toastr.success(resp?.message || (id ? 'Updated' : 'Created'));
            $modal.hide();
            loadDesignations(page);
          }).fail(function(xhr){
            handleAjaxError(xhr);
          }).always(function(){
            $('#desigSaveBtn').prop('disabled', false).text('Save');
          });
        });

        // Edit
        $tbody.on('click', '.btn-edit', function(){
          const $tr = $(this).closest('tr');
          const id = $tr.data('id');

          $.ajax({
            url: API_BASE + '/designations/' + id,
            method: 'GET',
            headers: { 'Accept':'application/json', ...authHeaders() },
          }).done(function(resp){
            const item = resp?.data ?? resp;
            $('#desig_id').val(item.id);
            $('#desig_name').val(item.name);
            $('#desig_short').val(item.short_name);
            $('#desig_active').prop('checked', item.is_active ? true : false);
            $('#desigModalTitle').text('Edit Designation');
            $('#desigFormMsg').text('');
            $modal.show();
          }).fail(handleAjaxError);
        });

        // Delete (deactivate)
        $tbody.on('click', '.btn-delete', function(){
          const $tr = $(this).closest('tr');
          const id = $tr.data('id');
          Swal.fire({
            title: 'Deactivate designation?',
            text: 'This will mark the designation as inactive.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, deactivate',
            reverseButtons: true
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: API_BASE + '/designations/' + id,
                method: 'DELETE',
                headers: { 'Accept':'application/json', ...authHeaders() },
              }).done(function(resp){
                toastr.success(resp?.message || 'Deactivated');
                loadDesignations(page);
              }).fail(handleAjaxError);
            }
          });
        });

        // Pagination controls
        $('#prevPage').on('click', function(){ if (page > 1) loadDesignations(page - 1); });
        $('#nextPage').on('click', function(){ if (page < lastPage) loadDesignations(page + 1); });
        $('#btnReload').on('click', function(){ loadDesignations(page); });
        $('#perPage').on('change', function(){ loadDesignations(1); });

        // Boot: check token & load
        $(function(){
          if (!getToken()) {
            $tbody.html('<tr><td colspan="5" class="text-center p-4">No API token found. Please login at <a href="{{ route("ext.login") }}">Login</a>.</td></tr>');
            return;
          }
          loadDesignations(1);
        });

      })();
    </script>
@endsection
