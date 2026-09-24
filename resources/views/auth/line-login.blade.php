<x-layouts.guest title="Number Access Login - WapApp">
  <main class="line-login-shell min-h-screen lg:grid lg:grid-cols-2">
    {{-- Left panel: context --}}
    <aside class="relative hidden overflow-hidden bg-[#0B1F17] lg:flex lg:flex-col lg:justify-center lg:px-14 lg:py-16">
      <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true" style="background-image: radial-gradient(circle at 20% 20%, rgba(34,197,94,0.35), transparent 45%), radial-gradient(circle at 80% 70%, rgba(16,185,129,0.22), transparent 40%), linear-gradient(160deg, #0B1F17 0%, #10261C 55%, #0B1F17 100%);"></div>
      <div class="relative z-10 mx-auto flex w-full max-w-md flex-col gap-8">
        <div class="flex size-20 items-center justify-center rounded-full border border-green-400/30 bg-green-400/10 shadow-[0_0_40px_rgba(34,197,94,0.18)]">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <rect x="5" y="11" width="14" height="10" rx="2"/>
            <path d="M8 11V8a4 4 0 018 0v3"/>
            <path d="M12 15v2"/>
          </svg>
        </div>
        <div class="flex flex-col gap-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-green-300/80">Number-specific workspace</p>
          <h1 class="text-3xl font-bold leading-tight text-white">One login. One WhatsApp number.</h1>
          <p class="text-base leading-relaxed text-white/70">
            Sign in with the WhatsApp number and Number Access password shared by your account owner. You will only see Inbox data for that line.
          </p>
        </div>
        <div class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 text-sm leading-relaxed text-white/65">
          <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
          </svg>
          <span>Billing, settings, and full account controls stay with the owner. This login is for operating a single number.</span>
        </div>
      </div>
    </aside>

    {{-- Right panel: form --}}
    <section class="flex min-h-screen flex-col justify-center bg-elevated px-4 py-10 sm:px-8">
      <div class="mx-auto w-full max-w-[420px]">
        <div class="mb-8 flex justify-center lg:justify-start">
          <x-auth.logo />
        </div>

        <header class="mb-6 flex flex-col gap-2 text-center lg:text-left">
          <h2 class="text-2xl font-bold leading-tight text-text-primary">Number access login</h2>
          <p class="text-sm leading-relaxed text-text-muted">Use the credentials you received for this WhatsApp number.</p>
        </header>

        <div class="mb-6 flex items-start gap-3 rounded-xl border border-border bg-surface px-3.5 py-3 text-sm leading-relaxed text-text-body">
          <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          <span>Operator view only — Inbox is limited to the number you sign in with.</span>
        </div>

        @if (session('status'))
          <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('status') }}
          </div>
        @endif

        @if ($errors->any())
          <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
            </svg>
            <span>{{ $errors->first() }}</span>
          </div>
        @endif

        <form method="POST" action="{{ route('line.login.submit') }}" class="flex flex-col gap-5">
          @csrf

          <div class="flex flex-col gap-1.5">
            <label for="phone" class="text-sm font-semibold text-text-primary">WhatsApp number</label>
            <input
              id="phone"
              type="tel"
              name="phone"
              value="{{ old('phone') }}"
              placeholder="e.g. 919876543210"
              autocomplete="username"
              required
              autofocus
              class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('phone') border-red-400 @enderror"
            >
            <p class="text-xs text-text-muted">Include country code. Spaces or symbols are fine.</p>
            @error('phone')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="password" class="text-sm font-semibold text-text-primary">Number access password</label>
            <input
              id="password"
              type="password"
              name="password"
              placeholder="Enter password for this number"
              autocomplete="current-password"
              required
              minlength="8"
              class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('password') border-red-400 @enderror"
            >
            @error('password')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </div>

          <button
            type="submit"
            class="mt-1 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#0B1F17] px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-[#143226] focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Continue to Inbox
          </button>
        </form>

        <p class="mt-6 rounded-xl border border-dashed border-border bg-surface px-4 py-3 text-center text-xs leading-relaxed text-text-muted">
          Need the main account? Ask your administrator for the standard login link.
        </p>
      </div>
    </section>
  </main>
</x-layouts.guest>
