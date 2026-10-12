@php
  $auth = $payload['auth'] ?? [];
  $autoFill = (bool) old('auto_fill', $auth['auto_fill'] ?? false);
  $zeroTap = (bool) old('zero_tap', $auth['zero_tap'] ?? false);
  $expirationOn = (bool) old('expiration_time', $auth['expiration_time'] ?? false);
  $validityOn = (bool) old('message_validity', $auth['message_validity'] ?? false);
  $suggestions = (bool) old('add_secret_recommendation', $auth['add_secret_recommendation'] ?? false);
  $apps = old('supported_apps', $auth['supported_apps'] ?? []);
@endphp

<x-templates.builder-layout
  active="auth"
  :card="false"
  :template="$template"
  :payload="$payload"
  :preview-data="$previewData ?? null"
  :setup-complete="$setupComplete ?? true"
  :builder-steps="$builderSteps ?? null"
  identity-form-id="builder-auth-form"
>
  <form
    id="builder-auth-form"
    method="post"
    action="{{ route('templates.builder.auth.save', $template) }}"
    class="flex flex-col gap-4"
    data-validate-form
    data-auth-builder
  >
    @csrf

    <div class="rounded-lg border border-border bg-elevated p-5 flex flex-col gap-6 shadow-sm">
      <div>
        <h2 class="text-lg font-semibold text-text-body">Authentication Template Settings</h2>
        <p class="mt-1 text-sm text-text-subtle">Configure OTP delivery options. The phone preview updates in real time.</p>
      </div>

      {{-- Security Settings (legacy parity) --}}
      <div class="flex flex-col gap-4">
        <h3 class="text-base font-semibold text-text-body">Security Settings</h3>

        <div class="flex items-center justify-between gap-4">
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-sm font-semibold text-text-body">Security Recommendations</span>
            <span class="text-xs text-text-subtle">Adds a security disclaimer to your message</span>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" name="add_secret_recommendation" value="1" class="peer sr-only" data-auth-toggle="suggestions" @checked($suggestions)>
            <span class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full bg-green-50 shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors peer-checked:bg-green-500 peer-checked:[&>span]:left-[24px]">
              <span class="pointer-events-none absolute top-[2px] left-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]"></span>
            </span>
          </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-1 items-center justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="text-sm font-semibold text-text-body">Code Expiration</span>
              <span class="text-xs text-text-subtle">Adds an expiration notice to your message footer</span>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
              <input type="checkbox" name="expiration_time" value="1" class="peer sr-only" data-auth-toggle="expiration" @checked($expirationOn)>
              <span class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full bg-green-50 shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors peer-checked:bg-green-500 peer-checked:[&>span]:left-[24px]">
                <span class="pointer-events-none absolute top-[2px] left-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]"></span>
              </span>
            </label>
          </div>
          <div data-auth-expiration-fields @class(['flex items-center gap-2', 'hidden' => ! $expirationOn])>
            <input id="expiration_minutes" name="expiration_minutes" type="number" min="1" max="90" value="{{ old('expiration_minutes', $auth['expiration_minutes'] ?? 10) }}" class="fd-input w-[88px] rounded-xl border border-border p-2.5 text-sm">
            <span class="text-sm text-text-subtle">minutes</span>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-1 items-center justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="text-sm font-semibold text-text-body">Message Validity Period</span>
              <span class="text-xs text-text-subtle">Sets how long the message will be valid (TTL)</span>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
              <input type="checkbox" name="message_validity" value="1" class="peer sr-only" data-auth-toggle="validity" @checked($validityOn)>
              <span class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full bg-green-50 shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors peer-checked:bg-green-500 peer-checked:[&>span]:left-[24px]">
                <span class="pointer-events-none absolute top-[2px] left-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]"></span>
              </span>
            </label>
          </div>
          <div data-auth-validity-fields @class(['flex items-center gap-2', 'hidden' => ! $validityOn])>
            <input id="validity_seconds" name="validity_seconds" type="number" min="30" max="900" step="30" value="{{ old('validity_seconds', $auth['validity_seconds'] ?? 30) }}" class="fd-input w-[88px] rounded-xl border border-border p-2.5 text-sm">
            <span class="text-sm text-text-subtle">seconds</span>
          </div>
        </div>
      </div>

      <div class="border-t border-border"></div>

      {{-- Button Settings (legacy parity) --}}
      <div class="flex flex-col gap-4">
        <h3 class="text-base font-semibold text-text-body">Button Settings</h3>

        <div data-auth-copy-row @class(['flex flex-col gap-2', 'hidden' => $autoFill])>
          <label for="copy_button_text" class="text-sm font-semibold text-text-body">Copy Button Text</label>
          <input
            id="copy_button_text"
            name="copy_button_text"
            type="text"
            maxlength="25"
            value="Copy code"
            readonly
            class="fd-input w-full max-w-[300px] rounded-xl border border-border bg-muted-surface p-3.5 text-text-muted"
          >
        </div>

        <div class="flex items-center justify-between gap-4">
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-sm font-semibold text-text-body">Enable Auto Fill</span>
            <span class="text-xs text-text-subtle">Allows users to autofill the code in supported Android apps</span>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" name="auto_fill" value="1" class="peer sr-only" data-auth-toggle="autofill" @checked($autoFill)>
            <span class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full bg-green-50 shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors peer-checked:bg-green-500 peer-checked:[&>span]:left-[24px]">
              <span class="pointer-events-none absolute top-[2px] left-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]"></span>
            </span>
          </label>
        </div>

        <div data-auth-autofill-fields @class(['flex flex-col gap-4', 'hidden' => ! $autoFill])>
          <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="text-sm font-semibold text-text-body">Enable Zero-tap</span>
              <span class="text-xs text-text-subtle">Automatically fills the code without user interaction</span>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
              <input type="checkbox" name="zero_tap" value="1" class="peer sr-only" data-auth-toggle="zerotap" @checked($zeroTap)>
              <span class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full bg-green-50 shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors peer-checked:bg-green-500 peer-checked:[&>span]:left-[24px]">
                <span class="pointer-events-none absolute top-[2px] left-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]"></span>
              </span>
            </label>
          </div>

          <div data-auth-zero-tap-terms @class(['rounded-lg border border-border bg-muted-surface p-3', 'hidden' => ! $zeroTap])>
            <label class="flex items-start gap-2">
              <input type="checkbox" name="zero_tap_accepted" value="1" class="mt-1 size-4 rounded" @checked(old('zero_tap_accepted', $zeroTap)) required>
              <span class="text-sm text-text-body">
                I understand that the use of zero-tap authentication is subject to WhatsApp Business Terms of Service, and I am responsible to ensure that after customers choose to receive zero-tap verification codes, the codes will be automatically filled in.
              </span>
            </label>
          </div>

          <div data-auth-fill-text-row @class(['flex flex-col gap-2', 'hidden' => $zeroTap])>
            <label for="fill_button_text" class="text-sm font-semibold text-text-body">Autofill Button Text</label>
            <input id="fill_button_text" name="fill_button_text" type="text" maxlength="20" value="{{ old('fill_button_text', $auth['fill_button_text'] ?? 'Autofill') }}" placeholder="e.g., Autofill" class="fd-input w-full max-w-[300px] rounded-xl border border-border p-3.5">
          </div>
        </div>
      </div>

      <div data-auth-android-section @class(['flex flex-col gap-3 border-t border-border pt-4', 'hidden' => ! $autoFill])>
        <h3 class="text-base font-semibold text-text-body">
          Supported Android Apps
          <span class="ml-1 text-sm font-normal text-text-subtle">(Max 5 apps)</span>
        </h3>
        <div id="auth-android-apps" class="flex flex-col gap-3" data-auth-apps='@json($apps)' data-auth-apps-max="5">
          <div data-auth-apps-list class="flex flex-col gap-3"></div>
          <button type="button" data-auth-add-app class="fd-btn-sm w-fit rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Add App</button>
        </div>
      </div>
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.builder.body', $template)" next-label="Save & Continue" />
  </form>
</x-templates.builder-layout>
