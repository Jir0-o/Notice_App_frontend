@extends('layouts.master')

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

<style>
  .dropzone {
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>

@section('content')
    <div class="container py-4">
      <h3>Add notice</h3>

      <div id="alertBox"></div>

      <div class="card p-3">
        <form id="noticeForm" autocomplete="off">
          @csrf

          <div class="mb-3">
            <label class="form-label">Notice Title</label>
            <input name="title" id="title" placeholder="Enter notice title" class="form-control" required />
            <div class="invalid-feedback d-block" id="error_title"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Notice Description</label>
            <div id="quillEditor" style="height:220px;background:#fff;"></div>
            <input type="hidden" id="descriptionInput" placeholder="Enter notice description" name="description">
            <div class="invalid-feedback d-block" id="error_description"></div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div><strong>Add Recipients</strong></div>
            <div><button type="button" class="btn btn-sm btn-primary" id="openExternalModal">Add External User</button></div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label>Departments (<span id="deptCount">0</span>)</label>
              <div id="departmentsList" class="border p-2" style="height:260px; overflow:auto">Loading...</div>
            </div>

            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center">
                <label class="mb-0">Department Users</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="toggleAllUsers">
                  <label class="form-check-label small" for="toggleAllUsers">Select all</label>
                </div>
              </div>
              <div id="departmentUsers" class="border p-2" style="height:260px; overflow:auto">
                <small class="text-muted">Choose departments first</small>
              </div>
            </div>

            <div class="col-md-4">
              <label>Selected Recipients (<span id="totalSelected">0</span>)</label>
              <div id="selectedPreview" class="border p-2" style="height:260px; overflow:auto">
                <div class="text-muted">No recipients selected</div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Upload files</label>

            <div id="dropArea" class="border dropzone mb-2 text-center">
              <div>
                <div class="fw-semibold mb-1">📁 Drag & drop files here or click to browse</div>
                <small class="text-muted">
                  Allowed: <span class="text-danger fw-semibold">pdf, doc, docx, xlsx, xls, jpg, jpeg, png</span> |
                  Max: 10 MB each (20 files)
                </small>
              </div>
            </div>

            <input
              id="fileInput"
              type="file"
              accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.jpeg,.png"
              multiple
              class="d-none"
            />

            <div id="fileError" class="text-danger small mt-2" role="alert" aria-live="polite"></div>
            <div id="filesPreview" class="mt-3"></div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <button id="saveDraft" type="button" class="btn btn-outline-secondary">Save as Draft</button>
            <button id="publishBtn" type="button" class="btn btn-primary">Publish</button>
          </div>
        </form>
      </div>
    </div>

    {{-- External user modal (Bootstrap 5) --}}
    <div class="modal fade" id="externalModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add External Users</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeExternalModal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Name</label>
                <input id="externalName" class="form-control" placeholder="Name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input id="externalEmail" class="form-control" placeholder="Email" />
                <div class="invalid-feedback d-block" id="externalEmailError"></div>
              </div>
              <div class="col-md-2 text-end">
                <button id="addExternalBtn" class="btn btn-success w-100">Add</button>
              </div>
            </div>
            <hr>
            <div id="externalList" style="max-height:300px; overflow:auto"></div>
          </div>
        </div>
      </div>
    </div>

    {{-- libs --}}
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(function () {
      // ----- CONFIG -----
      const API = '{{ url("/") }}/api';
      const token = localStorage.getItem('api_token') || null;

      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '',
          'X-Requested-With': 'XMLHttpRequest'
        },
        beforeSend: function (xhr) {
          if (token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
        }
      });

      // ----- QUILL -----
      const quill = new Quill('#quillEditor', { theme: 'snow' });

      // ----- STATE -----
      let departments = [];
      let selectedDepartments = [];
      let departmentUsers = {}; // depId -> users[]
      let finalSelectedUsers = []; // [{id,name,email}]
      let externalUsers = [];     // [{name,email}]
      let selectedFiles = [];     // File[]
      const MAX_FILE_MB = 10;
      const MAX_FILES = 20;
      const ALLOWED_EXT = ['pdf','doc','docx','xlsx','xls','jpg','jpeg','png'];

      // ----- HELPERS -----
      function t(msg, type='success'){ toastr[type](msg); }
        function escapeHtml(s){
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }
      function emailValid(e){ return /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(String(e||'').trim()); }
      function extOf(file){ return (file.name.split('.').pop()||'').toLowerCase(); }

      function updateCounts(){
        $('#deptCount').text(selectedDepartments.length);
        $('#totalSelected').text(finalSelectedUsers.length + externalUsers.length);
        renderSelectedPreview();
      }

        function renderSelectedPreview() {
            const $box = $('#selectedPreview').empty();
            const combined = [
                ...finalSelectedUsers.map(u => ({ ...u, __type: 'internal' })),
                ...externalUsers.map(x => ({ ...x, id: null, __type: 'external' }))
            ];
            if (!combined.length) {
                $box.append('<div class="text-muted">No recipients selected</div>');
                return;
            }
            combined.forEach(u => {
                const isInternal = u.__type === 'internal';
                const badge = isInternal
                    ? '<span class="badge bg-success ms-2">Internal</span>'
                    : '<span class="badge bg-secondary ms-2">External</span>';
                const dataAttr = isInternal
                    ? `data-int-id="${u.id}"`
                    : `data-ext-email="${escapeHtml(u.email || '')}"`;

                const row =
                    `<div class="d-flex justify-content-between align-items-center border-bottom py-1">
            <div class="me-2">
            <div class="fw-semibold truncate" title="${escapeHtml(u.name)}">
                ${escapeHtml(u.name)} ${badge}
            </div>
            <div class="small text-muted truncate" title="${escapeHtml(u.email || '')}">
                ${escapeHtml(u.email || '')}
            </div>
            </div>
            <div>
            <button class="btn btn-sm btn-link text-danger remove-recipient" ${dataAttr}>Remove</button>
            </div>
        </div>`;
                $box.append(row);
            });
        }


      function renderExternalList(){
        const $list = $('#externalList').empty();
        if(!externalUsers.length) { $list.append('<div class="text-muted">No external users</div>'); return; }
        externalUsers.forEach((u, idx) => {
          $list.append(
            `<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
              <div>
                <div class="fw-semibold">${escapeHtml(u.name)}</div>
                <div class="small text-muted">${escapeHtml(u.email)}</div>
              </div>
              <button class="btn btn-sm btn-link text-danger remove-external" data-idx="${idx}">Remove</button>
            </div>`
          );
        });
      }

      // ----- FILES -----
      const $fileInput = $('#fileInput');
      const $drop = $('#dropArea');

      function showFileError(msg){
        $('#fileError').text(msg || '');
      }

      function validateAndPush(files){
        let err = '';
        const list = Array.from(files||[]);
        if(selectedFiles.length + list.length > MAX_FILES){
          err = `You can upload up to ${MAX_FILES} files.`;
        }
        for(const f of list){
          const sizeMb = f.size / (1024*1024);
          const ext = extOf(f);
          if(!ALLOWED_EXT.includes(ext)){ err = `File type not allowed: ${f.name}`; break; }
          if(sizeMb > MAX_FILE_MB){ err = `“${f.name}” exceeds ${MAX_FILE_MB} MB.`; break; }
          if(selectedFiles.find(x => x.name === f.name && x.size === f.size)){ err = `Duplicate file skipped: ${f.name}`; }
        }
        if(err){ showFileError(err); return; }
        showFileError('');
        selectedFiles.push(...list);
        renderFiles();
      }

      function renderFiles(){
        const $pv = $('#filesPreview').empty();
        if(!selectedFiles.length){ return; }
        selectedFiles.forEach((f, idx) => {
          $pv.append(
            `<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
              <div class="truncate" style="max-width:70%" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</div>
              <button class="btn btn-sm btn-link text-danger remove-file" data-idx="${idx}">Remove</button>
            </div>`
          );
        });
      }

      $drop.on('click', () => $fileInput.trigger('click'));
      $fileInput.on('change', function(e){ validateAndPush(e.target.files); $(this).val(''); });
      $drop.on('dragover', function(e){ e.preventDefault(); $(this).addClass('border-primary'); });
      $drop.on('dragleave', function(e){ e.preventDefault(); $(this).removeClass('border-primary'); });
      $drop.on('drop', function(e){
        e.preventDefault(); $(this).removeClass('border-primary');
        validateAndPush(e.originalEvent.dataTransfer.files);
      });
      $('#filesPreview').on('click', '.remove-file', function(){
        const idx = Number($(this).data('idx'));
        selectedFiles.splice(idx,1);
        renderFiles();
      });

      // ----- EXTERNAL MODAL -----
      const externalModal = new bootstrap.Modal(document.getElementById('externalModal'));
      $('#openExternalModal').on('click', function(){ renderExternalList(); externalModal.show(); });
      $('#closeExternalModal').on('click', function(){ externalModal.hide(); });

      $('#addExternalBtn').on('click', function(e){
        e.preventDefault();
        const name = $('#externalName').val().trim();
        const email = $('#externalEmail').val().trim();
        if(!name || !email){ $('#externalEmailError').text('Name and Email are required'); return; }
        if(!emailValid(email)){ $('#externalEmailError').text('Invalid email'); return; }
        if(externalUsers.find(x => x.email.toLowerCase() === email.toLowerCase())){ $('#externalEmailError').text('This email is already added'); return; }
        $('#externalEmailError').text('');
        externalUsers.push({ name, email });
        $('#externalName').val(''); $('#externalEmail').val('');
        renderExternalList(); updateCounts();
      });

      $('#externalList').on('click', '.remove-external', function(){
        const i = Number($(this).data('idx'));
        externalUsers.splice(i,1);
        renderExternalList(); updateCounts();
      });

      // preview remove (internal/external)
      $('#selectedPreview').on('click', '.remove-recipient', function(){
        const $row = $(this).closest('.d-flex');
        const intId = $(this).attr('data-int-id');
        const extEmail = $(this).attr('data-ext-email');
        if(intId){
          const id = Number(intId);
          finalSelectedUsers = finalSelectedUsers.filter(u => u.id !== id);
          // uncheck if visible in list
          $('#departmentUsers input[type=checkbox][data-id="'+id+'"]').prop('checked', false);
        } else if(extEmail){
          externalUsers = externalUsers.filter(u => u.email !== extEmail);
          renderExternalList();
        }
        updateCounts();
      });

      // ----- DEPARTMENTS & USERS -----
      function loadDepartments(){
        $('#departmentsList').html('Loading...');
        $.get(`${API}/departments-data`)
          .done(function(res){
            departments = (res && res.data) ? res.data : res;
            const $box = $('#departmentsList').empty();
            if(!Array.isArray(departments) || !departments.length){
              $box.html('<div class="text-muted">No departments found</div>');
              return;
            }
            departments.forEach(d => {
              $box.append(
                `<div class="form-check">
                   <input class="form-check-input dept-checkbox" type="checkbox" value="${d.id}" id="dept_${d.id}">
                   <label class="form-check-label" for="dept_${d.id}">${escapeHtml(d.name || d.title || '')}</label>
                 </div>`
              );
            });
          })
          .fail(() => $('#departmentsList').html('<div class="text-danger">Failed to load departments</div>'));
      }
      loadDepartments();

      // when dept toggled
      $('#departmentsList').on('change', '.dept-checkbox', function(){
        const depId = Number(this.value);
        if(this.checked){
          if(!selectedDepartments.includes(depId)) selectedDepartments.push(depId);
          if(!departmentUsers[depId]){
            $.get(`${API}/notices/department-users/${depId}`)
              .done(function(res){
                departmentUsers[depId] = (res && res.data) ? res.data : res;
                renderDepartmentUsers();
              })
              .fail(function(){
                // fallback to /users list
                $.get(`${API}/users`).done(function(all){
                  const arr = (all && all.data) ? all.data : all;
                  const filtered = (arr||[]).filter(u => String(u.department_id||'') === String(depId)
                    || (u.department && String(u.department.id||u.department.department_id||'') === String(depId)));
                  departmentUsers[depId] = filtered;
                  renderDepartmentUsers();
                }).fail(() => Swal.fire('Error','Failed to load users','error'));
              });
          } else {
            renderDepartmentUsers();
          }
        } else {
          selectedDepartments = selectedDepartments.filter(x => x !== depId);
          renderDepartmentUsers();
        }
        updateCounts();
      });

    function renderDepartmentUsers() {
        const $box = $('#departmentUsers').empty();
        if (!selectedDepartments.length) {
            $box.html('<small class="text-muted">Choose departments to see users</small>');
            $('#toggleAllUsers').prop('checked', false);
            return;
        }
        // flatten unique
        const flat = [];
        const seen = {};
        selectedDepartments.forEach(id => {
            (departmentUsers[id] || []).forEach(u => {
                if (u && u.id != null && !seen[u.id]) {
                    seen[u.id] = true;
                    flat.push(u);
                }
            });
        });
        if (!flat.length) { $box.html('<small class="text-muted">No users for selected departments</small>'); return; }

        const allSelected = flat.every(u => finalSelectedUsers.some(x => x.id === u.id));
        $('#toggleAllUsers').prop('checked', allSelected);

        flat.forEach(u => {
            const checked = finalSelectedUsers.some(x => x.id === u.id) ? 'checked' : '';
            const name = escapeHtml(u.name || 'User');
            const email = escapeHtml(u.email || '');
            $box.append(
                `<label class="form-check d-block">
            <input class="form-check-input me-2 dep-user" type="checkbox" ${checked}
                    data-id="${u.id}" data-name="${name}" data-email="${email}">
            <span class="form-check-label">
            <strong>${name}</strong>
            <small class="text-muted">(${email || 'no email'})</small>
            </span>
        </label>`
            );
        });
    }

      // select all in current view
      $('#toggleAllUsers').on('change', function(){
        const checked = this.checked;
        $('#departmentUsers .dep-user').each(function(){
          $(this).prop('checked', checked);
          const id = Number($(this).data('id'));
          const name = $(this).data('name');
          const email = $(this).data('email');
          if(checked){
            if(!finalSelectedUsers.find(x => x.id === id)){
              finalSelectedUsers.push({ id, name, email });
            }
          } else {
            finalSelectedUsers = finalSelectedUsers.filter(x => x.id !== id);
          }
        });
        updateCounts();
      });

      // single user toggle
      $('#departmentUsers').on('change', '.dep-user', function(){
        const id = Number($(this).data('id'));
        const name = $(this).data('name');
        const email = $(this).data('email');
        if(this.checked){
          if(!finalSelectedUsers.find(x => x.id === id)){
            finalSelectedUsers.push({ id, name, email });
          }
        } else {
          finalSelectedUsers = finalSelectedUsers.filter(x => x.id !== id);
          $('#toggleAllUsers').prop('checked', false);
        }
        updateCounts();
      });

      // ----- SUBMIT -----
      function submitNotice(isDraft){
        $('#alertBox').empty();
        $('#error_title').text('');
        $('#error_description').text('');

        const title = $('#title').val().trim();
        const descriptionHtml = quill.root.innerHTML;

        if(!title){ $('#error_title').text('Title is required'); return; }
        // (optional) ensure not empty editor
        const plain = quill.getText().trim();
        if(!plain){ $('#error_description').text('Description is required'); return; }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('description', descriptionHtml);

        // departments
        selectedDepartments.forEach((depId, i) => fd.append(`departments[${i}]`, depId));

        // internal users
        finalSelectedUsers.forEach((u, i) => fd.append(`internal_users[${i}]`, u.id));

        // external users
        externalUsers.forEach((u, i) => {
          fd.append(`external_users[${i}][name]`, u.name);
          fd.append(`external_users[${i}][email]`, u.email);
        });

        // files
        selectedFiles.forEach((f, i) => fd.append(`attachments[${i}]`, f));

        const url = isDraft ? `${API}/notices/draft` : `${API}/notices`;
        const $btn = isDraft ? $('#saveDraft') : $('#publishBtn');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text(isDraft ? 'Saving...' : 'Publishing...');

        $.ajax({
          url: url,
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false
        })
        .done(function(){
          Swal.fire({
            icon: 'success',
            title: isDraft ? 'Draft saved' : 'Published successfully',
            timer: 1200,
            showConfirmButton: false
          }).then(function(){
            // redirect to route
            window.location.href = '{{ route("notices.index") }}';
            });
          // reset form
          finalSelectedUsers = [];
          externalUsers = [];
          selectedFiles = [];
          selectedDepartments = [];
          departmentUsers = {};

          $('#departmentsList input[type=checkbox]').prop('checked', false);
          $('#departmentUsers').empty().html('<small class="text-muted">Choose departments first</small>');
          $('#title').val('');
          quill.setContents([]);

          $('#filesPreview').empty();
          $('#externalList').empty();
          updateCounts();

          // optional redirect:
          // setTimeout(() => location.href = '/notices', 1200);
        })
        .fail(function(xhr){
          if(xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors){
            const e = xhr.responseJSON.errors;
            if(e.title) $('#error_title').text(e.title[0]);
            if(e.description) $('#error_description').text(e.description[0]);
            if(e.attachments) $('#fileError').text(e.attachments[0]);
          } else {
            const msg = xhr.responseJSON?.message || xhr.responseText || 'Failed to submit notice';
            Swal.fire('Error', escapeHtml(String(msg)), 'error');
          }
        })
        .always(function(){
          $btn.prop('disabled', false).text(originalText);
        });
      }

      $('#saveDraft').on('click', function(){ submitNotice(true); });
      $('#publishBtn').on('click', function(){ submitNotice(false); });
    });
    </script>
@endsection
