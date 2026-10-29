@php
  use App\Enums\BusinessCustomTermStatus;
  $status = $term?->status;
  $statusLabel = $status instanceof BusinessCustomTermStatus ? $status->label() : 'Not created yet';
@endphp

<x-profile.layout title="Business Terms - WapApp" headerTitle="Business Terms" active="profile.business-terms">
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Business Terms</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        View the signed onboarding agreement we emailed you, Meta WhatsApp policies you accepted, and write custom terms for your end customers (admin review required before publish).
      </p>
    </div>

    @if (session('status'))
      <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
        {{ $errors->first() }}
      </div>
    @endif

    {{-- Signed onboarding PDF --}}
    <section class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
      <h2 class="text-lg font-semibold text-text-primary">Signed onboarding agreement</h2>
      <p class="mt-1 text-sm text-text-body">The PDF attached to your onboarding / credentials email.</p>
      @if (! empty($signed['pdf_path']))
        <a
          href="{{ route('profile.business-terms.signed-pdf') }}"
          target="_blank"
          rel="noopener"
          class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-600"
        >
          View signed PDF
        </a>
        @if (! empty($signed['pdf_name']))
          <p class="mt-2 text-xs text-text-muted">{{ $signed['pdf_name'] }}</p>
        @endif
      @else
        <p class="mt-3 rounded-xl bg-surface px-3 py-2 text-sm text-text-muted">
          No signed PDF on file yet. If you onboarded via forms and expected an attachment, contact
          <a href="mailto:{{ config('legal.grievance.email') }}" class="font-semibold text-primary-2 underline">{{ config('legal.grievance.email') }}</a>.
        </p>
      @endif
    </section>

    {{-- Meta policies accepted at signup --}}
    <section class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
      <h2 class="text-lg font-semibold text-text-primary">WhatsApp / Meta policies</h2>
      <p class="mt-1 text-sm text-text-body">Official documents referenced during signup. Open to review anytime.</p>
      <ul class="mt-4 flex flex-col gap-2">
        @foreach ($metaPolicies as $key => $policy)
          <li>
            <a href="{{ $policy['pdf_url'] ?? $policy['url'] }}" target="_blank" rel="noopener" class="text-sm font-semibold text-primary-2 underline">
              {{ $policy['label'] ?? $key }}
            </a>
          </li>
        @endforeach
      </ul>
    </section>

    {{-- Custom customer terms --}}
    <section class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-text-primary">Your custom customer terms</h2>
          <p class="mt-1 text-sm text-text-body">
            Optional terms you show your end customers. Submitting sends them to Tittu admin for review.
            Wrong or non-compliant wording can be rejected or removed; accounts may be suspended.
          </p>
        </div>
        <span class="rounded-lg bg-surface px-3 py-1.5 text-xs font-semibold text-text-primary">{{ $statusLabel }}@if($term) · v{{ $term->version }}@endif</span>
      </div>

      @if ($term?->admin_note)
        <x-profile.info-banner title="Admin note" class="mt-4" :variant="$status === BusinessCustomTermStatus::Approved ? 'info' : 'warning'">
          {{ $term->admin_note }}
        </x-profile.info-banner>
      @endif

      <form method="POST" action="{{ route('profile.business-terms.update') }}" class="mt-4 flex flex-col gap-4">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-2">
          <label for="title" class="text-sm font-semibold text-text-primary">Title</label>
          <input
            id="title"
            name="title"
            type="text"
            required
            maxlength="{{ (int) config('legal.custom_terms.title_max', 191) }}"
            value="{{ old('title', $term->title ?? 'Customer Terms of Use') }}"
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary"
          >
        </div>

        <div class="flex flex-col gap-2">
          <label for="body" class="text-sm font-semibold text-text-primary">Terms text</label>
          <textarea
            id="body"
            name="body"
            required
            rows="16"
            maxlength="{{ $maxChars }}"
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.6] text-text-primary"
            placeholder="Write the terms your customers agree to when they interact with your business on WhatsApp…"
          >{{ old('body', $term->body ?? '') }}</textarea>
          <p class="text-xs text-text-muted">Max {{ number_format($maxChars) }} characters. Do not paste prohibited categories or misleading claims.</p>
        </div>

        <div class="flex flex-wrap gap-3">
          <button type="submit" name="submit_for_review" value="0" class="fd-btn inline-flex rounded-lg border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-primary-2 hover:bg-surface">
            Save draft
          </button>
          <button type="submit" name="submit_for_review" value="1" class="fd-btn inline-flex rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold text-white hover:bg-green-600">
            Submit for admin review
          </button>
        </div>
      </form>
    </section>
  </div>
</x-profile.layout>
