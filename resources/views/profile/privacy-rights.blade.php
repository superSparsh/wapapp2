<x-profile.layout title="Privacy rights - WapApp" headerTitle="Privacy rights" active="profile.privacy-rights">
  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif

  @if ($errors->any())
    <div class="mx-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Privacy rights &amp; grievances</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Submit Data Principal requests for {{ $productName }}. Target response: {{ $slaDays }} days.
        You can also email <a href="mailto:{{ $grievanceEmail }}" class="font-semibold text-primary-2 underline">{{ $grievanceEmail }}</a>.
      </p>
    </div>

    <x-profile.info-banner title="Internal review draft">
      This workflow is available to signed-in accounts for review. Public self-service links stay unpublished until leadership sign-off.
    </x-profile.info-banner>

    <x-legal.collection-notice />

    <section class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)] sm:p-8">
      <h2 class="text-lg font-bold text-text-primary">Submit a request</h2>
      <form method="POST" action="{{ route('profile.privacy-rights.store') }}" class="mt-4 flex flex-col gap-4">
        @csrf
        <div class="flex flex-col gap-2">
          <label for="request_type" class="text-sm font-semibold text-text-primary">Request type</label>
          <select id="request_type" name="request_type" class="rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm" required>
            @foreach ($requestTypes as $type)
              <option value="{{ $type->value }}" @selected(old('request_type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
          </select>
        </div>

        <div class="flex flex-col gap-2">
          <label for="description" class="text-sm font-semibold text-text-primary">Details</label>
          <textarea
            id="description"
            name="description"
            rows="5"
            class="rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm"
            required
            minlength="10"
          >{{ old('description') }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <x-form.input id="requester_phone" name="requester_phone" type="tel" placeholder="Optional phone" :value="old('requester_phone')">
            <x-slot:label>Phone (optional)</x-slot:label>
          </x-form.input>
          <x-form.input id="nominee_name" name="nominee_name" placeholder="Nominee name" :value="old('nominee_name')">
            <x-slot:label>Nominee name (if nominating)</x-slot:label>
          </x-form.input>
          <x-form.input id="nominee_email" name="nominee_email" type="email" placeholder="Nominee email" :value="old('nominee_email')">
            <x-slot:label>Nominee email</x-slot:label>
          </x-form.input>
          <x-form.input id="nominee_relation" name="nominee_relation" placeholder="Relation" :value="old('nominee_relation')">
            <x-slot:label>Nominee relation</x-slot:label>
          </x-form.input>
        </div>

        <x-ui.button type="submit" class="rounded-xl p-3.5 sm:w-auto">Submit request</x-ui.button>
      </form>
    </section>

    <section class="rounded-[20px] border border-border-light bg-elevated p-5 sm:p-8">
      <h2 class="text-lg font-bold text-text-primary">Your recent requests</h2>
      @if ($requests->isEmpty())
        <p class="mt-3 text-sm text-text-muted">No requests yet.</p>
      @else
        <ul class="mt-4 divide-y divide-border">
          @foreach ($requests as $item)
            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="text-sm font-semibold text-text-primary">{{ $item->request_type->label() }}</p>
                <p class="mt-1 text-sm text-text-body">{{ \Illuminate\Support\Str::limit($item->description, 160) }}</p>
                <p class="mt-1 text-xs text-text-muted">Submitted {{ $item->created_at?->format('d M Y H:i') }}</p>
              </div>
              <span class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $item->status->label() }}</span>
            </li>
          @endforeach
        </ul>
      @endif
    </section>

    <nav class="flex flex-wrap gap-3 text-sm font-semibold">
      <a href="{{ route('profile.privacy-policy') }}" class="text-primary-2 underline">Privacy Policy</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('profile.terms') }}" class="text-primary-2 underline">Terms of Service</a>
    </nav>
  </div>
</x-profile.layout>
