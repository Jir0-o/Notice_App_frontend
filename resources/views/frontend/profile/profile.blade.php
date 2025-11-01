@extends('layouts.master')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<style>
  .profile-container { max-width: 1200px; margin: 1.5rem auto; }
  .panel { border: 1px solid #e6e6e6; border-radius: 8px; background: #ffffff; padding: 1.1rem; margin-bottom: 1rem; }
  .panel .panel-title { font-weight: 600; margin-bottom: .75rem; }
  .avatar-circle { width:72px; height:72px; border-radius:50%; overflow:hidden; border:1px solid #ddd; }
  .muted { color:#6c757d; }
  .field-label { font-size:.9rem; font-weight:600; margin-bottom:.35rem; display:block; }
  .form-actions { display:flex; justify-content:flex-end; gap:.5rem; }
  .small-muted { font-size:.85rem; color:#6c757d; }
  .profile-top { display:flex; gap:1rem; align-items:center; }
  .profile-top .meta { flex:1; }
  .profile-grid { display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; }
  @media (max-width: 991px) {
    .profile-grid { grid-template-columns: 1fr; }
  }
  .error-text { color:#dc3545; font-size:.9rem; margin-top:.25rem; display:block; }
  .sig-box { border:1px dashed #ccc; border-radius:4px; padding:.35rem .5rem; min-height:60px; display:inline-block; }
  .sig-box img { max-height:56px; }
</style>

<div class="profile-container">

  <!-- Top avatar card -->
  <div class="panel">
    <div class="profile-top">
      <div class="avatar-circle">
        <img id="profileAvatar" src="{{ asset('template/assets/img/avatars/1.png') }}" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <div class="meta">
        <h5 id="profileName" style="margin:0;">Loading...</h5>
        <div id="profileRole" class="small-muted">Role</div>
        <div class="small-muted mt-1">
          Signature:
          <span class="sig-box" id="signaturePreviewBox">
            <span id="signatureEmptyText">No signature</span>
            <img id="profileSignature" src="" alt="signature" style="display:none;">
          </span>
        </div>
      </div>
      <div style="text-align:right;">
        <button id="btn-refresh-profile" class="btn btn-outline-secondary btn-sm">Refresh</button>
      </div>
    </div>
  </div>

  <!-- Personal Information -->
  <div class="panel">
    <div class="panel-title">Personal Information</div>
    <div class="profile-grid">
      <div>
        <p class="small-muted mb-1">Name</p>
        <p id="profileNameSmall" class="mb-2">—</p>
      </div>
      <div>
        <p class="small-muted mb-1">Email address</p>
        <p id="profileEmail" class="mb-2">—</p>
      </div>
      <div>
        <p class="small-muted mb-1">Phone</p>
        <p id="profilePhone" class="mb-2">—</p>
      </div>
      <div>
        <p class="small-muted mb-1">Role</p>
        <p id="profileRoleSmall" class="mb-2">—</p>
      </div>
    </div>
  </div>

  <!-- Update Profile -->
  <div class="panel">
    <div class="panel-title">Update Profile</div>

    <!-- enctype added for clarity; AJAX uses FormData -->
    <form id="profileForm" autocomplete="off" class="row g-3" enctype="multipart/form-data">
      <div class="col-12">
        <label class="field-label" for="username">Name</label>
        <input id="username" name="username" type="text" class="form-control" placeholder="User Name" />
        <span id="err-name" class="error-text" style="display:none;"></span>
      </div>

      <div class="col-12">
        <label class="field-label" for="phonenumber">Phone number</label>
        <input id="phonenumber" name="phonenumber" type="text" class="form-control" placeholder="User Phone Number" />
        <span id="err-phone" class="error-text" style="display:none;"></span>
      </div>

      <!-- NEW: profile photo -->
      <div class="col-12">
        <label class="field-label" for="profile_photo">Profile Photo</label>
        <input id="profile_photo" name="profile_photo" type="file" class="form-control" accept="image/*" />
        <small class="small-muted">Allowed: png, jpg, jpeg.</small>
      </div>

      <!-- NEW: signature -->
      <div class="col-12">
        <label class="field-label" for="signature">Signature</label>
        <input id="signature" name="signature" type="file" class="form-control" accept="image/*,.pdf" />
        <small class="small-muted">Allowed: png, jpg, jpeg, pdf.</small>
      </div>

      <div class="col-12 form-actions">
        <button id="btn-update-profile" type="submit" class="btn btn-dark btn-sm">Update User</button>
      </div>
    </form>
  </div>

  <!-- Update Password -->
  <div class="panel">
    <div class="panel-title">Update Password</div>

    <form id="passwordForm" autocomplete="off" class="row g-3">
      <div class="col-12">
        <label class="field-label" for="currentpassword">Current Password</label>
        <input id="currentpassword" name="currentpassword" type="password" class="form-control" placeholder="Current Password" />
        <span id="err-current_password" class="error-text" style="display:none;"></span>
      </div>

      <div class="col-12">
        <label class="field-label" for="newpassword">New Password</label>
        <input id="newpassword" name="newpassword" type="password" class="form-control" placeholder="New Password" />
        <span id="err-new_password" class="error-text" style="display:none;"></span>
      </div>

      <div class="col-12">
        <label class="field-label" for="confirmnewpassword">Confirm New Password</label>
        <input id="confirmnewpassword" name="confirmnewpassword" type="password" class="form-control" placeholder="Confirm New Password" />
        <span id="err-confirmed_password" class="error-text" style="display:none;"></span>
      </div>

      <div class="col-12">
        <p id="err-general" class="error-text" style="display:none;"></p>
      </div>

      <div class="col-12 form-actions">
        <button id="btn-change-password" type="submit" class="btn btn-dark btn-sm">Update password</button>
      </div>
    </form>
  </div>

</div>

<!-- libs -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function() {
  const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
  function getToken(){ return localStorage.getItem('api_token') || null; }

  $.ajaxSetup({
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
    },
    beforeSend: function(xhr){
      const t = getToken();
      if(t) xhr.setRequestHeader('Authorization', 'Bearer ' + t);
    }
  });

  function clearErrorsProfile(){
    $('#err-name').hide().text('');
    $('#err-phone').hide().text('');
  }
  function clearErrorsPassword(){
    $('#err-current_password').hide().text('');
    $('#err-new_password').hide().text('');
    $('#err-confirmed_password').hide().text('');
    $('#err-general').hide().text('');
  }
  function showToast(type, msg){ toastr[type](msg); }

  // Load profile
  function loadProfile(){
    const token = getToken();
    if(!token){
      $('#profileName').text('Guest');
      $('#profileAvatar').attr('src', '{{ asset('template/assets/img/avatars/1.png') }}');
      $('#profileSignature').hide();
      $('#signatureEmptyText').show();
      showToast('warning', 'No API token found. Please login to load profile.');
      return;
    }

    $('#profileName').text('Loading...');
    $.ajax({
      url: API_BASE + '/api/profile',
      method: 'GET',
      dataType: 'json'
    }).done(function(res){
      const payload = res?.data ?? res ?? {};
      const name  = payload.name || '';
      const email = payload.email || '';
      const phone = payload.phone || '';
      const role  = payload.role || (payload.role_names?.[0] ?? '');

      // photo
      let photoUrl = '{{ asset('template/assets/img/avatars/1.png') }}';
      if (payload.profile_photo_url) {
        photoUrl = payload.profile_photo_url;
      } else if (payload.profile_photo_path) {
        photoUrl = API_BASE + '/' + payload.profile_photo_path.replace(/^\/+/, '');
      }

      // signature
      let sigUrl = null;
      if (payload.signature_path) {
        sigUrl = API_BASE + '/' + payload.signature_path.replace(/^\/+/, '');
      }

      // set UI values
      $('#profileAvatar').attr('src', photoUrl);
      $('#profileName').text(name);
      $('#profileRole').text(role);
      $('#profileNameSmall').text(name);
      $('#profileRoleSmall').text(role);
      $('#profileEmail').text(email);
      $('#profilePhone').text(phone);
      $('#username').val(name);
      $('#phonenumber').val(phone);

      if (sigUrl) {
        $('#profileSignature').attr('src', sigUrl).show();
        $('#signatureEmptyText').hide();
      } else {
        $('#profileSignature').hide();
        $('#signatureEmptyText').show();
      }
    }).fail(function(xhr){
      $('#profileAvatar').attr('src', '{{ asset('template/assets/img/avatars/1.png') }}');
      $('#profileSignature').hide();
      $('#signatureEmptyText').show();
      showToast('error', xhr.status === 401 ? 'Unauthorized. Please login.' : 'Failed to load profile.');
    });
  }

  // Update profile (now multipart)
  $('#profileForm').on('submit', function(e){
    e.preventDefault();
    clearErrorsProfile();

    const name = $('#username').val() ? $('#username').val().toString().trim() : '';
    const phone = $('#phonenumber').val() ? $('#phonenumber').val().toString().trim() : '';
    const filePhoto = $('#profile_photo')[0].files[0];
    const fileSignature = $('#signature')[0].files[0];

    if(!name){
      $('#err-name').show().text('Name is required.');
      return;
    }
    if(phone && !/^[0-9+\s\-()]{6,20}$/.test(phone)){
      $('#err-phone').show().text('Enter a valid phone number.');
      return;
    }

    $('#btn-update-profile').prop('disabled', true).text('Updating...');

    // Build FormData to match controller's expectation
    const fd = new FormData();
    fd.append('name', name);
    fd.append('phone', phone);
    // optional extra fields if backend wants them in future
    // fd.append('designation_id', '');
    // fd.append('department_id', '');
    if (filePhoto) {
      fd.append('profile_photo', filePhoto);
    }
    if (fileSignature) {
      fd.append('signature', fileSignature);
    }

    $.ajax({
      url: API_BASE + '/api/profile',
      method: 'POST',
      data: fd,
      dataType: 'json',
      processData: false,
      contentType: false,
    }).done(function(res){
      showToast('success', res?.message || 'Profile updated successfully.');
      loadProfile();
      clearErrorsProfile();
      // clear file inputs
      $('#profile_photo').val('');
      $('#signature').val('');
    }).fail(function(xhr){
      console.error('update profile error', xhr);
      const errData = xhr.responseJSON || {};
      if(errData.errors){
        if(errData.errors.name && errData.errors.name[0]) $('#err-name').show().text(errData.errors.name[0]);
        if(errData.errors.phone && errData.errors.phone[0]) $('#err-phone').show().text(errData.errors.phone[0]);
      } else if(errData.message){
        if(String(errData.message).toLowerCase().includes('phone')) {
          $('#err-phone').show().text(errData.message);
        } else if(String(errData.message).toLowerCase().includes('name')) {
          $('#err-name').show().text(errData.message);
        } else {
          showToast('error', errData.message || 'Failed to update profile.');
        }
      } else {
        showToast('error', 'Failed to update profile.');
      }
    }).always(function(){
      $('#btn-update-profile').prop('disabled', false).text('Update User');
    });
  });

  // Change password
  $('#passwordForm').on('submit', function(e){
    e.preventDefault();
    clearErrorsPassword();

    const current = $('#currentpassword').val() ? $('#currentpassword').val().toString() : '';
    const newp = $('#newpassword').val() ? $('#newpassword').val().toString() : '';
    const conf = $('#confirmnewpassword').val() ? $('#confirmnewpassword').val().toString() : '';

    if(!current){ $('#err-current_password').show().text('Current password required.'); return; }
    if(!newp){ $('#err-new_password').show().text('New password required.'); return; }
    if(newp !== conf){ $('#err-confirmed_password').show().text('Passwords do not match.'); return; }

    $('#btn-change-password').prop('disabled', true).text('Updating...');

    const payload = {
      current_password: current,
      new_password: newp,
      confirmed_password: conf
    };

    $.ajax({
      url: API_BASE + '/api/profile/password',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    }).done(function(res){
      showToast('success', res?.message || 'Password updated. You will be logged out.');
      localStorage.removeItem('api_token');
      setTimeout(function(){ window.location.href = '/login'; }, 900);
    }).fail(function(xhr){
      console.error('password change error', xhr);
      const errData = xhr.responseJSON || {};
      if(errData.errors){
        if(errData.errors.current_password) $('#err-current_password').show().text(errData.errors.current_password[0]);
        if(errData.errors.new_password) $('#err-new_password').show().text(errData.errors.new_password[0]);
        if(errData.errors.confirmed_password) $('#err-confirmed_password').show().text(errData.errors.confirmed_password[0]);
      } else if(errData.message){
        if(xhr.status === 401 || xhr.status === 403){
          $('#err-current_password').show().text(errData.message || 'Invalid current password.');
        } else {
          $('#err-general').show().text(errData.message || 'Failed to change password.');
        }
      } else {
        $('#err-general').show().text('Failed to change password.');
      }
    }).always(function(){
      $('#btn-change-password').prop('disabled', false).text('Update password');
    });
  });

  // Refresh button
  $('#btn-refresh-profile').on('click', loadProfile);

  // Initial load
  loadProfile();
});
</script>
@endsection
