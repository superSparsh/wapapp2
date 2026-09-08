<x-layouts.guest title="Forgot Password - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[520px] rounded-[24px] bg-elevated p-8 sm:p-12">
            <x-auth.logo />

            <header class="mt-12 flex flex-col gap-4">
                <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Reset your password</h1>
                <p class="text-base font-medium leading-[1.5] text-text-primary/54">Enter your email and we will send you a reset link.</p>
            </header>

            @if (session('status'))
                <div class="mt-8 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('password.email') }}" method="POST" class="mt-12 flex flex-col gap-8">
                @csrf

                <x-form.input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="you@business.com"
                    :value="old('email')"
                    :error="$errors->first('email')"
                    required
                >
                    <x-slot:label>Email address</x-slot:label>
                </x-form.input>

                <x-ui.button type="submit" class="rounded-xl p-3.5">Send reset link</x-ui.button>
            </form>

            <p class="mt-8 text-sm font-medium text-text-primary/60">
                <a href="{{ route('login') }}" class="font-extrabold text-green-500 underline">Back to login</a>
            </p>
        </div>
    </main>
</x-layouts.guest>
