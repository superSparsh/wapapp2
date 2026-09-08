@php
  $isEdit = isset($plan);
  $action = $isEdit ? route('admin.plans.update', $plan) : route('admin.plans.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit plan' : 'Create plan').' - Admin'" active="admin.plans.index">
  <div class="p-4">
    <a href="{{ route('admin.plans.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Plans</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit plan' : 'Create plan' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $plan->name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Slug</span>
        <input name="slug" value="{{ old('slug', $plan->slug ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Billing cycle</span>
        <select name="billing_cycle" class="rounded-lg border border-border px-3 py-2" required>
          @foreach (['monthly', 'quarterly', 'yearly'] as $cycle)
            <option value="{{ $cycle }}" @selected(old('billing_cycle', $plan->billing_cycle?->value ?? 'monthly') === $cycle)>{{ ucfirst($cycle) }}</option>
          @endforeach
        </select>
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Price</span>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price ?? '0') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Currency</span>
        <input name="currency" maxlength="3" value="{{ old('currency', $plan->currency ?? 'INR') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
        <span class="font-semibold">Description</span>
        <textarea name="description" rows="3" class="rounded-lg border border-border px-3 py-2">{{ old('description', $plan->description ?? '') }}</textarea>
      </label>
      @foreach ([
        'messages_limit' => 'Messages limit',
        'contacts_limit' => 'Contacts limit',
        'team_members_limit' => 'Team members limit',
        'whatsapp_lines_limit' => 'WhatsApp lines limit',
        'sort_order' => 'Sort order',
      ] as $field => $label)
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">{{ $label }}</span>
          <input type="number" min="0" name="{{ $field }}" value="{{ old($field, $plan->{$field} ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      @endforeach
      <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
        Active
      </label>
    </div>

    <div class="mt-6">
      <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save plan</button>
    </div>
  </form>
</x-admin.layout>
