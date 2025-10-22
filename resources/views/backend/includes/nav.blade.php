<!-- Navbar (custom dropdowns, no bootstrap JS required) -->
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">

  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

    <ul class="navbar-nav flex-row align-items-center ms-auto">

      <!-- Notification Dropdown (custom) -->
      <li class="nav-item me-3 position-relative" id="nav-notif">
        <a href="javascript:void(0);" id="notifToggle" class="nav-link" aria-expanded="false" role="button">
          <i class="bx bx-bell fs-4"></i>
          <span id="notifBadge"
                class="badge rounded-pill bg-danger text-white"
                style="font-size: 0.65rem; position: absolute; top: 6px; right: 6px; display: none;"></span>
        </a>

        <div id="notifMenu" class="dropdown-menu shadow-lg p-0"
             style="width: 320px; max-height: 420px; overflow-y: auto; right: 0; left: auto; position: absolute; display:none; z-index:2200;">
          <div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong>Notifications</strong>
            <button id="markAllRead" class="btn btn-sm btn-link p-0">Mark all read</button>
          </div>
          <div id="notifList" class="list-group list-group-flush">
            <div class="px-3 py-2 text-muted small">Loading...</div>
          </div>
        </div>
      </li>

      <!-- User Dropdown (custom) -->
      <li class="nav-item position-relative" id="nav-user">
        <a href="javascript:void(0);" id="userToggle" class="nav-link d-flex align-items-center" aria-expanded="false" role="button">
          <div class="avatar avatar-online me-2">
            <img src="{{ asset('template/assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle" />
          </div>
          <div class="d-none d-md-block text-start">
            <div id="navUserName" class="fw-medium">Loading...</div>
            <small id="navUserRole" class="text-muted">—</small>
          </div>
          <i class="bx bx-chevron-down ms-2"></i>
        </a>

        <div id="userMenu" class="dropdown-menu shadow-lg"
             style="width: 220px; right: 0; left: auto; position: absolute; display:none; z-index:2200;">
          <div class="p-3 border-bottom">
            <div class="d-flex align-items-center">
              <div class="me-2">
                <img src="{{ asset('template/assets/img/avatars/1.png') }}" class="w-px-40 h-auto rounded-circle" alt />
              </div>
              <div>
                <div id="userMenuName" class="fw-semibold">Loading...</div>
                <small id="userMenuRole" class="text-muted">—</small>
              </div>
            </div>
          </div>

          <a class="dropdown-item" href="{{ route('user.profile.index') }}">
            <i class="bx bx-user me-2"></i> My Profile
          </a>

          <div class="dropdown-divider"></div>

          <div class="px-3 py-2">
            <form id="logout-form" method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="btn btn-danger w-100">Log out</button>
            </form>
          </div>
        </div>
      </li>

    </ul>
  </div>
</nav>

<!-- / Navbar -->

<!-- jQuery is required; ensure only one version is included in master -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
(function($){
  $(function(){

    const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
    const tokenKey = 'api_token';
    const token = localStorage.getItem(tokenKey) || null;
    const loginUrl = "{{ route('ext.login') }}";

    // helpers
    function authHeaders(xhr) {
      if (token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    }
    function safeText(s){ return (s===null||s===undefined) ? '' : String(s); }
    function hideAllMenus(){ $('#notifMenu,#userMenu').hide().closest('li').removeClass('show'); $('#notifToggle,#userToggle').attr('aria-expanded','false'); $(document).off('click.globalHide keydown.globalHide'); }

    // --- Load profile info and show/hide menu items by role if needed
    function loadUserInfo(){
      if (!token) return;
      $.ajax({
        url: API_BASE + '/api/profile',
        method: 'GET',
        beforeSend: authHeaders,
        dataType: 'json',
        success: function(res){
          const data = (res && res.data) ? res.data : (res || {});
          const name = data.name ?? data.user?.name ?? 'User';
          // roles may be string or array
          let role = '';
          if (data.role) role = data.role;
          else if (Array.isArray(data.roles) && data.roles.length) role = (typeof data.roles[0] === 'string') ? data.roles[0] : (data.roles[0].name || '');
          else if (data.role_names && data.role_names.length) role = data.role_names[0];
          $('#navUserName, #userMenuName').text(name);
          $('#navUserRole, #userMenuRole').text(role || '—');
          // Optionally handle client-side menu visibility by role here
        },
        error: function(xhr){
          // if 401/403, clear token
          if (xhr.status === 401 || xhr.status === 403){
            localStorage.removeItem(tokenKey);
            console.warn('profile fetch failed, token removed');
          }
        }
      });
    }

    // --- Notifications: unread count + list
    function loadUnreadCount(){
      if (!token) return;
      $.ajax({
        url: API_BASE + '/api/notifications/unread-count',
        method: 'GET',
        beforeSend: authHeaders,
        success: function(resp){
          const count = resp?.count ?? 0;
          const $b = $('#notifBadge');
          if (count > 0) {
            $b.text(count > 9 ? '9+' : count).show();
          } else $b.hide();
        }
      });
    }

    function loadNotifications(){
      if (!token) {
        $('#notifList').html('<div class="px-3 py-2 text-muted small">Please login</div>');
        return;
      }
      const $list = $('#notifList');
      $list.html('<div class="px-3 py-2 text-muted small">Loading...</div>');
      $.ajax({
        url: API_BASE + '/api/notifications?only=all&per_page=10',
        method: 'GET',
        beforeSend: authHeaders,
        success: function(res){
          const items = res?.data ?? [];
          if (!items || items.length === 0) {
            $list.html('<div class="px-3 py-2 text-muted small">No notifications.</div>');
            return;
          }
          $list.empty();
          items.forEach(n => {
            const readClass = n.read_at ? 'text-muted' : 'fw-bold';
            const title = $('<div>').text(n.title || 'Untitled').html();
            const excerpt = $('<small>').text(n.excerpt || '').html();
            const li = $(`
              <div class="list-group-item d-flex justify-content-between align-items-start ${readClass}" data-id="${n.id}" style="cursor:pointer;">
                <div>
                  <div class="fw-semibold">${title}</div>
                  <div class="text-muted small">${excerpt}</div>
                </div>
                <div class="ms-2 text-end">
                  ${n.read_at ? '' : `<a href="#" class="markRead text-success small d-block mb-1">✓</a>`}
                  <a href="#" class="deleteNotif text-danger small">✕</a>
                </div>
              </div>
            `);
            $list.append(li);
          });
        },
        error: function(xhr){
          console.error('notifications load failed', xhr);
          $list.html('<div class="px-3 py-2 text-muted small">Failed to load.</div>');
        }
      });
    }

    // --- Menu open/close logic (custom)
    const $notifToggle = $('#notifToggle');
    const $notifMenu   = $('#notifMenu');
    const $notifItem   = $('#nav-notif');

    const $userToggle  = $('#userToggle');
    const $userMenu    = $('#userMenu');
    const $userItem    = $('#nav-user');

    function openNotif(){
      hideAllMenus();
      $notifMenu.show();
      $notifItem.addClass('show');
      $notifToggle.attr('aria-expanded','true');
      loadNotifications();
      loadUnreadCount();
      // close on outside click / ESC
      $(document).on('click.globalHide', function(e){
        if ($(e.target).closest($notifItem).length === 0) hideAllMenus();
      }).on('keydown.globalHide', function(e){ if (e.key === 'Escape') hideAllMenus(); });
    }
    function toggleNotif(){ $notifMenu.is(':visible') ? hideAllMenus() : openNotif(); }

    function openUser(){
      hideAllMenus();
      $userMenu.show();
      $userItem.addClass('show');
      $userToggle.attr('aria-expanded','true');
      $(document).on('click.globalHide', function(e){
        if ($(e.target).closest($userItem).length === 0) hideAllMenus();
      }).on('keydown.globalHide', function(e){ if (e.key === 'Escape') hideAllMenus(); });
    }
    function toggleUser(){ $userMenu.is(':visible') ? hideAllMenus() : openUser(); }

    // Bind toggles
    $notifToggle.on('click', function(e){ e.preventDefault(); e.stopPropagation(); toggleNotif(); });
    $userToggle.on('click', function(e){ e.preventDefault(); e.stopPropagation(); toggleUser(); });

    // Prevent clicks inside menus from closing (we manage closing)
    $notifMenu.on('click', function(e){ e.stopPropagation(); });
    $userMenu.on('click', function(e){ e.stopPropagation(); });

    // Mark one as read
    $(document).on('click', '.markRead', function(e){
      e.preventDefault();
      const id = $(this).closest('[data-id]').data('id');
      if (!id) return;
      $.ajax({
        url: API_BASE + '/api/notifications/' + id + '/read',
        method: 'POST',
        beforeSend: authHeaders
      }).done(function(){ loadNotifications(); loadUnreadCount(); });
    });

    // Delete one
    $(document).on('click', '.deleteNotif', function(e){
      e.preventDefault();
      const id = $(this).closest('[data-id]').data('id');
      if (!id) return;
      $.ajax({
        url: API_BASE + '/api/notifications/' + id,
        method: 'DELETE',
        beforeSend: authHeaders
      }).done(function(){ loadNotifications(); loadUnreadCount(); });
    });

    // Mark all read
    $('#markAllRead').on('click', function(e){
      e.preventDefault();
      $.ajax({
        url: API_BASE + '/api/notifications/read-all',
        method: 'POST',
        beforeSend: authHeaders
      }).done(function(){ loadNotifications(); loadUnreadCount(); });
    });

    // Logout: clear token on submit (let server handle session logout)
    $('#logout-form').on('submit', function(e){
      e.preventDefault();

      // Clear tokens & remembered info
      localStorage.removeItem(tokenKey);
      localStorage.removeItem('remember_email');

      // Redirect straight to external login
      window.location.replace(loginUrl);
    });

    // Initial loads
    loadUserInfo();
    loadUnreadCount();

    // poll unread count every 60s
    setInterval(loadUnreadCount, 60000);

    // make sure dropdowns hide if route changes or user clicks elsewhere
    $(window).on('popstate', hideAllMenus);

  });
})(jQuery);
</script>
