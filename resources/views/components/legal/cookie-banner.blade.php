@if (config('legal.cookies.banner_enabled') && config('legal.public_publish'))
  <div
    id="legal-cookie-banner"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-elevated p-4 shadow-[0_-4px_16px_rgba(0,0,0,0.08)]"
    role="dialog"
    aria-label="Cookie notice"
    hidden
    data-legal-cookie-banner
  >
    <div class="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-text-body">
        We use essential cookies to run {{ config('legal.product_name', 'WapApp') }} securely. Analytics or marketing cookies (if any) are used only with your choice.
      </p>
      <div class="flex shrink-0 gap-2">
        <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold" data-cookie-essential>
          Essential only
        </button>
        <button type="button" class="rounded-lg bg-primary-2 px-3 py-2 text-sm font-semibold text-white" data-cookie-accept>
          Accept
        </button>
      </div>
    </div>
  </div>
  <script>
    (() => {
      const key = 'wapapp_cookie_choice';
      const banner = document.querySelector('[data-legal-cookie-banner]');
      if (!banner || localStorage.getItem(key)) return;
      banner.hidden = false;
      const save = (v) => { localStorage.setItem(key, v); banner.hidden = true; };
      banner.querySelector('[data-cookie-essential]')?.addEventListener('click', () => save('essential'));
      banner.querySelector('[data-cookie-accept]')?.addEventListener('click', () => save('accepted'));
    })();
  </script>
@endif
