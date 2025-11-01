@extends('layouts.guest')

@section('content')
<section class="vh-100 d-flex align-items-center" style="background: linear-gradient(135deg,#f0f4ff 0%, #eaeaea 100%);">
  <div class="container">
    <div class="row justify-content-center align-items-center">
      <div class="col-lg-10 col-xl-9">
        <div class="card shadow-lg border-0" style="border-radius: 18px;">
          <div class="row g-0 align-items-center">
            
            {{-- Left: Form --}}
            <div class="col-lg-6 p-5 order-2 order-lg-1">
              <div class="text-center mb-4">
                <h3 class="fw-bold mb-2">Login</h3>
                <p class="text-muted mb-0 small">
                  Skills for Industry Competitiveness and Innovation Program
                </p>
              </div>

              <div id="alertBox" class="alert d-none" role="alert"></div>

              <form id="loginForm" autocomplete="off">
                @csrf
                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input 
                    type="email" 
                    id="email"
                    name="email"
                    class="form-control form-control-lg"
                    placeholder="Enter your email"
                    required>
                </div>

                <div class="mb-3">
                  <label for="password" class="form-label d-flex justify-content-between">
                    <span>Password</span>
                    @if (Route::has('password.request'))
                      <a href="{{ route('password.request') }}" class="small text-success text-decoration-none">Forgot password?</a>
                    @endif
                  </label>
                  <div class="input-group input-group-lg">
                    <input 
                      type="password"
                      id="password"
                      name="password"
                      class="form-control"
                      placeholder="Enter your password"
                      required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePw">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                  </div>
                </div>

                <button type="submit" id="btnLogin" class="btn btn-primary btn-lg w-100">
                  <span class="spinner-border spinner-border-sm me-2 d-none" id="spin"></span>
                  Sign In
                </button>

                {{-- <div class="text-center mt-3 small text-muted">
                  Your session token is stored securely in your browser.
                </div> --}}
              </form>
            </div>

            {{-- Right: Image --}}
            <div class="col-lg-6 bg-light text-center py-5 order-1 order-lg-2">
              <img 
                src="{{ asset('images/sicip/SICIP.png') }}"
                alt="SICIP Program"
                class="img-fluid"
                style="max-height: 380px;">
            </div>

          </div>
        </div>

        <div class="text-center mt-3 text-muted small">
          @include('backend.partials.footer')
        </div>
      </div>
    </div>
  </div>
</section>

{{-- External JS --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>

<script>
  const API_BASE = '{{ rtrim(config('app.url'), '/') }}/api';
  const tokenKey = 'api_token';

  function showAlert(type, text) {
    const box = $('#alertBox');
    box.removeClass('d-none alert-success alert-danger alert-warning')
       .addClass('alert-' + type)
       .html(text);
  }

  function extractErr(xhr){
    if (xhr?.responseJSON?.errors) {
      const first = Object.values(xhr.responseJSON.errors)[0];
      return Array.isArray(first) ? first[0] : (first || 'Validation error');
    }
    return xhr?.responseJSON?.message || ('Error ' + xhr.status);
  }

  // Toggle password visibility
  $('#togglePw').on('click', function(){
    const el = document.getElementById('password');
    const isPw = el.type === 'password';
    el.type = isPw ? 'text' : 'password';
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
  });

  // Handle login
  $('#loginForm').on('submit', function(e){
    e.preventDefault();
    const email = $('#email').val().trim();
    const password = $('#password').val();
    const remember = $('#rememberMe').is(':checked');

    $('#btnLogin').prop('disabled', true);
    $('#spin').removeClass('d-none');
    showAlert('warning', 'Authenticating...');

    $.ajax({
      url: API_BASE + '/auth/login',
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      data: { email, password }
    }).done(function(resp){
      const token = resp.token || resp?.data?.token || resp.access_token;
      if (!token) { showAlert('danger', 'Login success but no token returned!'); return; }

      localStorage.setItem(tokenKey, token);
      if (remember) localStorage.setItem('remember_email', email);
      else localStorage.removeItem('remember_email');

      showAlert('success', 'Login successful. Checking role...');

      $.ajax({
        url: API_BASE + '/profile',
        method: 'GET',
        headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
      }).done(function(profile){
        const data = profile?.data || profile;
        let role = null;

        if (data.role) role = data.role;
        else if (Array.isArray(data.roles) && data.roles.length)
          role = typeof data.roles[0] === 'string' ? data.roles[0] : (data.roles[0].name || '');
        else if (data.role_names && data.role_names.length)
          role = data.role_names[0];

        role = role ? role.toLowerCase() : '';
        let redirectUrl = '';

        if (role.includes('super')) redirectUrl = '{{ route('dashboard') }}';
        else if (role === 'admin') redirectUrl = '{{ route('meetings.index') }}';
        else redirectUrl = '{{ route('user.meetings.index') }}';

        showAlert('success', 'Welcome ' + role.toUpperCase() + '! Redirecting...');
        setTimeout(() => window.location.href = redirectUrl, 1000);
      }).fail(function(){
        showAlert('warning', 'Role check failed, redirecting to dashboard.');
        setTimeout(() => window.location.href = '{{ route('dashboard') }}', 1200);
      });

    }).fail(function(xhr){
      showAlert('danger', extractErr(xhr));
    }).always(function(){
      $('#btnLogin').prop('disabled', false);
      $('#spin').addClass('d-none');
    });
  });

  // Pre-fill remembered email
  (function(){
    const saved = localStorage.getItem('remember_email');
    if (saved) {
      $('#email').val(saved);
      $('#rememberMe').prop('checked', true);
    }
  })();
</script>

<script>
console.log('PATH=', window.location.pathname);
console.log('TOKEN=', !!localStorage.getItem('api_token'));
fetch('/api/profile', {
  headers: { 'Authorization': 'Bearer ' + localStorage.getItem('api_token') }
}).then(r => r.json()).then(j => console.log('PROFILE=', j));
</script>
@endsection
