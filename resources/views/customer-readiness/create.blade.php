<x-layouts.guest title="Customer Readiness - WapApp">
  <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-elevated shadow-lg">
      <div class="flex flex-col gap-6 p-6 sm:p-10">
        <header class="flex flex-col gap-2">
          <h1 class="text-2xl font-bold leading-[1.2] text-text-primary">Customer readiness form</h1>
          <p class="text-sm font-medium leading-[1.5] text-text-primary/60">
            Share your business details so our team can prepare your WhatsApp Business account.
          </p>
        </header>

        @if ($errors->any())
          <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
            {{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('customer-readiness.store') }}" class="flex flex-col gap-4">
          @csrf

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Your name <span class="text-red-500">*</span></span>
            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Your email <span class="text-red-500">*</span></span>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Business name <span class="text-red-500">*</span></span>
            <input name="business_name" value="{{ old('business_name') }}" required class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Business email</span>
            <input type="email" name="business_email" value="{{ old('business_email') }}" class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Website</span>
            <input name="website" value="{{ old('website') }}" placeholder="https://example.com" class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold text-text-primary">Verification document <span class="text-red-500">*</span></span>
            <select name="doc_type" required class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm">
              <option value="">Select a document type</option>
              @foreach ($docTypes as $docType)
                <option value="{{ $docType }}" @selected(old('doc_type') === $docType)>{{ ucfirst(str_replace('_', ' ', $docType)) }}</option>
              @endforeach
            </select>
          </label>

          <button type="submit" class="mt-2 w-full rounded-xl bg-green-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-green-600">
            Submit
          </button>
        </form>
      </div>
    </div>
  </main>
</x-layouts.guest>
