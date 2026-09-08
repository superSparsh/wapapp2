@php
  $isEdit = isset($admin);
  $action = $isEdit ? route('admin.admins.update', $admin) : route('admin.admins.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit admin' : 'Create admin').' - Admin'" active="admin.admins.index">
  <div class="p-4">
    <a href="{{ route('admin.admins.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Admins</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit admin' : 'Create admin' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $admin->name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Email</span>
        <input type="email" name="email" value="{{ old('email', $admin->email ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Password {{ $isEdit ? '(leave blank to keep)' : '' }}</span>
        <input type="password" name="password" @required(! $isEdit) class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Confirm password</span>
        <input type="password" name="password_confirmation" @required(! $isEdit) class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $admin->is_active ?? true))>
        Active
      </label>
    </div>

    <button type="submit" class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save</button>
  </form>
</x-admin.layout>
