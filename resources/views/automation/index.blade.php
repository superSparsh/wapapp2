<x-layouts.app title="Automation - WapApp" active="automation.index">
  <div class="flex flex-col bg-surface">
    <div class="p-4">
      <x-ui.page-header title="Automation" subtitle="Manage chatbots, drip marketing, and flows." />
    </div>

    <section class="grid gap-4 p-4 pt-0 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ([
        ['Chatbot', 'Build automated conversation flows', 'chatbot.index', 'device-message'],
        ['Drip Marketing', 'Schedule message sequences', 'automation.drip.index', 'programming-arrows'],
        ['Automation Events', 'Schedule custom automation triggers', 'automation.events.index', 'calendar'],
        ['Flows', 'Visual automation builder', 'whatsapp-flows.index', 'hierarchy-3'],
      ] as [$title, $desc, $route, $icon])
        <a href="{{ route($route) }}" class="group flex flex-col gap-5 rounded-xl border border-border bg-elevated p-5 transition-shadow hover:shadow-[0px_4px_12px_0px_rgba(0,0,0,0.08)]">
          <div class="flex size-12 items-center justify-center rounded-xl bg-green-50 p-3">
            <x-icons.nav-icon :name="$icon" class="size-6" />
          </div>
          <div class="flex flex-col gap-1">
            <h3 class="fd-card-title group-hover:text-green-500">{{ $title }}</h3>
            <p class="fd-page-note">{{ $desc }}</p>
          </div>
          <span class="fd-btn inline-flex items-center gap-2">
            Open
            <x-icons.nav-icon name="arrow-right" class="size-4" />
          </span>
        </a>
      @endforeach
    </section>
  </div>
</x-layouts.app>
