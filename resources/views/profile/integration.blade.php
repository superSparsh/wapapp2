@php
    $line = $business['line'] ?? null;
@endphp

<x-profile.layout title="Integration - WapApp" headerTitle="Integration" active="profile.integration">
  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="mx-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <div class="flex flex-col p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Integration</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Manage your WhatsApp Business (WABA) profile and connected channels.
      </p>
    </div>
  </div>

  <section class="flex flex-col p-4 pt-0">
    <div class="rounded-lg bg-elevated p-3">
      <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Business Details</h2>
          <form method="POST" action="{{ route('profile.integration.sync') }}">
            @csrf
            <button type="submit" class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface">
              <img src="{{ asset('images/profile/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
              Sync Data
            </button>
          </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
          <div class="grid grid-cols-3 gap-2 bg-elevated p-2">
            <div class="p-2 text-[13px] font-medium text-text-body">Business ID</div>
            <div class="p-2 text-[13px] font-medium text-text-body">Business Name</div>
            <div class="p-2 text-[13px] font-medium text-text-body">Status</div>
          </div>
          <div class="grid grid-cols-3 gap-2 border-t border-divider bg-elevated px-2 py-1.5">
            <div class="p-2 text-xs text-text-body">{{ $business['waba_id'] ?? '—' }}</div>
            <div class="p-2 text-xs text-text-body">{{ $business['business_name'] ?? '—' }}</div>
            <div class="p-2">
              @if ($business['verified'] ?? false)
                <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium text-[green]">Verified</span>
              @else
                <span class="inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-2 py-1 text-[10px] font-medium text-[red]">Not connected</span>
              @endif
            </div>
          </div>
        </div>

        <h2 class="text-xl font-semibold text-text-primary">Channels</h2>

        @if ($lines->isEmpty())
          <div class="rounded-xl border border-dashed border-border bg-surface p-6 text-sm text-text-muted">
            No WhatsApp numbers are connected to your business account yet.
          </div>
        @else
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($lines as $whatsappLine)
              <x-profile.channel-card
                :phone="$whatsappLine->phone"
                :display-name="$whatsappLine->display_name"
                :quality="$whatsappLine->quality_rating"
                :limit="$whatsappLine->messaging_limit_tier"
                :connected="$whatsappLine->isConnected()"
                :is-default="(bool) $whatsappLine->is_default"
                :href="route('profile.integration.connected', $whatsappLine)"
                class="max-w-none"
              />
            @endforeach
          </div>
        @endif

        <div class="flex w-full items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-6 shrink-0">
          <div class="flex min-w-0 flex-1 flex-col gap-3 text-sm leading-[1.5] text-text-body">
            <p class="font-bold">Note:</p>

            <div class="flex flex-col gap-2">
              <p class="font-bold">
                1. Official Business Requirements for increasing the number of WhatsApp Phone Number limit 2 to 20
              </p>
              <p class="font-normal text-text-muted">
                As per Meta's WhatsApp Business platform's Phone Numbers Guidelines, Business accounts are
                initially limited to 2 registered business WhatsApp phone numbers. However, this limit can be
                increased to up to 20.
              </p>
              <ul class="list-disc space-y-2 pl-5 font-normal text-text-muted">
                <li>
                  <span class="font-bold text-text-body">Limit Increase:</span>
                  If your business has been verified and your business WhatsApp phone number has an approved
                  display name (or if you have two business WhatsApp phone numbers with approved display names),
                  Meta will determine if your usage warrants a business WhatsApp phone number limit increase.
                </li>
                <li>
                  <span class="font-bold text-text-body">Automatic Increase:</span>
                  If Meta determines that an increase is necessary, they will automatically raise your limit and
                  notify you through Meta Business Suite notifications regarding the updated limit.
                </li>
                <li>
                  <span class="font-bold text-text-body">Limit Remaining at 2:</span>
                  In cases where Meta determines that an increase is not warranted, your limit will remain at 2.
                  If more than a week has passed since your business was verified and your limit remains at 2,
                  kindly ensure that you are actively monitoring business WhatsApp phone numbers and message
                  quality, and take appropriate actions to enhance your quality scores. After improving your
                  quality scores, if it is determined that your business justifies an increase, your limit will
                  be raised automatically. You will be notified of the new limit via Meta Business Suite notification.
                </li>
              </ul>
              <p class="font-normal text-text-muted">
                For those with access to Enterprise Support who require a limit greater than 20, you can initiate a
                Direct Support Ticket, providing an explanation for why you need more than 20 numbers.
              </p>
              <p class="font-bold">
                For further details, please refer to the information provided
                <a
                  href="https://developers.facebook.com/docs/whatsapp/phone-numbers"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-text-body underline decoration-solid underline-offset-2 hover:text-green-600"
                >here</a>.
              </p>
            </div>

            <hr class="border-divider">

            <div class="flex flex-col gap-2">
              <p class="font-bold">
                2. Official Business Account Requirements and Application Process for Display Name and Green Tick
              </p>
              <ul class="list-disc space-y-2 pl-5 font-normal text-text-muted">
                <li>
                  <span class="font-bold text-text-body">Basic Prerequisites:</span>
                  <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li>Ensure your business verification is done.</li>
                    <li>Turn on two-step verification.</li>
                    <li>Approval process for your profile display name and Green Tick.</li>
                  </ul>
                  <ol class="mt-2 list-[lower-alpha] space-y-1.5 pl-5">
                    <li>Include links to articles, blog posts, or reviews from reputable sources.</li>
                    <li>These should showcase your business as popular and trusted by customers.</li>
                    <li>Avoid using links to your own website, Facebook, or Instagram pages.</li>
                    <li>Exclude any content that has been paid for or created for promotional purposes.</li>
                    <li>You must provide 5 such links.</li>
                  </ol>
                </li>
              </ul>
              <p class="font-normal text-text-muted">
                Provide any additional supporting information that can help demonstrate your business's recognizability.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</x-profile.layout>
