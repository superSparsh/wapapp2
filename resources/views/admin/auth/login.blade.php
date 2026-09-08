<x-layouts.guest title="Admin Login - WapApp">
  <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-md overflow-hidden rounded-[24px] bg-elevated p-8 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
      <x-auth.logo />
      <header class="mt-10 flex flex-col gap-2">
        <h1 class="text-[28px] font-bold leading-[1.2] text-text-primary">Admin login</h1>
        <p class="text-sm font-medium text-text-primary/54">Sign in to the platform backoffice</p>
      </header>

      <form action="{{ route('admin.login.store') }}" method="POST" class="mt-10 flex flex-col gap-6">
        @csrf

        @if ($errors->any())
          <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
            {{ $errors->first() }}
          </div>
        @endif

        <x-form.input
          id="email"
          name="email"
          type="email"
          placeholder="admin@wapapp.test"
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
        </x-form.input>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-text-muted">
          <input type="checkbox" name="remember" value="1" class="size-4 rounded border-border" @checked(old('remember'))>
          Remember me
        </label>

        <button type="submit" class="rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold text-white hover:bg-green-600">
          Login to Admin
        </button>
      </form>
    </div>
  </main>
</x-layouts.guest>
