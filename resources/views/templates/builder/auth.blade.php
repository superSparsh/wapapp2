@php
  $auth = $payload['auth'] ?? [];
@endphp

<x-templates.builder-layout active="auth" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null">
  <form method="post" action="{{ route('templates.builder.auth.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-4">
      <p class="text-sm text-text-subtle">Configure authentication template options for WhatsApp OTP messages.</p>

      <div class="flex flex-col gap-2">
        <label for="copy_button_text" class="fd-label">Copy code button text<span class="text-[red]">*</span></label>
        <input id="copy_button_text" name="copy_button_text" type="text" maxlength="25" value="{{ old('copy_button_text', $auth['copy_button_text'] ?? 'Copy Code') }}" class="fd-input w-full rounded-xl border border-border p-3.5" required>
      </div>

      <label class="flex items-center gap-2">
        <input type="checkbox" name="auto_fill" value="1" class="size-4 rounded" @checked(old('auto_fill', $auth['auto_fill'] ?? false))>
        <span class="text-sm text-text-body">Enable one-tap autofill (Android)</span>
      </label>

      <label class="flex items-center gap-2">
        <input type="checkbox" name="zero_tap" value="1" class="size-4 rounded" @checked(old('zero_tap', $auth['zero_tap'] ?? false))>
        <span class="text-sm text-text-body">Enable zero-tap (requires autofill)</span>
      </label>

      <div class="flex flex-col gap-2">
        <label for="fill_button_text" class="fd-label">Autofill button text</label>
        <input id="fill_button_text" name="fill_button_text" type="text" maxlength="25" value="{{ old('fill_button_text', $auth['fill_button_text'] ?? 'Autofill') }}" class="fd-input w-full rounded-xl border border-border p-3.5">
      </div>

      <label class="flex items-center gap-2">
        <input type="checkbox" name="message_validity" value="1" class="size-4 rounded" @checked(old('message_validity', $auth['message_validity'] ?? false))>
        <span class="text-sm text-text-body">Set message validity period</span>
      </label>

      <div class="flex flex-col gap-2">
        <label for="validity_seconds" class="fd-label">Validity (seconds, 30–900)</label>
        <input id="validity_seconds" name="validity_seconds" type="number" min="30" max="900" value="{{ old('validity_seconds', $auth['validity_seconds'] ?? 120) }}" class="fd-input w-full rounded-xl border border-border p-3.5">
      </div>

      <label class="flex items-center gap-2">
        <input type="checkbox" name="expiration_time" value="1" class="size-4 rounded" @checked(old('expiration_time', $auth['expiration_time'] ?? false))>
        <span class="text-sm text-text-body">Add code expiration in footer</span>
      </label>

      <div class="flex flex-col gap-2">
        <label for="expiration_minutes" class="fd-label">Code expiration (minutes)</label>
        <input id="expiration_minutes" name="expiration_minutes" type="number" min="1" max="90" value="{{ old('expiration_minutes', $auth['expiration_minutes'] ?? 10) }}" class="fd-input w-full rounded-xl border border-border p-3.5">
      </div>

      <label class="flex items-center gap-2">
        <input type="checkbox" name="add_secret_recommendation" value="1" class="size-4 rounded" @checked(old('add_secret_recommendation', $auth['add_secret_recommendation'] ?? false))>
        <span class="text-sm text-text-body">Add security recommendation to body</span>
      </label>

      <div id="auth-android-apps" class="flex flex-col gap-3" data-auth-apps='@json(old('supported_apps', $auth['supported_apps'] ?? []))'>
        <p class="text-sm font-semibold text-text-body">Supported Android apps (optional)</p>
        <div data-auth-apps-list class="flex flex-col gap-2"></div>
        <button type="button" data-auth-add-app class="fd-btn-sm w-fit rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Add app</button>
      </div>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="fd-btn-sm rounded bg-green-500 px-4 py-3 text-primary-2">Next</button>
    </div>
  </form>
</x-templates.builder-layout>
