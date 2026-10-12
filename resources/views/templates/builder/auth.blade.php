@php
  $auth = $payload['auth'] ?? [];
  $autoFill = old('auto_fill', $auth['auto_fill'] ?? false);
  $expirationOn = old('expiration_time', $auth['expiration_time'] ?? false);
  $validityOn = old('message_validity', $auth['message_validity'] ?? false);
@endphp

<x-templates.builder-layout active="auth" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null" identity-form-id="builder-auth-form">
  <form id="builder-auth-form" method="post" action="{{ route('templates.builder.auth.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-6">
      <div>
        <h2 class="text-base font-semibold text-text-body">Authentication template settings</h2>
        <p class="mt-1 text-sm text-text-subtle">WhatsApp OTP templates use a fixed body format. Preview updates as you change these options.</p>
      </div>

      <div class="flex flex-col gap-4 border-b border-border pb-4">
        <h3 class="text-sm font-semibold text-text-body">Security settings</h3>

        <label class="flex items-center gap-2">
          <input type="checkbox" name="add_secret_recommendation" value="1" class="size-4 rounded" @checked(old('add_secret_recommendation', $auth['add_secret_recommendation'] ?? false))>
          <span class="text-sm text-text-body">Add security recommendation to the message body</span>
        </label>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <label class="flex items-center gap-2">
            <input type="checkbox" name="expiration_time" value="1" class="size-4 rounded" @checked($expirationOn)>
            <span class="text-sm text-text-body">Code expiration in footer</span>
          </label>
          <div data-auth-expiration-fields @class(['flex flex-col gap-2 sm:flex-row sm:items-center', 'hidden' => ! $expirationOn])>
            <input id="expiration_minutes" name="expiration_minutes" type="number" min="1" max="90" value="{{ old('expiration_minutes', $auth['expiration_minutes'] ?? 10) }}" class="fd-input w-full max-w-[120px] rounded-xl border border-border p-3.5">
            <span class="text-sm text-text-subtle">minutes</span>
          </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <label class="flex items-center gap-2">
            <input type="checkbox" name="message_validity" value="1" class="size-4 rounded" @checked($validityOn)>
            <span class="text-sm text-text-body">Message validity period</span>
          </label>
          <div data-auth-validity-fields @class(['flex flex-col gap-2 sm:flex-row sm:items-center', 'hidden' => ! $validityOn])>
            <input id="validity_seconds" name="validity_seconds" type="number" min="30" max="900" value="{{ old('validity_seconds', $auth['validity_seconds'] ?? 120) }}" class="fd-input w-full max-w-[120px] rounded-xl border border-border p-3.5">
            <span class="text-sm text-text-subtle">seconds (30–900)</span>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-4">
        <h3 class="text-sm font-semibold text-text-body">Button &amp; Android delivery</h3>

        <label class="flex items-center gap-2">
          <input type="checkbox" name="auto_fill" value="1" class="size-4 rounded" @checked($autoFill)>
          <span class="text-sm text-text-body">Enable one-tap autofill (Android)</span>
        </label>

        <div data-auth-copy-row @class(['flex flex-col gap-2', 'hidden' => $autoFill])>
          <label for="copy_button_text" class="fd-label">Copy code button text<span class="text-[red]">*</span></label>
          <input id="copy_button_text" name="copy_button_text" type="text" maxlength="25" value="{{ old('copy_button_text', $auth['copy_button_text'] ?? 'Copy code') }}" class="fd-input w-full max-w-md rounded-xl border border-border p-3.5" @required(! $autoFill)>
        </div>

        <div data-auth-autofill-fields @class(['flex flex-col gap-4', 'hidden' => ! $autoFill])>
          <div data-auth-zero-tap-row class="flex flex-col gap-2">
            <label class="flex items-center gap-2">
              <input type="checkbox" name="zero_tap" value="1" class="size-4 rounded" @checked(old('zero_tap', $auth['zero_tap'] ?? false))>
              <span class="text-sm text-text-body">Enable zero-tap (automatic code delivery)</span>
            </label>
          </div>

          <div class="flex flex-col gap-2">
            <label for="fill_button_text" class="fd-label">Autofill button text</label>
            <input id="fill_button_text" name="fill_button_text" type="text" maxlength="25" value="{{ old('fill_button_text', $auth['fill_button_text'] ?? 'Autofill') }}" class="fd-input w-full max-w-md rounded-xl border border-border p-3.5">
          </div>

          <div id="auth-android-apps" class="flex flex-col gap-3" data-auth-apps='@json(old('supported_apps', $auth['supported_apps'] ?? []))'>
            <p class="text-sm font-semibold text-text-body">Supported Android apps</p>
            <div data-auth-apps-list class="flex flex-col gap-2"></div>
            <button type="button" data-auth-add-app class="fd-btn-sm w-fit rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Add app</button>
          </div>
        </div>
      </div>
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>
</x-templates.builder-layout>
