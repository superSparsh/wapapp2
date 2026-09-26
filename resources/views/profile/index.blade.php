<x-profile.layout title="My Profile - WapApp" active="profile.index">
  <div class="flex flex-col gap-1">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">My Profile</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Manage your personal information, preferences, and account credentials.
    </p>
  </div>

  @if (session('status'))
    <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      <ul class="list-disc space-y-1 pl-4">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-6 flex flex-col gap-4" data-profile-form>
    @csrf

    <section class="rounded-lg bg-elevated p-3">
      <div class="flex flex-col gap-4">
        <div class="flex w-full max-w-[318px] flex-col gap-2">
          <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Profile Photo</h2>
          <div class="flex w-fit flex-col gap-3 rounded-xl border border-border bg-elevated p-3">
            <img
              id="profile-avatar-preview"
              src="{{ $profile->avatarUrl ?? asset('images/profile/photo-sample.png') }}"
              alt=""
              class="size-40 rounded border border-green-100 object-cover"
              width="160"
              height="160"
              data-original-src="{{ $profile->avatarUrl ?? asset('images/profile/photo-sample.png') }}"
            >
            <p id="profile-avatar-error" class="hidden text-xs font-medium text-red-600" role="alert"></p>
            @error('avatar')
              <p class="text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
            <div class="flex w-full items-center justify-between gap-3">
              <label class="cursor-pointer text-xs font-semibold leading-[1.4] text-primary-2 underline">
                Change
                <input
                  type="file"
                  name="avatar"
                  id="profile-avatar-input"
                  accept="image/png,image/jpeg,image/webp"
                  class="sr-only"
                  data-max-kb="{{ (int) config('account.avatar.max_kb', 2048) }}"
                  data-max-mb="{{ $avatarMaxMb ?? 2 }}"
                >
              </label>
              <label class="flex cursor-pointer items-center gap-1 text-xs font-semibold text-text-muted">
                <input type="checkbox" name="remove_avatar" value="1" class="size-3" id="profile-avatar-remove">
                Remove
              </label>
            </div>
            <p class="text-[11px] text-text-muted">JPG, PNG or WebP. Max {{ $avatarMaxMb ?? 2 }} MB. Click Save to apply.</p>
          </div>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
          <div class="flex min-w-0 flex-1 flex-col gap-2">
            <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Basic information</h2>

            <div class="grid gap-3 sm:grid-cols-2">
              <x-form.input id="first_name" name="first_name" type="text" placeholder="Enter First name" :value="old('first_name', $profile->firstName)" required>
                <x-slot:label>First Name <span class="text-[red]">*</span></x-slot:label>
              </x-form.input>
              <x-form.input id="last_name" name="last_name" type="text" placeholder="Enter Last name" :value="old('last_name', $profile->lastName)" required>
                <x-slot:label>Last Name <span class="text-[red]">*</span></x-slot:label>
              </x-form.input>
            </div>

            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-2">
                <label for="timezone" class="text-sm font-semibold leading-[1.4] text-text-primary">Time zone</label>
                <select id="timezone" name="timezone" @disabled(! $profile->canEditTenantPreferences) class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                  @foreach ($timezones as $value => $label)
                    <option value="{{ $value }}" @selected(old('timezone', $profile->timezone) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="flex flex-col gap-2">
                <label for="country_code" class="text-sm font-semibold leading-[1.4] text-text-primary">Country</label>
                <select id="country_code" name="country_code" @disabled(! $profile->canEditTenantPreferences) class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                  @foreach ($countries as $value => $label)
                    <option value="{{ $value }}" @selected(old('country_code', $profile->countryCode) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="flex flex-col gap-2">
                <label for="locale" class="text-sm font-semibold leading-[1.4] text-text-primary">Language</label>
                <select id="locale" name="locale" @disabled(! $profile->canEditTenantPreferences) class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                  @foreach ($locales as $value => $label)
                    <option value="{{ $value }}" @selected(old('locale', $profile->locale) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <div class="flex min-w-0 flex-1 flex-col gap-2">
            <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Account</h2>

            <x-form.input id="email" name="email" type="email" placeholder="Enter Email" :value="old('email', $profile->email)" required>
              <x-slot:label>Email <span class="text-[red]">*</span></x-slot:label>
            </x-form.input>

            <div class="flex flex-col gap-3">
              <x-form.input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                <x-slot:label>New password</x-slot:label>
                <x-slot:suffix>
                  <button type="button" data-password-toggle class="flex size-5 items-center justify-center" aria-label="Toggle password visibility">
                    <img src="{{ asset('images/profile/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                  </button>
                </x-slot:suffix>
              </x-form.input>
              <x-form.input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Only required when changing password">
                <x-slot:label>Confirm new password</x-slot:label>
                <x-slot:suffix>
                  <button type="button" data-password-toggle class="flex size-5 items-center justify-center" aria-label="Toggle password visibility">
                    <img src="{{ asset('images/profile/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                  </button>
                </x-slot:suffix>
              </x-form.input>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="flex items-center justify-end">
      <x-ui.button type="submit" class="rounded px-6 py-3 text-xs">Save</x-ui.button>
    </div>
  </form>

  <script>
    (function () {
      const input = document.getElementById('profile-avatar-input');
      const preview = document.getElementById('profile-avatar-preview');
      const errorEl = document.getElementById('profile-avatar-error');
      const removeCb = document.getElementById('profile-avatar-remove');
      if (!input || !preview) return;

      let objectUrl = null;

      function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message || '';
        errorEl.classList.toggle('hidden', !message);
      }

      function resetPreview() {
        if (objectUrl) {
          URL.revokeObjectURL(objectUrl);
          objectUrl = null;
        }
        preview.src = preview.dataset.originalSrc || preview.src;
      }

      input.addEventListener('change', function () {
        showError('');
        const file = input.files && input.files[0];
        if (!file) {
          resetPreview();
          return;
        }

        const maxKb = Number(input.dataset.maxKb || 2048);
        const maxMb = input.dataset.maxMb || String(Math.round(maxKb / 1024));
        if (file.size > maxKb * 1024) {
          input.value = '';
          resetPreview();
          showError('Avatar must not be greater than ' + maxMb + ' MB.');
          return;
        }

        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
          input.value = '';
          resetPreview();
          showError('Avatar must be a JPG, PNG, or WebP image.');
          return;
        }

        if (removeCb) removeCb.checked = false;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
      });

      if (removeCb) {
        removeCb.addEventListener('change', function () {
          if (removeCb.checked) {
            input.value = '';
            showError('');
            resetPreview();
          }
        });
      }
    })();
  </script>
</x-profile.layout>
