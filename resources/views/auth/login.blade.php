<x-layouts.guest title="Login - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[1440px] overflow-hidden rounded-[24px] bg-elevated">
            <div class="flex flex-col gap-4 p-4 sm:p-8 lg:flex-row lg:items-stretch">
                <section class="flex min-h-[960px] flex-1 flex-col overflow-hidden rounded-2xl p-6 sm:p-10 lg:p-20">
                    <x-auth.logo />

                    <header class="mt-20 flex max-w-[520px] flex-col gap-4">
                        <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Login to Dashboard</h1>
                        <p class="text-base font-medium leading-[1.5] text-text-primary/54">Fill the below form to login</p>
                    </header>

                    <div class="mt-20 flex max-w-[520px] flex-col gap-12">
                        <x-ui.tab-toggle
                            :tabs="[
                                ['label' => 'Email', 'target' => 'email-form', 'href' => route('login')],
                                ['label' => 'Mobile Number', 'target' => 'mobile-form', 'href' => route('login', ['tab' => 'mobile'])],
                            ]"
                            :active="$activeTab ?? 0"
                        />

                        <div id="email-form" data-tab-panel @class(['flex flex-col gap-12', 'hidden' => ($activeTab ?? 0) === 1])>
                            <form action="{{ route('login.store') }}" method="POST" class="flex flex-col gap-12">
                                @csrf

                                @if (session('status'))
                                    <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                @if ($errors->any())
                                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
                                        {{ $errors->first() }}
                                    </div>
                                @endif

                                <div class="flex flex-col gap-8">
                                    <x-form.input
                                        id="email"
                                        name="email"
                                        type="email"
                                        placeholder="Your email"
                                        autocomplete="email"
                                        :value="old('email')"
                                        :error="$errors->first('email')"
                                        required
                                    >
                                        <x-slot:label>Email address</x-slot:label>
                                    </x-form.input>

                                    <x-form.input
                                        id="password"
                                        name="password"
                                        type="password"
                                        placeholder="Password"
                                        autocomplete="current-password"
                                        required
                                    >
                                        <x-slot:label>Password</x-slot:label>
                                        <x-slot:suffix>
                                            <button type="button" data-password-toggle class="flex size-5 items-center justify-center" aria-label="Toggle password visibility">
                                                <img src="{{ asset('images/auth/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                                            </button>
                                        </x-slot:suffix>
                                    </x-form.input>

                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input type="checkbox" name="remember" value="1" class="sr-only" @checked(old('remember'))>
                                            <img src="{{ asset('images/auth/tick-square.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
                                            <span class="text-sm font-medium leading-[1.4] text-text-muted">Remember me</span>
                                        </label>

                                        <p class="text-sm font-medium leading-[1.4] text-text-muted">
                                            Forgot your password?
                                            <a href="{{ route('password.request') }}" class="font-semibold text-primary-2 underline decoration-solid underline-offset-2">Reset</a>
                                        </p>
                                    </div>
                                </div>

                                <x-ui.button type="submit" class="rounded-xl p-3.5">Sign in</x-ui.button>
                            </form>
                        </div>

                        <div id="mobile-form" data-tab-panel @class(['flex flex-col gap-12', 'hidden' => ($activeTab ?? 0) !== 1])>
                            <form action="{{ route('login.otp.verify') }}" method="POST" class="flex flex-col gap-12" data-login-otp-form>
                                @csrf

                                @if ($errors->has('otp') || $errors->has('phone'))
                                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
                                        {{ $errors->first('otp') ?: $errors->first('phone') }}
                                    </div>
                                @endif

                                <p class="hidden text-sm font-medium text-green-600" data-login-otp-success></p>
                                <p class="hidden text-sm font-medium text-red-600" data-login-otp-error></p>

                                <div class="flex flex-col gap-6">
                                    <div class="flex flex-col gap-3">
                                        <x-form.label for="phone">Phone Number</x-form.label>
                                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated px-3.5 py-3.5">
                                            <input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                placeholder="10-digit mobile number"
                                                value="{{ old('phone') }}"
                                                class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:outline-none"
                                                autocomplete="tel"
                                                data-login-otp-phone
                                                required
                                            >
                                            <button
                                                type="button"
                                                class="shrink-0 rounded-xl bg-green-600 px-2 py-1 text-[10px] font-semibold leading-[1.2] text-white disabled:opacity-60"
                                                data-login-otp-send
                                                data-send-url="{{ route('login.otp.send') }}"
                                            >
                                                Send OTP
                                            </button>
                                        </div>
                                        <p class="hidden text-xs font-medium text-text-muted" data-login-otp-timer></p>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <x-form.label for="otp-1">Enter OTP</x-form.label>
                                        <div class="flex gap-3" data-otp-inputs data-login-otp-inputs>
                                            @for ($i = 1; $i <= 6; $i++)
                                                <input
                                                    id="otp-{{ $i }}"
                                                    type="text"
                                                    inputmode="numeric"
                                                    maxlength="1"
                                                    placeholder="-"
                                                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-center text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                                                    data-login-otp-digit
                                                >
                                            @endfor
                                        </div>
                                        <input type="hidden" name="otp" value="{{ old('otp') }}" data-login-otp-hidden>
                                    </div>

                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input type="checkbox" name="remember" value="1" class="sr-only" @checked(old('remember'))>
                                        <img src="{{ asset('images/auth/tick-square.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
                                        <span class="text-sm font-medium leading-[1.4] text-text-muted">Remember me</span>
                                    </label>
                                </div>

                                <x-ui.button type="submit" class="rounded-xl p-3.5">Verify OTP</x-ui.button>
                            </form>
                        </div>

                        <p class="text-sm font-medium leading-[1.4] text-text-primary/60" style="font-family: var(--font-display)">
                            Don't have an account yet?
                            <a href="{{ route('signup.step-1') }}" class="font-extrabold text-green-500 underline">Sign Up</a>
                        </p>
                    </div>
                </section>

                <x-auth.features-panel />
            </div>
        </div>
    </main>
</x-layouts.guest>
