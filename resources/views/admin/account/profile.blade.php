<x-admin.layout title="My Profile — Admin" active="admin.account.profile">
  <div class="mx-auto max-w-2xl p-6">
    <h1 class="text-2xl font-semibold text-text-primary">My Profile</h1>
    <p class="mt-1 text-sm text-text-subtle">Update your admin account details.</p>

    <form method="POST" action="{{ route('admin.account.update') }}" class="mt-6 space-y-4 rounded-xl border border-border bg-elevated p-5">
      @csrf
      @method('PUT')

      <div>
        <label for="name" class="mb-1 block text-sm font-medium text-text-primary">Name</label>
        <input
          id="name"
          name="name"
          type="text"
          value="{{ old('name', $admin->name) }}"
          required
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
        @error('name')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="email" class="mb-1 block text-sm font-medium text-text-primary">Email</label>
        <input
          id="email"
          name="email"
          type="email"
          value="{{ old('email', $admin->email) }}"
          required
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
        @error('email')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="password" class="mb-1 block text-sm font-medium text-text-primary">New password</label>
        <input
          id="password"
          name="password"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
          placeholder="Leave blank to keep current"
        >
        @error('password')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-text-primary">Confirm password</label>
        <input
          id="password_confirmation"
          name="password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
      </div>

      <div class="flex items-center justify-between gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
          Save changes
        </button>
      </div>
    </form>

    <form method="POST" action="{{ route('admin.logout') }}" class="mt-6">
      @csrf
      <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100">
        Logout
      </button>
    </form>
  </div>
</x-admin.layout>
