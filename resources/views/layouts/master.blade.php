<!DOCTYPE html>

<html
  lang="en"
  class="light-style layout-menu-fixed layout-compact"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Dashboard - Analytics | Sneat - Bootstrap 5 HTML Admin Template - Pro</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="{{asset('template/assets/vendor/fonts/boxicons.css')}}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('template/assets/vendor/css/core.css')}}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{asset('template/assets/vendor/css/theme-default.css')}}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{asset('')}}template/assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
    <link rel="stylesheet" href="{{asset('template/assets/vendor/libs/apex-charts/apex-charts.css')}}" />
    {{-- Datatable CSS --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" />

    <!-- Page CSS -->

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- Helpers -->
    <script src="{{asset('template/assets/vendor/js/helpers.js')}}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{asset('template/assets/js/config.js')}}"></script>



  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">

        @include('backend.includes.side-nav')
        <!-- Layout container -->
        <div class="layout-page">

          @include('backend.includes.nav')
          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              @yield('content')
            </div>
            <!-- / Content -->
            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->

    <script src="{{asset('template/assets/vendor/libs/jquery/jquery.js')}}"></script>
    <script src="{{asset('template/assets/vendor/libs/popper/popper.js')}}"></script>
    <script src="{{asset('template/assets/vendor/js/bootstrap.js')}}"></script>
    <script src="{{asset('template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
    <script src="{{asset('template/assets/vendor/js/menu.js')}}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{asset('template/assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>

    <!-- Main JS -->
    <script src="{{asset('template/assets/js/main.js')}}"></script>

    <!-- Page JS -->
    <script src="{{asset('template/assets/js/dashboards-analytics.js')}}"></script>

    {{-- Datatable Scripts --}}
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script>
        new DataTable('#datatable');
        new DataTable('#datatable1');
        new DataTable('#datatable2');
    </script>

    <script>
        (function () {
          const token = localStorage.getItem('api_token');

          // Pages that should be blocked if you're already logged in:
          const loginLikePaths = ['/ext/login', '/login', '/register'];

          // Decide where to push the user when logged in
          function goDashboardByRole(role){
            if(role === 'Super Admin') return '/dashboard';
            if(role === 'Admin|PO')       return '/meetings';
            return '/user/meetings';
          }

          async function getRole(){
            try{
              const base = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
              const res = await fetch(base + '/api/profile', {
                headers: { 'Authorization': 'Bearer ' + token, 'X-Requested-With': 'XMLHttpRequest' }
              });
              if(!res.ok) return null;
              const json = await res.json();
              return json?.data?.role || null;
            }catch(e){ return null; }
          }

          (async function main(){
            const path = window.location.pathname;

            // If NOT logged in and page requires login, bounce to login
            const mustBeAuthed = [
              '/user-profile','/user-notices','/user-notices/', '/user/meetings',
              '/meetings','/view-notices','/rooms','/settings'
            ];
            if(!token && mustBeAuthed.some(p => path.startsWith(p))){
              window.location.replace('/ext/login');
              return;
            }

            // If logged in and on a login-like page, bounce to dashboard
            if(token && loginLikePaths.includes(path)){
              const role = await getRole();
              window.location.replace(goDashboardByRole(role));
            }
          })();
        })();
        </script>

        <script>
            const API_BASE = '{{ rtrim(config("app.url") ?: url("/"), "/") }}/api';

            // show message after redirects (we use sessionStorage)
            (function () {
              const msg = sessionStorage.getItem('flash_error');
              if (msg) {
                sessionStorage.removeItem('flash_error');
                if (window.toastr) toastr.warning(msg);
                else alert(msg);
              }
            })();

            // If any API call returns redirect_to, auto redirect
            $(document).ajaxError(function (event, xhr) {
              const j = xhr.responseJSON || {};
              if ((xhr.status === 401 || xhr.status === 403) && j.redirect_to) {
                sessionStorage.setItem('flash_error', j.message || 'Unauthorized');
                window.location.href = j.redirect_to;
              }
            });
          </script>

          <script>
              (function () {
                // Pages can set window.requiredRoles = ['admin','po'] etc.
                const required = window.requiredRoles || [];
                if (!required.length) return; // no guard needed on this page

                const token = localStorage.getItem('api_token');
                if (!token) {
                  sessionStorage.setItem('flash_error', 'Please login first.');
                  window.location.href = '{{ route("ext.login") }}';
                  return;
                }

                $.ajax({
                  url: API_BASE + '/profile',
                  method: 'GET',
                  headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }
                })
                .done(function (resp) {
                  const u = resp?.data || resp || {};
                  const roles = [];

                  if (u.role) roles.push(u.role);
                  if (Array.isArray(u.role_names)) roles.push(...u.role_names);
                  if (Array.isArray(u.roles)) roles.push(...u.roles.map(r => (typeof r === 'string' ? r : (r.name || ''))));

                  const rolesLc = roles.map(r => String(r).toLowerCase()).filter(Boolean);
                  const requiredLc = required.map(r => String(r).toLowerCase());

                  const ok = rolesLc.some(r => requiredLc.includes(r));
                  if (ok) return;

                  // pick dashboard based on actual role
                  let target = '{{ route("dashboard.user") }}';
                  if (rolesLc.includes('super admin')) target = '{{ route("dashboard.superadmin") }}';
                  else if (rolesLc.includes('admin') || rolesLc.includes('po')) target = '{{ route("dashboard.admin") }}';

                  sessionStorage.setItem('flash_error', 'You are not allowed to access that page. Redirected to your dashboard.');
                  window.location.href = target;
                })
                .fail(function () {
                  sessionStorage.setItem('flash_error', 'Session expired. Please login again.');
                  window.location.href = '{{ route("ext.login") }}';
                });

              })();
              </script>


  <script>
  (function () {
      // pages that do NOT need token
      const publicPaths = [
          '/ext/login',
          '/login',
          '/',
      ];

      let path = window.location.pathname;
      // normalize trailing slash
      if (path.length > 1 && path.endsWith('/')) {
          path = path.slice(0, -1);
      }

      // if current page is public -> do nothing
      if (publicPaths.includes(path)) {
          return;
      }

      const token = localStorage.getItem('api_token');

      // no token -> go to login
      if (!token) {
          window.location.href = '/ext/login';
          return;
      }

      // set global ajax header
      if (window.$) {
          $.ajaxSetup({
              beforeSend: function (xhr) {
                  xhr.setRequestHeader('Authorization', 'Bearer ' + token);
              }
          });
      }
  })();
  </script>



  </body>
</html>
