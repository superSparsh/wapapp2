@php
    $variant = $variant ?? 'default';
    $titles = [
        'default' => 'Create your account',
        'business-details' => 'Business Details',
        'whatsapp' => 'WhatsApp connection',
        'success' => 'Successfully connected',
    ];
    $subtitles = [
        'default' => 'Welcome to our WhatsApp Automation Platform! Simplify your sales and marketing tasks effortlessly. Join us now to get started and enjoy seamless sales & marketing experiences, hassle-free!',
        'business-details' => 'Please provide your business details to complete registration.',
        'whatsapp' => 'Connect your WhatsApp Business account to start messaging.',
        'success' => 'Your WhatsApp Business account has been connected successfully.',
    ];
@endphp

<x-auth.signup-layout
    :show-stepper="false"
    :title="$titles[$variant] ?? $titles['default']"
    :subtitle="$subtitles[$variant] ?? $subtitles['default']"
    account-prompt="If you already have an account?"
    account-action="Sign In"
    :account-href="route('login')"
>
    @if ($variant === 'success')
        <div class="flex flex-col items-center gap-6 py-8 text-center">
            <div class="flex size-20 items-center justify-center rounded-full bg-green-50">
                <img src="{{ asset('images/icons/tick-circle.svg') }}" alt="" class="size-10" width="40" height="40" onerror="this.style.display='none'">
                <span class="text-4xl text-green-500">✓</span>
            </div>
            <p class="text-base font-medium leading-[1.5] text-text-primary/70">You can now access your dashboard and start using WhatsApp automation features.</p>
        </div>
        <x-slot:footer>
            <x-ui.auth-footer-buttons
                :back-href="route('signup.register', ['variant' => 'whatsapp'])"
                back-label="Go Back"
                :next-href="route('signup.email')"
                next-label="Continue"
            />
        </x-slot:footer>
    @elseif ($variant === 'whatsapp')
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-border bg-elevated p-6">
                <p class="fd-label mb-4">Connect WhatsApp Business API</p>
                <p class="fd-page-note mb-6 !opacity-100">Click the button below to authorize WhatsApp Business connection via Meta.</p>
                <x-ui.button type="button" class="!w-auto px-8">Connect WhatsApp</x-ui.button>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-green-500 bg-green-50 p-4">
                <span class="size-3 shrink-0 rounded-full bg-green-500"></span>
                <p class="text-sm font-medium text-text-subtle">Status: Pending connection</p>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.auth-footer-buttons
                :back-href="route('signup.register', ['variant' => 'business-details'])"
                back-label="Go Back"
                :next-href="route('signup.register', ['variant' => 'success'])"
                next-label="Connect"
            />
        </x-slot:footer>
    @elseif ($variant === 'business-details')
        <form action="{{ route('signup.register.store') }}" method="POST" class="flex flex-col gap-6">
            @csrf
            <x-form.input id="business_name" name="business_name" placeholder="Enter Business Name" :value="old('business_name')" :error="$errors->first('business_name')" required>
                <x-slot:label>Business Name <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <x-form.input id="business_website" name="business_website" type="url" placeholder="https://example.com" required>
                <x-slot:label>Business Website <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <div class="flex flex-col gap-3">
                <x-form.label for="industry">Industry <span class="text-red-500">*</span></x-form.label>
                <div class="flex items-center justify-between rounded-xl border border-border bg-elevated px-3.5 py-3.5">
                    <span class="fd-input">Select industry</span>
                    <svg class="size-4 text-text-muted" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <x-form.input id="business_address" name="business_address" placeholder="Enter Business Address" :value="old('business_address')" required>
                <x-slot:label>Business Address <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>

            <x-ui.button type="submit" class="rounded-xl p-3.5">Complete registration</x-ui.button>
        </form>
        <x-slot:footer>
            <x-ui.link-button href="{{ route('signup.register') }}" variant="outline" class="rounded-xl p-3.5">Go Back</x-ui.link-button>
        </x-slot:footer>
    @else
        <form action="{{ route('signup.register.profile') }}" method="POST" class="flex flex-col gap-6">
            @csrf
            <x-form.input id="work_email" name="work_email" type="email" placeholder="Enter your Work Email" :value="old('work_email')" :error="$errors->first('work_email')" required>
                <x-slot:label>Work Email <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <x-form.input id="first_name" name="first_name" placeholder="Enter your First Name" required>
                    <x-slot:label>First name <span class="text-red-500">*</span></x-slot:label>
                </x-form.input>
                <x-form.input id="last_name" name="last_name" placeholder="Enter your Last Name" required>
                    <x-slot:label>Last name <span class="text-red-500">*</span></x-slot:label>
                </x-form.input>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-[160px_1fr]">
                <div class="flex flex-col gap-3">
                    <x-form.label for="country">Country <span class="text-red-500">*</span></x-form.label>
                    <div class="flex items-center justify-between rounded-xl border border-border bg-elevated px-3.5 py-3.5">
                        <span class="fd-input">Select country</span>
                        <svg class="size-4 text-text-muted" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <x-form.input id="mobile" name="mobile" type="tel" placeholder="Enter your Mobile Number" required>
                    <x-slot:label>Mobile Number <span class="text-red-500">*</span></x-slot:label>
                </x-form.input>
            </div>

            <x-form.input id="new_password" name="new_password" type="password" placeholder="Password field" required>
                <x-slot:label>New password <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>

            <x-form.input id="signature" name="signature" placeholder="Sign Here" required>
                <x-slot:label>Sign Here <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>

            <p class="text-sm font-medium leading-[1.4] text-text-primary/60">Note: Please try to keep your signature in a single line, if possible.</p>

            <label class="flex cursor-pointer items-center gap-1.5">
                <input type="checkbox" name="whatsapp_addendum" value="1" class="sr-only" required @checked(old('whatsapp_addendum'))>
                <img src="{{ asset('images/auth/tick-square.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
                <span class="text-sm font-medium leading-[1.4] text-text-muted">I agree to WhatsApp Business Program Addendum.</span>
            </label>

            <x-ui.button type="submit" class="rounded-xl p-3.5">Register Now</x-ui.button>
        </form>

        <x-slot:footer>
            <x-ui.link-button href="{{ route('signup.step-5', ['modal' => 'info']) }}" variant="outline" class="rounded-xl p-3.5">Go Back</x-ui.link-button>
        </x-slot:footer>
    @endif
</x-auth.signup-layout>
