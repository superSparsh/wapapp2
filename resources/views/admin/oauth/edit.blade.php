<x-admin.layout title="OAuth Settings - Admin" active="admin.oauth.edit">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">OAuth logins</h1>
    <p class="text-sm text-text-subtle opacity-70">Social sign-in providers for customer accounts.</p>
  </div>

  <form method="POST" action="{{ route('admin.oauth.update') }}" class="mx-4 mb-8 max-w-3xl space-y-4">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    @foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $provider => $label)
      <section class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold">{{ $label }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold">Enabled</span>
            <select name="oauth_{{ $provider }}_enabled" class="rounded-lg border border-border px-3 py-2">
              <option value="0" @selected(old('oauth_'.$provider.'_enabled', $settings['oauth.'.$provider.'_enabled'] ?? '0') === '0')>No</option>
              <option value="1" @selected(old('oauth_'.$provider.'_enabled', $settings['oauth.'.$provider.'_enabled'] ?? '0') === '1')>Yes</option>
            </select>
          </label>
          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold">Client ID</span>
            <input name="oauth_{{ $provider }}_client_id" value="{{ old('oauth_'.$provider.'_client_id', $settings['oauth.'.$provider.'_client_id'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
          </label>
          <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
            <span class="font-semibold">Client secret</span>
            <input name="oauth_{{ $provider }}_client_secret" value="{{ old('oauth_'.$provider.'_client_secret', $settings['oauth.'.$provider.'_client_secret'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
          </label>
        </div>
      </section>
    @endforeach

    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save OAuth settings</button>
  </form>
</x-admin.layout>
