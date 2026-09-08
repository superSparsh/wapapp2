<x-layouts.guest title="Number Login - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-elevated shadow-lg">
            <div class="flex flex-col gap-6 p-6 sm:p-10">

                {{-- Logo --}}
                <div class="flex justify-center">
                    <x-auth.logo />
                </div>

                <header class="flex flex-col gap-2 text-center">
                    <h1 class="text-2xl font-bold leading-[1.2] text-text-primary">Number Login</h1>
                    <p class="text-sm font-medium leading-[1.5] text-text-primary/60">
                        Enter your WhatsApp number and the Number Access password set by your account owner to access the Inbox for that number.
                    </p>
                </header>

                {{-- Errors --}}
                @if ($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('line.login.submit') }}" class="flex flex-col gap-5">
                    @csrf

                    {{-- Phone number --}}
                    <div class="flex flex-col gap-1.5">
                        <label for="phone" class="text-sm font-semibold text-text-primary">
                            WhatsApp Number <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="phone"
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="+91 98765 43210"
                            autocomplete="tel"
                            required
                            autofocus
                            class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('phone') border-red-400 @enderror"
                        >
                        @error('phone')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-sm font-semibold text-text-primary">
                            Number Access Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        >
                    </div>

                    <button
                        type="submit"
                        class="mt-2 w-full rounded-xl bg-green-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2"
                    >
                        Open Inbox
                    </button>
                </form>

                <p class="text-center text-xs text-text-muted">
                    This is a restricted access link. If you don't know your number password, contact the account owner.
                </p>

            </div>
        </div>
    </main>
</x-layouts.guest>
