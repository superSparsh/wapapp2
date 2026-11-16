@props([
    'member' => null,
    'showEmail' => true,
    'passwordRequired' => false,
    'showPasswordFields' => true,
    'inviteMode' => false,
    'autoGeneratePassword' => false,
    'generatedPassword' => null,
    'passwordLabel' => 'Password',
    'passwordConfirmLabel' => 'Confirm password',
    'passwordPlaceholder' => 'Minimum 8 characters',
    'passwordConfirmPlaceholder' => 'Re-enter password',
])

@php
  $firstName = old('first_name', $member?->first_name);
  $lastName = old('last_name', $member?->last_name);
  $phone = old('phone', $member?->phone);
  $email = old('email', $member?->email);
  $isInviteCreate = $inviteMode && $member === null;
  $shouldAutoGenerate = $autoGeneratePassword || $isInviteCreate;
  $passwordValue = old('password', $shouldAutoGenerate ? (string) ($generatedPassword ?? '') : '');
  $passwordConfirmValue = old('password_confirmation', $passwordValue);
@endphp

<div class="flex flex-col gap-5 border-b border-divider p-5 sm:p-6">
  <div class="flex flex-col gap-1">
    <h2 class="text-base font-semibold text-text-primary">Basic information</h2>
    <p class="text-sm text-text-subtle opacity-70">Name and phone used across inbox and team views.</p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-form.input id="first_name" name="first_name" type="text" :value="$firstName" placeholder="First name" required :error="$errors->first('first_name')" autocomplete="given-name">
      <x-slot:label>First name <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>

    <x-form.input id="last_name" name="last_name" type="text" :value="$lastName" placeholder="Last name" required :error="$errors->first('last_name')" autocomplete="family-name">
      <x-slot:label>Last name <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>

    <div class="sm:col-span-2 lg:col-span-1">
      <x-form.input id="phone" name="phone" type="tel" :value="$phone" placeholder="10-digit mobile number" required :error="$errors->first('phone')" autocomplete="tel">
        <x-slot:label>Contact number <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>
    </div>
  </div>
</div>

<div
  class="flex flex-col gap-5 border-b border-divider p-5 sm:p-6"
  @if ($shouldAutoGenerate) data-team-password-generator @endif
>
  <div class="flex flex-col gap-1">
    <h2 class="text-base font-semibold text-text-primary">
      {{ $isInviteCreate ? 'Invite & login' : 'Account credentials' }}
    </h2>
    <p class="text-sm text-text-subtle opacity-70">
      @if ($shouldAutoGenerate)
        A strong password is generated for you (like Chrome). You can regenerate or edit it before saving.
      @elseif ($passwordRequired)
        Used for team login. Share securely after creation.
      @else
        Leave password blank to keep the current one.
      @endif
    </p>
  </div>

  @if ($showEmail)
    <x-form.input id="email" name="email" type="email" :value="$email" placeholder="name@company.com" required :error="$errors->first('email')" autocomplete="email">
      <x-slot:label>Email <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>
  @else
    <div class="rounded-xl border border-border bg-surface px-3.5 py-3 text-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle opacity-60">Login email</p>
      <p class="mt-1 font-medium text-text-primary">{{ $member?->email }}</p>
    </div>
  @endif

  @if ($isInviteCreate)
    <div class="rounded-xl border border-green-100 bg-green-50/70 px-4 py-3 text-sm text-green-800">
      <p class="font-semibold">Invite email will be sent automatically</p>
      <p class="mt-1 text-green-700/90">They get a link to set/change their password. The generated password below also works for first login if you share it.</p>
    </div>
  @endif

  @if ($showPasswordFields)
    <div class="grid gap-4 sm:grid-cols-2">
      <x-form.input
        id="password"
        name="password"
        type="text"
        :value="$passwordValue"
        :placeholder="$passwordPlaceholder"
        :required="$passwordRequired || $shouldAutoGenerate"
        :error="$errors->first('password')"
        autocomplete="new-password"
        data-team-password-input
      >
        <x-slot:label>
          {{ $shouldAutoGenerate ? 'Generated password' : $passwordLabel }}
          @if ($passwordRequired || $shouldAutoGenerate)<span class="text-red-500">*</span>@endif
        </x-slot:label>
        <x-slot:suffix>
          <button type="button" data-password-toggle class="flex size-5 items-center justify-center" aria-label="Hide password">
            <img src="{{ asset('images/auth/eye.svg') }}" alt="" class="size-5" width="20" height="20">
          </button>
        </x-slot:suffix>
      </x-form.input>

      <x-form.input
        id="password_confirmation"
        name="password_confirmation"
        type="text"
        :value="$passwordConfirmValue"
        :placeholder="$passwordConfirmPlaceholder"
        :required="$passwordRequired || $shouldAutoGenerate"
        autocomplete="new-password"
        data-team-password-confirm
      >
        <x-slot:label>
          {{ $passwordConfirmLabel }}
          @if ($passwordRequired || $shouldAutoGenerate)<span class="text-red-500">*</span>@endif
        </x-slot:label>
        <x-slot:suffix>
          <button type="button" data-password-toggle class="flex size-5 items-center justify-center" aria-label="Hide password">
            <img src="{{ asset('images/auth/eye.svg') }}" alt="" class="size-5" width="20" height="20">
          </button>
        </x-slot:suffix>
      </x-form.input>
    </div>

    @if ($shouldAutoGenerate)
      <div class="flex flex-wrap items-center gap-3">
        <button
          type="button"
          data-team-password-generate
          class="inline-flex items-center justify-center rounded border border-green-500 bg-elevated px-4 py-2 text-xs font-semibold text-green-600 transition-colors hover:bg-green-50"
        >
          Generate new password
        </button>
        <p class="text-xs text-text-muted" data-team-password-hint>
          Shown in plain text so you can copy it. Click the eye to hide.
        </p>
      </div>
    @endif
  @endif
</div>
