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
      // --- CONFIG (server-aware) ---
      const BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}"; // e.g. https://notice.quaarks.com
      const API_BASE = BASE + '/api';
      const token = localStorage.getItem('api_token') || null;

      // normalize path — remove trailing slash
      function normPath(p) {
        if (!p) return '/';
        if (p.length > 1 && p.endsWith('/')) return p.slice(0, -1);
        return p;
      }

      const currentPath = normPath(window.location.pathname);

      // pages where logged-in user should NOT stay
      const loginLikePaths = [
        '/',             // base
        '/login',
        '/ext/login',
        '/register'
      ].map(normPath);

      // pages that MUST be logged in
      const protectedPrefixes = [
        '/dashboard',
        '/meetings',
        '/user/meetings',
        '/view-notices',
        '/rooms',
        '/settings',
        '/user-profile',
        '/user-notices'
      ];

      function isProtected(path) {
        path = normPath(path);
        return protectedPrefixes.some(p => path === p || path.startsWith(p + '/'));
      }

      // map role -> url
      function redirectByRole(role) {
        // role coming from API may be: "Super Admin", "Admin", "Inspector", etc.
        if (!role) return '/user/meetings';

        const r = String(role).toLowerCase();
        if (r.includes('super')) return '/dashboard';
        if (r === 'admin')       return '/meetings';
        return '/user/meetings';
      }

      async function fetchProfileRole(token) {
        try {
          const res = await fetch(API_BASE + '/profile', {
            headers: {
              'Authorization': 'Bearer ' + token,
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          if (!res.ok) return null;
          const json = await res.json();
          const data = json?.data || json;

          // try all possible shapes
          if (data?.role) return data.role;
          if (Array.isArray(data?.roles) && data.roles.length) {
            return typeof data.roles[0] === 'string'
              ? data.roles[0]
              : (data.roles[0].name || null);
          }
          if (Array.isArray(data?.role_names) && data.role_names.length) {
            return data.role_names[0];
          }
          return null;
        } catch (e) {
          return null;
        }
      }

      (async function main() {
        // 1) NOT LOGGED IN CASE
        if (!token) {
          // user is on a protected page → kick to login
          if (isProtected(currentPath)) {
            window.location.replace('/ext/login');
          }
          return;
        }

        // 2) LOGGED IN CASE → get role
        const role = await fetchProfileRole(token);
        const target = redirectByRole(role);

        // 2a) if user is on login-like page, bounce them
        if (loginLikePaths.includes(currentPath)) {
          window.location.replace(target);
          return;
        }

        // 2b) else: user is on some guest page but already logged in → optional:
        // if they hit "/" (base) → also bounce
        if (currentPath === '/') {
          window.location.replace(target);
          return;
        }

        // else: logged in, on a normal/protected page → let them stay
      })();
    })();
    </script>


</body>

</html>
