@extends('layouts.master')

@section('content')
<div class="container-fluid py-4">
  <h3 class="fw-bold mb-4">Super Admin Dashboard</h3>

  {{-- Skeleton Loader --}}
  <div id="skeletonLoader" class="row g-4">
    @for ($i = 0; $i < 4; $i++)
      <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm placeholder-glow">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-center bg-light rounded mb-3" style="height:60px;">
              <span class="placeholder col-4"></span>
            </div>
            <span class="placeholder col-8 mb-2"></span>
            <h4 class="placeholder col-4"></h4>
          </div>
        </div>
      </div>
    @endfor
  </div>

  {{-- Dashboard Data --}}
  <div id="dashboardContent" class="row g-4 d-none">
    {{-- Departments --}}
    <div class="col-sm-6 col-md-3">
      <a href="{{ route('departments.index') }}" class="text-decoration-none text-dark">
        <div class="card border-0 shadow-sm h-100 hover-shadow">
          <div class="card-body text-center">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:70px;height:70px;">
              <i class="bx bx-building text-primary fs-2"></i>
            </div>
            <p class="text-muted mb-1">Total Departments</p>
            <h3 id="departmentsCount" class="fw-bold mb-0">0</h3>
          </div>
        </div>
      </a>
    </div>

    {{-- Designations --}}
    <div class="col-sm-6 col-md-3">
      <a href="{{ route('designations.index') }}" class="text-decoration-none text-dark">
        <div class="card border-0 shadow-sm h-100 hover-shadow">
          <div class="card-body text-center">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:70px;height:70px;">
              <i class="bx bx-briefcase text-success fs-2"></i>
            </div>
            <p class="text-muted mb-1">Total Designations</p>
            <h3 id="designationsCount" class="fw-bold mb-0">0</h3>
          </div>
        </div>
      </a>
    </div>

    {{-- Users --}}
    <div class="col-sm-6 col-md-3">
      <a href="{{ route('panel.users.index') }}" class="text-decoration-none text-dark">
        <div class="card border-0 shadow-sm h-100 hover-shadow">
          <div class="card-body text-center">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:70px;height:70px;">
              <i class="bx bx-user text-info fs-2"></i>
            </div>
            <p class="text-muted mb-1">Total Users</p>
            <h3 id="usersCount" class="fw-bold mb-0">0</h3>
          </div>
        </div>
      </a>
    </div>

    {{-- Notices --}}
    <div class="col-sm-6 col-md-3">
      <a class="text-decoration-none text-dark">
        <div class="card border-0 shadow-sm h-100 hover-shadow">
          <div class="card-body text-center">
            <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-light rounded-circle" style="width:70px;height:70px;">
              <i class="bx bx-bell text-warning fs-2"></i>
            </div>
            <p class="text-muted mb-1">Total Notices</p>
            <h3 id="noticesCount" class="fw-bold mb-0">0</h3>
          </div>
        </div>
      </a>
    </div>
  </div>
</div>
<script>window.requiredRoles = ['super admin'];</script>
<script>
$(function(){
  const API_BASE = '{{ url('/') }}/api';
  const tokenKey = 'api_token';
  const token = localStorage.getItem(tokenKey);

  function fallbackMsg(msg){
    if (window.toastr) toastr.error(msg);
    else alert(msg);
  }

  console.log('[dashboard] booting. API_BASE:', API_BASE, 'token present:', !!token);

  if (!token) {
    console.warn('[dashboard] no api_token in localStorage — redirecting to login.');
    fallbackMsg('Not authenticated. Please login.');
    return window.location.href = '{{ route("login") }}';
  }

  // ensure loader visible
  $('#skeletonLoader').removeClass('d-none');
  $('#dashboardContent').addClass('d-none');

  $.ajax({
    url: API_BASE + '/dashboard/superadmin',
    method: 'GET',
    dataType: 'json',
    cache: false,
    headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer ' + token
    },
    timeout: 15000,
    success: function(resp, status, xhr){
      console.info('[dashboard] api success', resp);

      // Defensive: support multiple shapes
      // prefer resp.data (controller returns { success:true, data: { ... } })
      const payload = (resp && (resp.data ?? resp)) || {};
      // If controller returned wrapped with success:false -> handle
      if (resp && resp.success === false) {
        const msg = resp.message || 'Failed to fetch dashboard data';
        fallbackMsg(msg);
        $('#skeletonLoader').addClass('d-none');
        return;
      }

      // Extract counts with safe defaults
      const departments = payload.departments_count ?? payload.departments ?? 0;
      const designations = payload.designations_count ?? payload.designations ?? 0;
      const users = payload.users_count ?? payload.users ?? 0;
      const notices = payload.notices_count ?? payload.notices ?? 0;

      $('#departmentsCount').text(Number(departments));
      $('#designationsCount').text(Number(designations));
      $('#usersCount').text(Number(users));
      $('#noticesCount').text(Number(notices));

      // show data, hide skeleton
      $('#skeletonLoader').addClass('d-none');
      $('#dashboardContent').removeClass('d-none');
    },
    error: function(xhr, status, err){
      console.error('[dashboard] api error', status, err, xhr);
      // handle auth errors
      if (xhr.status === 401 || xhr.status === 403) {
        // clear token and redirect to login
        localStorage.removeItem(tokenKey);
        fallbackMsg('Session expired. Redirecting to login...');
        setTimeout(function(){ window.location.href = '{{ route("login") }}'; }, 900);
        return;
      }

      // Show server message if available
      let message = 'Failed to load dashboard data.';
      try {
        const json = xhr.responseJSON;
        if (json?.message) message = json.message;
      } catch(e){
        // ignore
      }
      fallbackMsg(message);

      // hide skeleton so user sees error
      $('#skeletonLoader').addClass('d-none');
    },
    complete: function(){
      // nothing here — kept for debugging
      console.log('[dashboard] request complete');
    }
  });
});
</script>

@endsection

