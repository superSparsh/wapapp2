<x-layouts.guest title="Thanks - WapApp">
  <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-elevated shadow-lg">
      <div class="flex flex-col gap-4 p-6 text-center sm:p-10">
        <h1 class="text-2xl font-bold text-text-primary">Thanks — we got your details</h1>
        <p class="text-sm font-medium text-text-primary/60">
          Our onboarding team will review your submission and reach out over email shortly.
        </p>
        <a href="{{ route('customer-readiness.create') }}" class="mt-2 text-sm font-semibold text-green-600 hover:underline">Submit another response</a>
      </div>
    </div>
  </main>
</x-layouts.guest>
