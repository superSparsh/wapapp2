@props(['title' => null, 'active' => '', 'mainOverflow' => 'overflow-y-auto', 'suppressValidationToasts' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="preview-business-name" content="{{ \App\Support\PreviewBusinessName::resolve() }}">
    <title>{{ $title ?? config('app.name', 'WapApp') }}</title>
    <x-layouts.theme-boot />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head-scripts')
</head>
<body class="h-screen overflow-hidden bg-surface font-display text-text-primary antialiased">
    <div class="flex h-screen">
        <div class="relative hidden h-screen shrink-0 overflow-visible lg:block">
            <x-app.sidebar :active="$active ?? ''" :mobile="false" />
        </div>
        <div id="mobile-sidebar" class="fixed inset-y-0 left-0 z-50 w-[243px] -translate-x-full overflow-hidden transition-transform lg:hidden">
            <x-app.sidebar :active="$active ?? ''" :mobile="true" />
        </div>
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-overlay lg:hidden" aria-hidden="true"></div>
        <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <x-app.header />
            <x-app.impersonation-banner />
            <x-app.admin-area-ribbon />
            <main @class(['min-h-0 flex-1 bg-surface', $mainOverflow])>
                {{ $slot }}
            </main>
        </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggle = () => {
          sidebar?.classList.toggle('-translate-x-full');
          backdrop?.classList.toggle('hidden');
        };
        backdrop?.addEventListener('click', toggle);
        document.querySelector('[data-mobile-menu]')?.addEventListener('click', toggle);
        const userPanel = document.getElementById('user-panel');
        const userPanelBackdrop = document.getElementById('user-panel-backdrop');
        const userMenuBtn = document.querySelector('[data-user-menu]');
        const notificationsPanel = document.getElementById('notifications-panel');
        const notificationsBackdrop = document.getElementById('notifications-panel-backdrop');
        const notificationsMenuBtn = document.querySelector('[data-notifications-menu]');
        const desktopSidebar = document.querySelector('[data-desktop-sidebar]');
        const desktopSidebarToggle = document.querySelector('[data-desktop-sidebar-toggle]');

        const applyDesktopSidebarState = (collapsed) => {
          if (!desktopSidebar) return;

          desktopSidebar.dataset.collapsed = collapsed ? 'true' : 'false';
          desktopSidebar.classList.toggle('w-[88px]', collapsed);
          desktopSidebar.classList.toggle('w-[243px]', !collapsed);

          desktopSidebar.querySelectorAll('[data-sidebar-label],[data-sidebar-badge],[data-sidebar-submenu]').forEach((el) => {
            el.classList.toggle('hidden', collapsed);
          });

          desktopSidebar.querySelectorAll('[data-sidebar-item]').forEach((el) => {
            el.classList.toggle('justify-center', collapsed);
            el.classList.toggle('gap-0', collapsed);
            el.classList.toggle('gap-3', !collapsed);
          });

          desktopSidebar.querySelectorAll('[data-sidebar-chevron]').forEach((el) => {
            el.classList.toggle('hidden', collapsed);
          });

          const icon = desktopSidebar.querySelector('[data-sidebar-toggle-icon]');
          icon?.classList.toggle('rotate-180', !collapsed);
          icon?.classList.toggle('rotate-0', collapsed);
          desktopSidebarToggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        };

        const storedSidebarState = window.localStorage.getItem('sidebar-collapsed') === 'true';
        applyDesktopSidebarState(storedSidebarState);

        desktopSidebarToggle?.addEventListener('click', () => {
          const nextCollapsed = desktopSidebar?.dataset.collapsed !== 'true';
          applyDesktopSidebarState(nextCollapsed);
          window.localStorage.setItem('sidebar-collapsed', nextCollapsed ? 'true' : 'false');
        });

        const closeUserPanel = () => {
          userPanel?.classList.add('hidden');
          userPanelBackdrop?.classList.add('hidden');
          userMenuBtn?.setAttribute('aria-expanded', 'false');
        };

        const openUserPanel = () => {
          closeNotificationsPanel();
          userPanel?.classList.remove('hidden');
          userPanelBackdrop?.classList.remove('hidden');
          userMenuBtn?.setAttribute('aria-expanded', 'true');
        };

        const closeNotificationsPanel = () => {
          notificationsPanel?.classList.add('hidden');
          notificationsBackdrop?.classList.add('hidden');
          notificationsMenuBtn?.setAttribute('aria-expanded', 'false');
        };

        const openNotificationsPanel = async () => {
          closeUserPanel();
          notificationsPanel?.classList.remove('hidden');
          notificationsBackdrop?.classList.remove('hidden');
          notificationsMenuBtn?.setAttribute('aria-expanded', 'true');
        };

        const markAllNotificationsRead = async () => {
          const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
          try {
            await fetch('{{ route('notifications.read') }}', {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
              },
            });
            document.querySelector('[data-notification-badge]')?.classList.add('hidden');
            document.querySelector('[data-notification-count]')?.classList.add('hidden');
            const markBtn = document.querySelector('[data-mark-all-notifications-read]');
            if (markBtn) {
              markBtn.disabled = true;
            }
            const list = document.querySelector('[data-notifications-list]');
            if (list) {
              list.innerHTML = '<div class="px-4 py-8 text-center text-sm text-text-muted" data-notifications-empty>No new notifications.</div>';
            }
          } catch (e) {
            // Ignore mark-read failures; panel still works.
          }
        };

        notificationsMenuBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          if (notificationsPanel?.classList.contains('hidden')) {
            openNotificationsPanel();
          } else {
            closeNotificationsPanel();
          }
        });

        document.querySelector('[data-mark-all-notifications-read]')?.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          markAllNotificationsRead();
        });

        notificationsBackdrop?.addEventListener('click', closeNotificationsPanel);

        document.addEventListener('click', (e) => {
          if (! notificationsPanel || ! notificationsMenuBtn) return;
          if (! notificationsPanel.contains(e.target) && ! notificationsMenuBtn.contains(e.target)) {
            closeNotificationsPanel();
          }
        });

        userMenuBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          if (userPanel?.classList.contains('hidden')) {
            openUserPanel();
          } else {
            closeUserPanel();
          }
        });

        userPanelBackdrop?.addEventListener('click', closeUserPanel);

        document.addEventListener('click', (e) => {
          if (! userPanel || ! userMenuBtn) return;
          if (! userPanel.contains(e.target) && ! userMenuBtn.contains(e.target)) {
            closeUserPanel();
          }
        });

        const scrollSidebarToActive = (nav) => {
          const active = nav.querySelector('[data-sidebar-active]');
          if (! active) return;

          const navRect = nav.getBoundingClientRect();
          const activeRect = active.getBoundingClientRect();
          const offset = activeRect.top - navRect.top - (navRect.height / 2) + (activeRect.height / 2);

          nav.scrollTop = nav.scrollTop + offset;
        };

        document.querySelectorAll('[data-sidebar-nav]').forEach(scrollSidebarToActive);
      });
    </script>
    <x-ui.confirm-dialog />
    <x-ui.page-loader />
    @php
      $flashToasts = [];

      if (session('status')) {
          $flashToasts[] = ['type' => 'success', 'message' => session('status')];
      }

      if (session('error')) {
          $flashToasts[] = ['type' => 'error', 'message' => session('error')];
      }

      if ($errors->any() && ! ($suppressValidationToasts ?? false)) {
          foreach ($errors->all() as $error) {
              $flashToasts[] = ['type' => 'error', 'message' => $error];
          }
      }
    @endphp
    @if (! empty($flashToasts))
      <script>window.__FLASH_TOASTS__ = @json($flashToasts);</script>
    @endif
    @stack('scripts')
</body>
</html>
