<x-layouts.guest title="Reset Password - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[520px] rounded-[24px] bg-elevated p-8 sm:p-12">
            <x-auth.logo />

            <header class="mt-12 flex flex-col gap-4">
                <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Choose a new password</h1>
                <p class="text-base font-medium leading-[1.5] text-text-primary/54">Enter your new password below.</p>
            </header>

            <form action="{{ route('password.update') }}" method="POST" class="mt-12 flex flex-col gap-8">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <x-form.input
                    id="email"
                    name="email"
                    type="email"
                    :value="old('email', $email)"
                    :error="$errors->first('email')"
                    required
                >
                    <x-slot:label>Email address</x-slot:label>
                </x-form.input>

                <x-form.input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="New password"
                    :error="$errors->first('password')"
                    required
                >
                    <x-slot:label>New password</x-slot:label>
                </x-form.input>

                <x-form.input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="Confirm password"
                    required
                >
                    <x-slot:label>Confirm password</x-slot:label>
                </x-form.input>

                <x-ui.button type="submit" class="rounded-xl p-3.5">Reset password</x-ui.button>
            </form>
        </div>
    </main>
</x-layouts.guest>
