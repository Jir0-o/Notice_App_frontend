<!-- Menu (client-side role gating + active state) -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a class="app-brand-link d-flex align-items-center justify-content-center">
    <img
        id="brandLogo"
        src="{{ asset('images/sicip/SICIP.png') }}"
        alt="SICIP Logo"
        class="img-fluid"
        style="width: 80px; transition: all 0.3s ease;"
    />
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1" id="clientRoleMenu">
    <!-- Header -->
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Main</span></li>

    <!-- Dashboard (Super Admin only) -->
    <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-dashboard" style="display:none;">
      <a href="{{ route('dashboard') }}" class="menu-link" id="menu-dashboard-link">
        <i class="menu-icon tf-icons bx bx-home"></i>
        <div data-i18n="Dashboard">Dashboard</div>
      </a>
    </li>

    <!-- Departments (Super Admin only) -->
    <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-departments" style="display:none;">
      <a href="{{ route('departments.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-building"></i>
        <div data-i18n="Departments">Departments</div>
      </a>
    </li>

    <!-- Designations (Super Admin only) -->
    <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-designations" style="display:none;">
      <a href="{{ route('designations.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-briefcase"></i>
        <div data-i18n="Designations">Designations</div>
      </a>
    </li>

    <!-- Users (Super Admin only) -->
    <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-users" style="display:none;">
      <a href="{{ route('panel.users.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-user"></i>
        <div data-i18n="Users">Users</div>
      </a>
    </li>

    <!-- Archive (Super Admin only) -->
    <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-archive" style="display:none;">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-archive"></i>
        <div data-i18n="Archive">Archive</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item"><a href="{{ route('archive.departments') }}" class="menu-link"><div>Departments (Archived)</div></a></li>
        <li class="menu-item"><a href="{{ route('archive.designations') }}" class="menu-link"><div>Designations (Archived)</div></a></li>
        <li class="menu-item"><a href="{{ route('archive.users') }}" class="menu-link"><div>Users (Archived)</div></a></li>
      </ul>
    </li>

    <li class="menu-item menu-by-role" data-role="Admin, PO" id="menu-meetings-admin" style="display:none;">
      <a href="{{ route('meetings.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-calendar"></i>
        <div data-i18n="Meetings">Meetings</div>
      </a>
    </li>

    <li class="menu-item menu-by-role" data-role="Admin , User" id="menu-notices-admin" style="display:none;">
      <a href="{{ route('notices.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-bell"></i>
        <div data-i18n="Notices">Upload Notice</div>
      </a>
    </li>

    <!-- NEW: Generate Notice (Admin only) -->
    <li class="menu-item menu-by-role" data-role="Admin, PO, AEPD" id="menu-notice-generate" style="display:none;">
      <a href="{{ route('notices.generate') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-edit-alt"></i>
        <div data-i18n="Generate Notice">Create Notice</div>
      </a>
    </li>

    <li class="menu-item menu-by-role" data-role="Admin" id="menu-rooms" style="display:none;">
      <a href="{{ route('rooms.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-box"></i>
        <div data-i18n="Rooms">Rooms</div>
      </a>
    </li>

    <!-- User links -->
    <li class="menu-item menu-by-role" data-role="User" id="menu-user-meetings" style="display:none;">
      <a href="{{ route('user.meetings.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-calendar-event"></i>
        <div data-i18n="My Meetings">Meetings</div>
      </a>
    </li>

    <li class="menu-item menu-by-role" data-role="User" id="menu-user-notices" style="display:none;">
      <a href="{{ route('user-notices.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-bell"></i>
        <div data-i18n="My Notices">My Notices</div>
      </a>
    </li>

    <!-- Settings (Super Admin only) -->
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Settings</span></li>
    {{-- <li class="menu-item menu-by-role" data-role="Super Admin" id="menu-settings" style="display:none;">
      <a href="{{ route('settings') }}" class="menu-link">
        <i class="menu-icon bx bx-cog"></i>
        <div data-i18n="Settings">Settings</div>
      </a>
    </li> --}}

    <!-- My profile (public) -->
    <li class="menu-item menu-by-role" data-public="1" id="menu-profile">
      <a href="{{ route('user.profile.index') }}" class="menu-link">
        <i class="menu-icon tf-icons bx bx-user-circle"></i>
        <div data-i18n="Profile">My profile</div>
      </a>
    </li>
  </ul>
</aside>

<!-- Role gating + Active item -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Update the sidebar script section -->
<script>
(function($){
  $(function(){
    const API_BASE = "{{ rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/') }}";
    const token = localStorage.getItem('api_token') || null;

    function extractRole(payload){
      if(!payload) return null;
      if(payload.role) return String(payload.role);
      if(Array.isArray(payload.roles) && payload.roles.length){
        const r = payload.roles[0];
        return (typeof r === 'string') ? r : (r.name || r.role || null);
      }
      if(payload.role_names && payload.role_names.length) return payload.role_names[0];
      if(payload.roleNames && payload.roleNames.length) return payload.roleNames[0];
      if(payload.user && (payload.user.role || payload.user.roles)){
        if(payload.user.role) return payload.user.role;
        if(Array.isArray(payload.user.roles) && payload.user.roles.length){
          const r = payload.user.roles[0];
          return (typeof r === 'string') ? r : (r.name || null);
        }
      }
      return null;
    }

    function setActiveMenu(){
      const current = window.location.pathname.replace(/\/+$/,'') || '/';
      let best = null, bestLen = -1;

      $('#clientRoleMenu a.menu-link').each(function(){
        let href = this.getAttribute('href') || '';
        if(!href || href === 'javascript:void(0);') return;
        try { href = new URL(href, window.location.origin).pathname; } catch(e){}
        href = href.replace(/\/+$/,'') || '/';

        if(current === href || (href !== '/' && current.startsWith(href))){
          if(href.length > bestLen){
            best = $(this);
            bestLen = href.length;
          }
        }
      });

      if(best){
        const li = best.closest('.menu-item');
        li.addClass('active');
        li.parents('.menu-item').addClass('active open');
      }
    }

    // Show public items right away
    $('[data-public="1"]').show();

    if(!token){
      setActiveMenu();
      return;
    }

    $.ajaxSetup({
      beforeSend: function(xhr){
        const t = localStorage.getItem('api_token') || null;
        if(t) xhr.setRequestHeader('Authorization', 'Bearer ' + t);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      }
    });

    $.ajax({
      url: API_BASE + '/api/profile',
      method: 'GET',
      dataType: 'json',
      timeout: 10000
    }).done(function(res){
      const payload = (res && res.data) ? res.data : (res || {});
      const roleRaw = extractRole(payload);
      const role = roleRaw ? String(roleRaw).trim() : null;

      if(!role){ setActiveMenu(); return; }

      // Store role globally for use in other scripts
      window.currentUserRole = role;
      localStorage.setItem('user_role', role);

      // Reveal items matching role (data-role can be comma-separated)
      $('[data-role]').each(function(){
        const rolesList = (($(this).attr('data-role') || '')).split(',').map(s => s.trim()).filter(Boolean);
        if(rolesList.includes(role)){ $(this).show(); }
      });

      setActiveMenu();
    }).fail(function(){
      setActiveMenu();
    });

  });
})(jQuery);
</script>
