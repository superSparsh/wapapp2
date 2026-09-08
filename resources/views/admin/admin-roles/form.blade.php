@php
  $isEdit = isset($role);
  $action = $isEdit ? route('admin.admin-roles.update', $role) : route('admin.admin-roles.store');
  $selected = old('permissions', $isEdit ? ($role->permissions ?? []) : []);
@endphp

<x-admin.layout :title="($isEdit ? 'Edit role' : 'Add role').' - Admin'" active="admin.admin-roles.index">
  <div class="p-4">
    <a href="{{ route('admin.admin-roles.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Roles</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit role' : 'Add role' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-2xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $role->name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Slug</span>
        <input name="slug" value="{{ old('slug', $role->slug ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="Auto-generated from name">
      </label>

      <fieldset class="flex flex-col gap-2 text-sm">
        <legend class="font-semibold">Permissions</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          @foreach ($permissions as $permission)
            <label class="flex items-center gap-2">
              <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, (array) $selected, true))>
              {{ ucfirst(str_replace('_', ' ', $permission)) }}
            </label>
          @endforeach
        </div>
      </fieldset>

      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $role->is_active ?? true))>
        <span class="font-semibold">Active</span>
      </label>
    </div>

    <button class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
