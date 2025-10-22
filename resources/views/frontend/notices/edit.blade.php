@extends('layouts.master')

@section('content')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="container py-4">
    <h3>Update Notice</h3>
    <div id="alertBox"></div>

    <div class="card p-3">
        <form id="updateForm" class="mb-0">
            @csrf
            <div class="mb-3">
                <label>Notice Title</label>
                <input name="title" id="title" class="form-control" required />
                <div class="invalid-feedback" id="error_title"></div>
            </div>

            <div class="mb-3">
                <label>Notice Description</label>
                <div id="quillEditor" style="height:200px;"></div>
                <div class="invalid-feedback d-block" id="error_description"></div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div><strong>Add Recipients</strong></div>
                <div><button type="button" class="btn btn-sm btn-primary" id="openExternalModal">Add External User</button></div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Departments</label>
                    <div id="departmentsList" class="border p-2" style="height:260px; overflow:auto"></div>
                </div>

                <div class="col-md-4">
                    <label>Department Users</label>
                    <div id="departmentUsers" class="border p-2" style="height:260px; overflow:auto"></div>
                </div>

                <div class="col-md-4">
                    <label>Selected Recipients</label>
                    <div id="selectedPreview" class="border p-2" style="height:260px; overflow:auto"></div>
                </div>
            </div>

            <div class="mb-3">
                <label>Upload files</label>

                <div id="dropArea" class="border rounded p-4 mb-2 text-center" style="min-height:160px; cursor:pointer;">
                    <div style="max-width:640px;margin:0 auto;">
                        <div style="font-size:28px;">☁️</div>
                        <div class="text-muted">Drag & drop your <span class="text-danger">pdf,doc,docx, xlsx,xls,jpg,jpeg,png</span> files here upto 10mb or <a href="#" id="browseFileLink">Browse file</a></div>
                    </div>
                </div>
                <input id="fileInput" type="file" multiple class="d-none" />
                <div id="fileError" class="text-danger small mt-2" role="alert" aria-live="polite"></div>

                <div class="mt-3">
                    <p id="prevFilesMsg" class="text-success mb-2" style="display:none;">
                      your previously uploaded files... If you upload new file your previously uploaded file will be deleted
                    </p>

                    <!-- previous server attachments -->
                    <div id="previousFilesPreview" class="d-flex flex-wrap gap-2 mb-2"></div>

                    <!-- new selected files preview -->
                    <div id="filesPreview" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button id="saveDraft" type="button" class="btn btn-outline-secondary">Update (Save as Draft)</button>
            </div>
        </form>
    </div>
</div>

<!-- external modal -->
<div class="modal fade" id="externalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">External Users</h5>
        <button type="button" class="btn-close" id="closeExternalModal"></button>
      </div>
      <div class="modal-body">
        <input id="externalName" class="form-control mb-2" placeholder="Name" />
        <input id="externalEmail" class="form-control mb-2" placeholder="Email" />
        <div class="text-end mb-2">
          <button id="addExternalBtn" class="btn btn-success">Add</button>
        </div>
        <div id="externalList" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>

<!-- scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* previews styling */
.prev-file-card, .new-file-card {
  width: 160px;
  border: 1px solid #e6e6e6;
  border-radius: 6px;
  padding: 8px;
  text-align: center;
  background: #fff;
  position: relative;
}
.prev-file-card.dimmed { opacity:0.45; filter:grayscale(40%); }
.prev-file-thumb, .new-file-thumb { width:100%; height:88px; object-fit:cover; border-radius:4px; display:block; margin-bottom:6px; }
.file-name { font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.file-icon { font-size:36px; line-height:88px; color:#666; }
</style>

<script>
$(function(){
    // Blade-provided id (guarded)
    const id = @json($id ?? null);
    if (!id) {
        $('#alertBox').html('<div class="alert alert-danger">Notice id missing.</div>');
        return;
    }

    const API = '{{ url("/") }}/api';
    const token = localStorage.getItem('api_token') || null;

    // Setup AJAX headers (CSRF + Auth)
    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
        beforeSend(xhr){
            if(token) xhr.setRequestHeader('Authorization','Bearer '+token);
        }
    });

    // Allowed extensions and max size (10 MB)
    const ALLOWED_EXT = ['pdf','doc','docx','xlsx','xls','jpg','jpeg','png'];
    const MAX_BYTES = 10 * 1024 * 1024; // 10MB

    // state
    let departmentUsers = {}; // depId => users[]
    let selectedDepartments = [];
    let finalSelectedUsers = []; // internal users: {id,name,email}
    let externalUsers = []; // {name,email}
    let selectedFiles = []; // File[]

    // Quill
    const quill = new Quill('#quillEditor', { theme: 'snow' });

    // helpers
    function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
    function isImageExt(name){
        const ext = (name.split('.').pop()||'').toLowerCase();
        return ['jpg','jpeg','png','gif','webp','bmp'].includes(ext);
    }
    function fileUrlFor(item){
        if(!item) return '#';
        return item.file_url || item.url || item.file_path || (item.file_name ? `/storage/${item.file_name}` : '#');
    }

    // -------- Previous attachments preview ----------
    function renderPreviousAttachments(arr){
        const $wrap = $('#previousFilesPreview').empty();
        if(!arr || !arr.length){
            $('#prevFilesMsg').hide();
            return;
        }
        $('#prevFilesMsg').show();
        arr.forEach(it=>{
            const fname = it.file_name || it.name || 'file';
            const url = fileUrlFor(it);
            const $card = $('<div>').addClass('prev-file-card').attr('title', fname);
            if(isImageExt(fname)){
                const $img = $('<img>').addClass('prev-file-thumb').attr('src', url).on('error', function(){ $(this).attr('src','https://via.placeholder.com/160x88?text=No+Image'); });
                $card.append($img);
            } else {
                $card.append(`<div class="file-icon">📄</div>`);
            }
            const display = $('<div>').addClass('file-name');
            if(url !== '#'){
                display.append(`<a href="${url}" target="_blank" rel="noopener">${escapeHtml(fname)}</a>`);
            } else {
                display.text(fname);
            }
            $card.append(display);
            $wrap.append($card);
        });
    }

    // -------- New files preview ----------
    function renderFiles(){
        const $fp = $('#filesPreview').empty();
        if (!selectedFiles.length){
            $fp.html('<div class="text-muted">No files selected</div>');
            $('#previousFilesPreview .prev-file-card').removeClass('dimmed');
            return;
        }
        $('#previousFilesPreview .prev-file-card').addClass('dimmed');

        selectedFiles.forEach((f,idx)=>{
            const $card = $('<div>').addClass('new-file-card');
            if(isImageExt(f.name)){
                const $img = $('<img>').addClass('new-file-thumb').attr('alt', f.name);
                const reader = new FileReader();
                reader.onload = e => $img.attr('src', e.target.result);
                reader.readAsDataURL(f);
                $card.append($img);
            } else {
                $card.append(`<div class="file-icon">📄</div>`);
            }
            $card.append(`<div class="file-name">${escapeHtml(f.name)}</div>`);
            $card.append(`<div class="text-end mt-2"><button class="btn btn-sm btn-outline-danger remove-file" data-idx="${idx}">Remove</button></div>`);
            $fp.append($card);
        });
    }
    function validateFileObj(file){
        const name = file.name || '';
        const ext = (name.split('.').pop() || '').toLowerCase();
        if (!ext || !ALLOWED_EXT.includes(ext)) {
            return { ok: false, message: `File "${name}" not allowed. Allowed: ${ALLOWED_EXT.join(', ')}` };
        }
        if (file.size > MAX_BYTES) {
            return { ok: false, message: `File "${name}" is too large. Max ${MAX_BYTES/1024/1024} MB.` };
        }
        return { ok: true };
    }
    function addFilesFromList(fileList){
        const invalid = [];
        Array.from(fileList).forEach(f=>{
            const v = validateFileObj(f);
            if (v.ok){
                const duplicate = selectedFiles.some(sf => sf.name === f.name && sf.size === f.size);
                if (!duplicate) selectedFiles.push(f);
            } else {
                invalid.push(v.message);
            }
        });
        renderFiles();
        if (invalid.length){
            $('#fileError').html(invalid.join('<br/>'));
        } else {
            $('#fileError').text('');
        }
    }
    $('#dropArea').on('click', ()=> $('#fileInput').trigger('click'));
    $('#browseFileLink').on('click', function(e){ e.preventDefault(); $('#fileInput').trigger('click'); });
    $('#fileInput').on('change', function(){
        const files = this.files || [];
        if (files.length) addFilesFromList(files);
        $(this).val('');
    });
    $('#dropArea').on('dragover', e => { e.preventDefault(); $('#dropArea').addClass('border-primary'); });
    $('#dropArea').on('dragleave', e => { e.preventDefault(); $('#dropArea').removeClass('border-primary'); });
    $('#dropArea').on('drop', function(e){
        e.preventDefault(); $('#dropArea').removeClass('border-primary');
        const files = e.originalEvent?.dataTransfer?.files || [];
        if (files.length) addFilesFromList(files);
    });
    $('#filesPreview').on('click', '.remove-file', function(){
        const idx = Number($(this).data('idx'));
        selectedFiles.splice(idx,1);
        renderFiles();
    });

    // -------- External modal ----------
    const extModalEl = document.getElementById('externalModal');
    const extModal = extModalEl ? new bootstrap.Modal(extModalEl, {}) : null;
    $('#openExternalModal').on('click', ()=> {
        if (extModal) extModal.show();
        renderExternalList();
    });
    $('#closeExternalModal').on('click', ()=> { if (extModal) extModal.hide(); });

    function renderExternalList(){
        const $l = $('#externalList').empty();
        if(!externalUsers.length) return $l.html('<div class="text-muted">No external users</div>');
        externalUsers.forEach((u, idx)=> $l.append(
          `<div class="d-flex justify-content-between border p-1 mb-1 align-items-center">
             <div><strong>${escapeHtml(u.name)}</strong><div class="small text-muted">${escapeHtml(u.email)}</div></div>
             <button data-idx="${idx}" class="btn btn-sm btn-link rm-ext">Remove</button>
           </div>`
        ));
    }
    $('#externalList').on('click', '.rm-ext', function(){
        externalUsers.splice(Number($(this).data('idx')),1);
        renderExternalList();
        renderSelectedPreview();
    });
    $('#addExternalBtn').on('click', function(e){
        e.preventDefault();
        const n = $('#externalName').val().trim();
        const em = $('#externalEmail').val().trim();
        if(!n || !em){ alert('Name and Email required'); return; }
        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)){ alert('Invalid email'); return; }
        if(!externalUsers.find(x=>x.email.toLowerCase() === em.toLowerCase())) externalUsers.push({name:n, email:em});
        $('#externalName,#externalEmail').val('');
        renderExternalList();
        renderSelectedPreview();
    });

    // -------- Selected preview (WITH Internal/External badges) ----------
    function renderSelectedPreview(){
        const $c = $('#selectedPreview').empty();
        const combined = [
          ...finalSelectedUsers.map(u => ({...u, __type:'internal'})),
          ...externalUsers.map(u => ({...u, id:null, __type:'external'}))
        ];
        if(!combined.length) return $c.html('<div class="text-muted">No recipients selected</div>');
        combined.forEach(u=>{
            const isInternal = u.__type === 'internal';
            const badge = isInternal
              ? '<span class="badge bg-success ms-2">Internal</span>'
              : '<span class="badge bg-secondary ms-2">External</span>';
            const dataAttr = isInternal
              ? `data-int-id="${u.id}"`
              : `data-ext-email="${escapeHtml(u.email || '')}"`;

            $c.append(
              `<div class="d-flex justify-content-between border-bottom py-1 align-items-center mb-1">
                 <div>
                   <strong>${escapeHtml(u.name || '')}</strong> ${badge}
                   <div class="small text-muted">${escapeHtml(u.email || '')}</div>
                 </div>
                 <div><button class="btn btn-sm btn-link remove-rec" ${dataAttr}>Remove</button></div>
               </div>`
            );
        });
    }
    $('#selectedPreview').on('click', '.remove-rec', function(){
        const p = $(this).closest('[data-int-id],[data-ext-email]');
        if(p.attr('data-int-id')){
            const uid = Number(p.attr('data-int-id'));
            finalSelectedUsers = finalSelectedUsers.filter(u=>u.id !== uid);
            $('#departmentUsers').find(`input.dept-user-checkbox[data-id="${uid}"]`).prop('checked', false);
        } else if(p.attr('data-ext-email')){
            const e = p.attr('data-ext-email');
            externalUsers = externalUsers.filter(u=>u.email !== e);
            renderExternalList();
        }
        renderSelectedPreview();
    });

    // -------- Init (departments + notice data) ----------
    function init(){
        $.get(`${API}/departments-data`).done(res=>{
            const depts = res.data || res;
            $('#departmentsList').empty();
            depts.forEach(d=> $('#departmentsList').append(
              `<div class="mb-1">
                 <input class="dept-checkbox me-2" type="checkbox" value="${d.id}" id="dept_${d.id}">
                 <label for="dept_${d.id}">${escapeHtml(d.name)}</label>
               </div>`
            ));
        }).fail(()=> $('#departmentsList').html('<div class="text-muted">Failed to load departments</div>'));

        $.get(`${API}/notices/${id}`).done(res=>{
            const it = res.data || res;
            $('#title').val(it.title || '');
            quill.root.innerHTML = it.description || '';

            const propagations = it.propagations || [];
            const internal = propagations.filter(p=>p.user_id !== null);
            const externalBack = propagations.filter(p=>p.user_id === null);

            finalSelectedUsers = internal.map(p=>({
              id: p.user?.id || p.user_id,
              name: p.user?.name || p.name || '',
              email: p.user?.email || p.user_email || ''
            }));
            externalUsers = externalBack.map(p=>({ name: p.name || p.user?.name || '', email: p.user_email || '' }));

            if (Array.isArray(it.departments)) {
                selectedDepartments = it.departments.map(d=>d.id);
                setTimeout(()=> {
                    selectedDepartments.forEach(depId => $(`#dept_${depId}`).prop('checked', true));
                    selectedDepartments.forEach(depId => fetchDepartmentUsers(depId, true));
                }, 250);
            }

            const attachments = it.attachments || it.attach || [];
            const normalized = attachments.map(a => ({
                file_name: a.file_name || a.name || a.filename,
                file_url: a.file_url || a.url || a.file_path || null
            }));
            renderPreviousAttachments(normalized);

            renderExternalList();
            renderSelectedPreview();
        }).fail(()=> $('#alertBox').html('<div class="alert alert-danger">Failed to load notice</div>'));
    }
    init();

    // -------- Departments & Users ----------
    $('#departmentsList').on('change', '.dept-checkbox', function(){
        const depId = Number(this.value);
        if(this.checked){
            if (!selectedDepartments.includes(depId)) selectedDepartments.push(depId);
            fetchDepartmentUsers(depId, true);
        } else {
            selectedDepartments = selectedDepartments.filter(x=>x!==depId);
            delete departmentUsers[depId];
            renderDepartmentUsers();
        }
    });

    function fetchDepartmentUsers(depId, markAsChecked = false){
        if(departmentUsers[depId]){
            renderDepartmentUsers();
            if(markAsChecked) markUsersFromFinal();
            return;
        }
        $.get(`${API}/notices/department-users/${depId}`).done(res=>{
            departmentUsers[depId] = res.data || res || [];
            renderDepartmentUsers();
            if(markAsChecked) markUsersFromFinal();
        }).fail(()=> {
            departmentUsers[depId] = [];
            renderDepartmentUsers();
        });
    }

    // UPDATED: show user name WITH email in the list
    function renderDepartmentUsers(){
        const $box = $('#departmentUsers').empty();
        if(selectedDepartments.length===0){
            $box.html('<small class="text-muted">Choose departments</small>');
            return;
        }
        const all = [].concat(...selectedDepartments.map(id=> departmentUsers[id] || []));
        const unique = []; const map = {};
        all.forEach(u=>{ if(u && u.id != null && !map[u.id]){ map[u.id]=true; unique.push(u); } });
        if(!unique.length) return $box.html('<small class="text-muted">No users</small>');

        const allIds = unique.map(u=>u.id);
        const allSelected = allIds.every(id=> finalSelectedUsers.some(s=>s.id === id));
        $box.append(
          `<div class="mb-2">
             <input type="checkbox" id="selectAllDep" ${allSelected ? 'checked' : ''}/>
             <label for="selectAllDep">Select all</label>
           </div>`
        );

        unique.forEach(u=>{
            const checked = finalSelectedUsers.some(x=>x.id===u.id) ? 'checked' : '';
            const name = escapeHtml(u.name || 'User');
            const email = escapeHtml(u.email || '');
            $box.append(
              `<label class="form-check d-block mb-1">
                 <input class="form-check-input me-2 dept-user-checkbox"
                        type="checkbox" ${checked}
                        data-id="${u.id}" data-name="${name}" data-email="${email}">
                 <span class="form-check-label">
                   <strong>${name}</strong>
                   <small class="text-muted">(${email || 'no email'})</small>
                 </span>
               </label>`
            );
        });
    }

    function markUsersFromFinal(){
        $('#departmentUsers .dept-user-checkbox').each(function(){
            const id = Number($(this).data('id'));
            const exists = finalSelectedUsers.some(u=>u.id === id);
            $(this).prop('checked', !!exists);
        });
    }

    $('#departmentUsers').on('change', '#selectAllDep', function(){
        const checked = this.checked;
        $('#departmentUsers .dept-user-checkbox').each(function(){
            $(this).prop('checked', checked);
            const id = Number($(this).data('id'));
            const name = $(this).data('name');
            const email = $(this).data('email');
            if (checked) { if (!finalSelectedUsers.find(x=>x.id===id)) finalSelectedUsers.push({id, name, email}); }
            else finalSelectedUsers = finalSelectedUsers.filter(x=>x.id!==id);
        });
        renderSelectedPreview();
    });
    $('#departmentUsers').on('change', '.dept-user-checkbox', function(){
        const id = Number($(this).data('id'));
        const name = $(this).data('name');
        const email = $(this).data('email');
        if(this.checked){ if(!finalSelectedUsers.find(x=>x.id===id)) finalSelectedUsers.push({id, name, email}); }
        else finalSelectedUsers = finalSelectedUsers.filter(x=>x.id !== id);
        renderSelectedPreview();
    });

    // -------- Submit (Update) ----------
    $('#saveDraft').on('click', function(){
        const title = $('#title').val().trim();
        const description = quill.root.innerHTML;
        const fd = new FormData();
        fd.append('title', title);
        fd.append('description', description);

        selectedDepartments.forEach((d,i)=> fd.append(`departments[${i}]`, d));
        finalSelectedUsers.forEach((u,i)=> { if(u.id) fd.append(`internal_users[${i}]`, u.id); });
        externalUsers.forEach((u,i)=> { fd.append(`external_users[${i}][name]`, u.name); fd.append(`external_users[${i}][email]`, u.email); });
        selectedFiles.forEach((f,i)=> fd.append(`attachments[${i}]`, f));

        $('#saveDraft').prop('disabled', true).text('Updating...');
        $.ajax({
            url: `${API}/notices-update/${id}`,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        }).done(()=> {
            $('#alertBox').html('<div class="alert alert-success">Updated</div>');
            setTimeout(()=> window.location.href='/view-notices',1000);
        }).fail(xhr=>{
            if(xhr.status === 422){
                const e = xhr.responseJSON.errors || {};
                $('#error_title').text(e.title ? e.title[0] : '');
                $('#error_description').text(e.description ? e.description[0] : '');
                if(e.attachments) $('#fileError').html(e.attachments.join('<br/>'));
            } else {
                $('#alertBox').html('<div class="alert alert-danger">Failed to update notice</div>');
            }
        }).always(()=> {
            $('#saveDraft').prop('disabled', false).text('Update (Save as Draft)');
        });
    });
});
</script>

@endsection
