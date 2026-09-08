<x-admin.layout title="Edit customer - Admin" active="admin.customers.index">
  <div class="p-4">
    <a href="{{ route('admin.customers.show', $tenant) }}" class="text-xs font-semibold text-green-600 hover:underline">← Back</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">Edit customer</h1>
  </div>

  <form method="POST" action="{{ route('admin.customers.update', $tenant) }}" class="mx-4 mb-8 max-w-2xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $tenant->name) }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Company</span>
        <input name="company_name" value="{{ old('company_name', $tenant->company_name) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Email</span>
        <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Phone</span>
        <input name="phone" value="{{ old('phone', $tenant->phone) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Plan</span>
        <select name="plan_id" class="rounded-lg border border-border px-3 py-2">
          <option value="">No plan</option>
          @foreach ($plans as $plan)
            <option value="{{ $plan->id }}" @selected((int) old('plan_id', $tenant->plan_id) === (int) $plan->id)>{{ $plan->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Status</span>
        <select name="status" class="rounded-lg border border-border px-3 py-2" required>
          @foreach (['active', 'suspended', 'pending'] as $status)
            <option value="{{ $status }}" @selected(old('status', $tenant->status?->value) === $status)>{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </label>
      <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
        <span class="font-semibold">Timezone</span>
        <input name="timezone" value="{{ old('timezone', $tenant->timezone) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
    </div>

    <div class="mt-6 flex gap-3">
      <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save</button>
      <a href="{{ route('admin.customers.show', $tenant) }}" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold">Cancel</a>
    </div>
  </form>
</x-admin.layout>
