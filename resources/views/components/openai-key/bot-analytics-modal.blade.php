@props(['open' => false, 'closeHref' => null])

<div
  id="modal-bot-analytics"
  data-modal="bot-analytics"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-bot-analytics"
>
  <div class="flex max-h-[90vh] w-full max-w-[613px] flex-col items-center gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex w-full items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-bot-analytics" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Bot Readiness Analysis
        </h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Configuration health and readiness assessment.
        </p>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @else
        <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      @endif
    </div>

    {{-- Health gauge --}}
    <div class="flex h-[229px] w-[233px] shrink-0 flex-col items-center justify-center gap-3">
      <div class="relative flex size-[180px] items-center justify-center">
        <svg class="size-full -rotate-90" viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="16" />
          <circle
            id="analytics-health-arc"
            cx="100" cy="100" r="80" fill="none"
            stroke="#22c55e" stroke-width="16" stroke-linecap="round"
            stroke-dasharray="502.4"
            stroke-dashoffset="502.4"
            style="transition: stroke-dashoffset 0.6s ease;"
          />
        </svg>
        <div class="absolute flex flex-col items-center">
          <span id="analytics-health-percent" class="text-3xl font-bold leading-[1.2] text-text-primary">0%</span>
          <span class="text-xs font-medium leading-[1.4] text-text-muted">Health</span>
        </div>
      </div>
    </div>

    {{-- Bot info summary --}}
    <div class="flex w-full flex-col gap-2 rounded-xl border border-border-light bg-muted-surface p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium leading-[1.4] text-text-muted">Bot Name</span>
        <span id="analytics-bot-name" class="text-sm font-semibold leading-[1.4] text-text-primary">—</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium leading-[1.4] text-text-muted">Provider</span>
        <span id="analytics-bot-provider" class="text-sm font-semibold leading-[1.4] text-text-primary">—</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium leading-[1.4] text-text-muted">Chat Model</span>
        <span id="analytics-bot-model" class="text-sm font-semibold leading-[1.4] text-text-primary">—</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium leading-[1.4] text-text-muted">System Prompt</span>
        <span id="analytics-bot-prompt" class="text-sm font-semibold leading-[1.4] text-text-primary">—</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium leading-[1.4] text-text-muted">Knowledge Base Entries</span>
        <span id="analytics-bot-kb" class="text-sm font-semibold leading-[1.4] text-text-primary">—</span>
      </div>
    </div>

    {{-- Executive Summary --}}
    <div class="flex w-full items-start gap-3 rounded-xl bg-green-100 p-3.5">
      <img src="{{ asset('images/openai-key/tick-circle-linear.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
      <div class="flex min-w-0 flex-1 flex-col gap-2.5">
        <p class="text-sm font-bold leading-[1.4] text-text-body">Executive Summary</p>
        <ul class="list-disc space-y-0 pl-[21px] text-sm font-normal leading-[1.4] text-text-muted">
          <li id="analytics-summary">Select a bot to view its readiness analysis.</li>
        </ul>
      </div>
    </div>

    {{-- Recommendations --}}
    <div id="analytics-recommendations-box" class="flex w-full items-start gap-3 rounded-xl bg-stat-orange/15 p-3.5">
      <div class="flex min-w-0 flex-1 flex-col gap-2.5">
        <p class="text-sm font-bold leading-[1.4] text-text-body">Recommendations</p>
        <ul id="analytics-recommendations" class="list-disc pl-[21px] text-sm font-normal leading-[1.8] text-text-muted">
          <li>No data yet. Click a bot's analytics icon to view recommendations.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var triggers = document.querySelectorAll('[data-open-modal="bot-analytics"]');

    function populate(btn) {
      var name = btn.dataset.botName || '—';
      var provider = btn.dataset.botProvider || '—';
      var model = btn.dataset.botModel || '—';
      var prompt = btn.dataset.botPrompt || 'No';
      var kb = btn.dataset.botKb || '0';

      document.getElementById('analytics-bot-name').textContent = name;
      document.getElementById('analytics-bot-provider').textContent = provider;
      document.getElementById('analytics-bot-model').textContent = model || 'Not set';
      document.getElementById('analytics-bot-prompt').textContent = prompt;
      document.getElementById('analytics-bot-kb').textContent = kb;

      // Calculate health: 4 criteria, each 25%
      var score = 0;
      if (provider && provider !== '—') score += 25;
      if (model && model !== '—' && model !== 'Not set') score += 25;
      if (prompt === 'Yes') score += 25;
      if (parseInt(kb) > 0) score += 25;

      // Update gauge arc
      var circumference = 502.4;
      var offset = circumference - (score / 100) * circumference;
      var arc = document.getElementById('analytics-health-arc');
      arc.style.strokeDashoffset = offset;

      // Color based on score
      var color = score >= 75 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#ef4444';
      arc.setAttribute('stroke', color);
      document.getElementById('analytics-health-percent').textContent = score + '%';

      // Summary
      var summaryEl = document.getElementById('analytics-summary');
      summaryEl.textContent = name + ' is ' + score + '% configured for production use.';

      // Recommendations
      var recs = [];
      if (!model || model === '—' || model === 'Not set') {
        recs.push('Set a specific chat model to ensure consistent AI responses.');
      }
      if (prompt === 'No') {
        recs.push('Add a system prompt to guide the bot\'s behavior and tone.');
      }
      if (parseInt(kb) === 0) {
        recs.push('Add knowledge base entries (text, URLs, or documents) to improve response accuracy.');
      }
      if (recs.length === 0) {
        recs.push('This bot is fully configured and ready for production use.');
      }

      var recList = document.getElementById('analytics-recommendations');
      recList.innerHTML = recs.map(function (r) { return '<li>' + r + '</li>'; }).join('');
    }

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () { populate(btn); });
    });
  })();
</script>
