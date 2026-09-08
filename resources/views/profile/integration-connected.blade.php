<x-profile.layout title="Modify Phone Business Profile - WapApp" headerTitle="Integration" active="profile.integration">
  <div class="flex flex-col p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Modify Phone Business Profile</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Update your WABA business profile for {{ $line->display_name }}.
      </p>
    </div>
  </div>

  <section class="flex flex-col gap-4 p-4 pt-0">
    <form method="POST" action="{{ route('profile.integration.update', $line) }}" enctype="multipart/form-data" class="rounded-lg bg-elevated p-3">
      @csrf

      <div class="flex flex-col gap-4">
        <div class="flex w-full max-w-[564.5px] flex-col gap-2">
          <label for="email" class="text-base font-semibold text-text-primary">Business Email <span class="text-[red]">*</span></label>
          <input id="email" name="email" type="email" value="{{ old('email', $profile['email'] ?? '') }}" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
          @error('email')<p class="text-xs text-[red]">{{ $message }}</p>@enderror
        </div>

        <div class="flex w-full max-w-[318px] flex-col gap-2">
          <h2 class="text-base font-semibold text-text-primary">Business Logo</h2>
          <div class="flex w-fit flex-col gap-3 rounded-xl border border-border bg-elevated p-3">
            <img
              src="{{ isset($profile['logo_path']) ? asset('storage/'.$profile['logo_path']) : asset('images/profile/business-logo.png') }}"
              alt=""
              class="size-40 rounded border border-green-100 object-cover"
            >
            <input type="file" name="logo" accept="image/*" class="text-xs">
            <label class="flex items-center gap-2 text-xs text-text-muted">
              <input type="checkbox" name="remove_logo" value="1"> Remove logo
            </label>
          </div>
        </div>

        <div class="flex w-full max-w-[564.5px] flex-col gap-2">
          <label for="website" class="text-base font-semibold text-text-primary">Website URL <span class="text-[red]">*</span></label>
          <input id="website" name="website" type="url" value="{{ old('website', $profile['website'] ?? '') }}" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
        </div>

        <div class="flex w-full max-w-[564.5px] flex-col gap-2">
          <label for="address" class="text-base font-semibold text-text-primary">Address <span class="text-[red]">*</span></label>
          <input id="address" name="address" type="text" value="{{ old('address', $profile['address'] ?? '') }}" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
        </div>

        <div class="flex w-full max-w-[564.5px] flex-col gap-2">
          <label for="description" class="text-base font-semibold text-text-primary">Description <span class="text-[red]">*</span></label>
          <textarea id="description" name="description" rows="4" required class="h-[122px] w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">{{ old('description', $profile['description'] ?? '') }}</textarea>
        </div>

        <div class="flex w-full max-w-[564.5px] flex-col gap-2">
          <label for="about" class="text-base font-semibold text-text-primary">About</label>
          <input id="about" name="about" type="text" value="{{ old('about', $profile['about'] ?? '') }}" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
        </div>
      </div>

      <div class="mt-4 flex items-center justify-end gap-3">
        <a href="{{ route('profile.integration') }}" class="text-sm font-semibold text-text-muted">Cancel</a>
        <button type="submit" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold text-primary-2">Update</button>
      </div>
    </form>
  </section>
</x-profile.layout>
