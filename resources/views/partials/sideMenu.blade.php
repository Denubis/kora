@php
  $sidebarCookie = false;
  if(isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == "1") { $sidebarCookie = true; }
@endphp

<div class="side-menu side-menu-js @php if ($sidebarCookie) { echo 'active'; } @endphp">
  <div class="blanket blanket-js"></div>
  <aside class="aside-content">
    <div class="header-elements">
      @yield('aside-content')
    </div>
    <div class="footer-elements">
      <div class="drawer-element">
        <a target="_blank" href="https://github.com/matrix-msu/kora/issues" class="drawer-toggle">
          <i class="icon icon-feedback"></i>
          <span>Submit Feedback</span>
          <i class="icon icon-external-link"></i>
        </a>
      </div>

      <div class="drawer-element">
        <a target="_blank" href="https://github.com/matrix-msu/kora" class="drawer-toggle">
          <i class="icon icon-help"></i>
          <span>Help & Documentation</span>
          <i class="icon icon-external-link"></i>
        </a>
      </div>

      @if(null !== \Auth::user() && \Auth::user()->admin)
        @include('partials.sideMenu.management', ['openDrawer' => (isset($openManagement) && $openManagement)])
      @endif
    <div>
  </aside>
</div>
