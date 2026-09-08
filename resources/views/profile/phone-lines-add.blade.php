<x-profile.layout title="Add WhatsApp Number - WapApp" headerTitle="Phone Numbers" active="profile.phone-lines.index">

  @if (session('error'))
    <div class="mx-4 mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ session('error') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="mx-4 mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="flex flex-col gap-1 p-4">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Add New Number</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Register an additional WhatsApp number under your connected business space
      ({{ $defaultLine->displayPhone() }}).
    </p>
  </div>

  <section class="mx-4 mb-8 max-w-xl rounded-[20px] border border-border bg-elevated p-5">
    <form id="addChatappPhoneForm" method="POST" action="{{ route('profile.phone-lines.add.store') }}" class="flex flex-col gap-4">
      @csrf

      <div class="flex flex-col gap-1.5">
        <label for="country_code" class="text-sm font-semibold text-text-primary">Country code <span class="text-red-500">*</span></label>
        <input
          type="text"
          id="country_code"
          name="country_code"
          maxlength="4"
          inputmode="numeric"
          autocomplete="tel-country-code"
          placeholder="91"
          value="{{ old('country_code', '91') }}"
          required
          class="rounded-lg border border-border px-3 py-2 text-sm @error('country_code') border-red-400 @enderror"
        >
        <p class="text-xs text-text-subtle">Digits only, without +</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="phone_number" class="text-sm font-semibold text-text-primary">Mobile number <span class="text-red-500">*</span></label>
        <input
          type="text"
          id="phone_number"
          name="phone_number"
          inputmode="numeric"
          autocomplete="tel-national"
          placeholder="9876543210"
          value="{{ old('phone_number') }}"
          required
          class="rounded-lg border border-border px-3 py-2 text-sm @error('phone_number') border-red-400 @enderror"
        >
        <p class="text-xs text-text-subtle">National number only (no country code).</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="verified_name" class="text-sm font-semibold text-text-primary">WhatsApp display name <span class="text-red-500">*</span></label>
        <input
          type="text"
          id="verified_name"
          name="verified_name"
          maxlength="128"
          placeholder="Display name"
          value="{{ old('verified_name') }}"
          required
          class="rounded-lg border border-border px-3 py-2 text-sm @error('verified_name') border-red-400 @enderror"
        >
      </div>

      <div class="flex items-center gap-3 pt-2">
        <a href="{{ route('profile.phone-lines.index') }}" class="text-sm font-medium text-text-subtle hover:text-text-primary">Cancel</a>
        <button
          type="submit"
          id="addPhoneSubmit"
          class="inline-flex items-center rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600"
        >
          Save
        </button>
      </div>
    </form>
  </section>

  <script>
    document.getElementById('addChatappPhoneForm')?.addEventListener('submit', function () {
      var btn = document.getElementById('addPhoneSubmit');
      if (btn) btn.disabled = true;
    });
  </script>

</x-profile.layout>
