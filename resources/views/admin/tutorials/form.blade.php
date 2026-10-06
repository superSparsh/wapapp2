@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.tutorials.update', $row) : route('admin.tutorials.store');
  $hasVideoFile = $hasVideoFile ?? false;
  $playbackUrl = $playbackUrl ?? null;
  $usingFallback = $usingFallback ?? false;
  $playbackFilename = $playbackFilename ?? null;
@endphp

<x-admin.layout :title="($isEdit ? 'Edit tutorial' : 'Add tutorial').' - Admin'" active="admin.tutorials.index">
  <div class="p-4">
    <a href="{{ route('admin.tutorials.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Tutorials</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit tutorial' : 'Add tutorial' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if (session('status'))
      <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

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

      <div class="rounded-xl border border-border bg-muted-surface/40 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-text-primary">Video file</p>
            <p class="text-xs text-text-subtle">Upload MP4 here to add or replace. Max ~200 MB (server PHP limits must allow it).</p>
          </div>
          @if ($isEdit)
            @if ($hasVideoFile)
              @if ($usingFallback)
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Playing old file</span>
              @else
                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">File on disk</span>
              @endif
            @else
              <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Missing file</span>
            @endif
          @endif
        </div>

        @if ($isEdit && $usingFallback && filled($playbackFilename))
          <p class="mb-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
            New filename is not on disk yet, so the front will keep playing the previous file:
            <code class="font-mono">{{ $playbackFilename }}</code>.
            Upload the new MP4 (or match the filename field) to switch customers to the updated video.
          </p>
        @elseif ($isEdit && ! $hasVideoFile && filled($row->youtube_id ?? null))
          <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Expected file not found:
            <code class="font-mono">public/assets/videos/tutorials/{{ $row->youtube_id }}</code>.
            Upload below with this same filename, or change the filename field to match the MP4 already on the server.
          </p>
        @endif

        @if ($isEdit && $hasVideoFile && $playbackUrl)
          <div class="mb-3 overflow-hidden rounded-lg bg-black">
            <video controls playsinline preload="metadata" class="aspect-video w-full max-h-64 object-contain" src="{{ $playbackUrl }}?v={{ $row->id }}-{{ time() }}"></video>
          </div>
        @endif

        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">{{ $isEdit ? 'Upload / replace video' : 'Upload video' }}</span>
          <input type="file" name="video" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" class="rounded-lg border border-border bg-elevated px-3 py-2 text-sm">
          @if ($isEdit)
            <span class="text-xs text-text-subtle">Uploading a new file deletes/replaces the current MP4 on disk.</span>
          @endif
        </label>

        <label class="mt-3 flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Local video filename</span>
          <input name="youtube_id" value="{{ old('youtube_id', $row->youtube_id ?? '') }}" class="rounded-lg border border-border px-3 py-2 font-mono text-xs" placeholder="Video_1_Dashboard_Overview.mp4">
          <span class="text-xs text-text-subtle">
            Stored under <code>public/assets/videos/tutorials/</code>.
            @if ($isEdit)
              Keep the same name when replacing so existing links stay stable.
            @else
              Optional if you upload a file (uses the uploaded filename). Required if you skip upload.
            @endif
          </span>
        </label>
      </div>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Description (optional)</span>
        <textarea name="description" rows="4" class="rounded-lg border border-border px-3 py-2 text-sm">{{ old('description', $row->description ?? '') }}</textarea>
      </label>
      <div class="grid gap-4 sm:grid-cols-3">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Duration</span>
          <input name="duration" value="{{ old('duration', $row->duration ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="1:30">
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

    <div class="mt-5 flex flex-wrap items-center gap-3">
      <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
    </div>
  </form>

  @if ($isEdit && $hasVideoFile)
    <div class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-red-200 bg-red-50 p-5">
      <p class="text-sm font-semibold text-red-700">Delete current video file</p>
      <p class="mt-1 text-xs text-red-600/80">Removes the MP4 from disk only. The tutorial row stays so you can upload a replacement.</p>
      <form method="POST" action="{{ route('admin.tutorials.destroy-video', $row) }}" class="mt-3" onsubmit="return confirm('Delete the current video file from the server?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-red-500 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Delete video file</button>
      </form>
    </div>
  @endif
</x-admin.layout>
