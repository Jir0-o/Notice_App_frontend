<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" />
    <!-- MDB -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.0.0/mdb.min.css" rel="stylesheet" />
    <link href="{{ asset('backend/css/master.css') }}" rel="stylesheet" />

</head>

<body>
    <div class="font-sans text-gray-900 antialiased">
        @yield('content')
    </div>

    @livewireScripts
    <!-- MDB -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.0.0/mdb.umd.min.js"></script>
        <script>
        (function () {
          const token = localStorage.getItem('api_token');

          // Pages that should be blocked if you're already logged in:
          const loginLikePaths = ['/','/ext/login', '/login', '/register'];

          // Decide where to push the user when logged in
          function goDashboardByRole(role){
            if(role === 'Super Admin') return '/dashboard';
            if(role === 'Admin')       return '/meetings';
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
</body>

</html>
