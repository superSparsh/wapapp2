@props([
    'member' => null,
    'showEmail' => true,
    'passwordRequired' => true,
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
@endphp

<div class="flex flex-col gap-5 border-b border-divider p-5 sm:p-6">
  <div class="flex flex-col gap-1">
    <h2 class="text-base font-semibold text-text-primary">Basic information</h2>
    <p class="text-sm text-text-subtle opacity-70">Name and phone used across inbox and team views.</p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-form.input id="first_name" name="first_name" type="text" :value="$firstName" placeholder="First name" required :error="$errors->first('first_name')">
      <x-slot:label>First name <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>

    <x-form.input id="last_name" name="last_name" type="text" :value="$lastName" placeholder="Last name" required :error="$errors->first('last_name')">
      <x-slot:label>Last name <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>

    <div class="sm:col-span-2 lg:col-span-1">
      <x-form.input id="phone" name="phone" type="tel" :value="$phone" placeholder="10-digit mobile number" required :error="$errors->first('phone')">
        <x-slot:label>Contact number <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>
    </div>
  </div>
</div>

<div class="flex flex-col gap-5 border-b border-divider p-5 sm:p-6">
  <div class="flex flex-col gap-1">
    <h2 class="text-base font-semibold text-text-primary">Account credentials</h2>
    <p class="text-sm text-text-subtle opacity-70">
      {{ $passwordRequired ? 'Used for team login. Share securely after creation.' : 'Leave password blank to keep the current one.' }}
    </p>
  </div>

  @if ($showEmail)
    <x-form.input id="email" name="email" type="email" :value="$email" placeholder="name@company.com" required :error="$errors->first('email')">
      <x-slot:label>Email <span class="text-red-500">*</span></x-slot:label>
    </x-form.input>
  @else
    <div class="rounded-xl border border-border bg-surface px-3.5 py-3 text-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle opacity-60">Login email</p>
      <p class="mt-1 font-medium text-text-primary">{{ $member?->email }}</p>
    </div>
  @endif

  <div class="grid gap-4 sm:grid-cols-2">
    <x-form.input
      id="password"
      name="password"
      type="password"
      :placeholder="$passwordPlaceholder"
      :required="$passwordRequired"
      :error="$errors->first('password')"
    >
      <x-slot:label>{{ $passwordLabel }} @if ($passwordRequired)<span class="text-red-500">*</span>@endif</x-slot:label>
    </x-form.input>

    <x-form.input
      id="password_confirmation"
      name="password_confirmation"
      type="password"
      :placeholder="$passwordConfirmPlaceholder"
      :required="$passwordRequired"
    >
      <x-slot:label>{{ $passwordConfirmLabel }} @if ($passwordRequired)<span class="text-red-500">*</span>@endif</x-slot:label>
    </x-form.input>
  </div>
</div>
