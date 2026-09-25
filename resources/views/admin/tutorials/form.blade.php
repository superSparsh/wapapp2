@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.tutorials.update', $row) : route('admin.tutorials.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit tutorial' : 'Add tutorial').' - Admin'" active="admin.tutorials.index">
  <div class="p-4">
    <a href="{{ route('admin.tutorials.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Tutorials</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit tutorial' : 'Add tutorial' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Title</span>
        <input name="title" value="{{ old('title', $row->title ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Module name</span>
        <input name="module_name" value="{{ old('module_name', $row->module_name ?? '') }}" required class="rounded-lg border border-border px-3 py-2" placeholder="e.g. Module 1: Dashboard">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Local video filename</span>
        <input name="youtube_id" value="{{ old('youtube_id', $row->youtube_id ?? '') }}" required class="rounded-lg border border-border px-3 py-2 font-mono text-xs" placeholder="Video_1_Dashboard_Overview.mp4">
        <span class="text-xs text-text-subtle">Put the MP4 in <code>public/assets/videos/tutorials/</code>. Filename must match exactly (including spaces / &amp;).</span>
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Description (optional)</span>
        <textarea name="description" rows="4" class="rounded-lg border border-border px-3 py-2 text-sm">{{ old('description', $row->description ?? '') }}</textarea>
      </label>
      <div class="grid gap-4 sm:grid-cols-3">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Duration</span>
          <input name="duration" value="{{ old('duration', $row->duration ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="2:00">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Sort order</span>
          <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $row->sort_order ?? 0) }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="inline-flex items-center gap-2 self-end text-sm">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
          <span class="font-semibold">Active</span>
        </label>
      </div>
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
