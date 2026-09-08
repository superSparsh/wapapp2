<x-layouts.app title="Preview Free Template - WapApp" active="templates.index">
  <div class="flex flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
      <h1 class="fd-page-title">{{ $message->name }}</h1>
      <a href="{{ route('templates.free.edit', $message) }}" class="fd-btn rounded bg-green-500 px-4 py-2 text-primary-2">Edit</a>
    </div>
    <x-templates.phone-preview>
      <x-templates.message-preview-bubble :preview-data="$previewData" />
    </x-templates.phone-preview>
  </div>
</x-layouts.app>
