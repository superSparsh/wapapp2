<x-layouts.app title="Import Team Members - WapApp" active="manager.team.index">
  <div class="flex flex-col gap-4 bg-surface p-4">
    <h1 class="text-2xl font-bold text-text-primary">Import Team Members</h1>
    <p class="text-sm text-text-subtle">Upload a CSV with columns: first_name, last_name, email, phone_number, password, permissions</p>
    <a href="{{ route('manager.team.import.sample') }}" class="text-sm font-semibold text-green-500">Download sample CSV</a>
    <form method="post" action="{{ route('manager.team.import.store') }}" enctype="multipart/form-data" class="max-w-xl rounded-lg bg-elevated p-4">
      @csrf
      <input type="file" name="file" accept=".csv,text/csv" required class="mb-4 block w-full text-sm">
      @error('file')<p class="mb-2 text-xs text-red-500">{{ $message }}</p>@enderror
      <button type="submit" class="rounded bg-green-500 px-4 py-2 text-sm font-semibold text-primary-2">Import</button>
    </form>
  </div>
</x-layouts.app>
