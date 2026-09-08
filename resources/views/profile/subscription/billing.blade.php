<x-profile.layout title="Billing Information - WapApp" headerTitle="Subscription">
  <div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Billing Information</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Create personalized message templates for initiating conversation with your customers.
      </p>
    </div>

    <nav class="flex flex-wrap items-center gap-5" aria-label="Subscription steps">
      <a
        href="{{ route('profile.subscription.upgrade') }}"
        class="text-xl font-semibold leading-[1.5] whitespace-nowrap text-text-primary transition-colors hover:opacity-80"
      >
        Select Plans
      </a>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
      <span class="text-xl font-semibold leading-[1.5] whitespace-nowrap text-text-primary">Billing Information</span>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
      <a
        href="{{ route('profile.subscription.payment') }}"
        class="text-xl font-semibold leading-[1.5] whitespace-nowrap text-blue-200 transition-colors hover:text-text-primary"
      >
        Payment Method
      </a>
    </nav>
  </div>

  <section class="flex flex-col gap-4 p-4 pt-0 xl:flex-row xl:items-start">
    <form class="flex w-full flex-col gap-4 rounded-lg bg-elevated p-4 xl:max-w-[746px] xl:shrink-0" action="{{ route('profile.subscription.billing.save') }}" method="post">
      @csrf
      <div class="flex flex-col gap-2">
        <div class="flex flex-col gap-2">
          <label for="gst_treatment" class="text-sm font-semibold leading-[1.4] text-text-primary">
            GST Treatment <span class="text-[red]">*</span>
          </label>
          <input
            id="gst_treatment"
            name="gst_treatment"
            type="text"
            placeholder="Enter GST Treatment"
            value="{{ old('gst_treatment', $address?->gst_treatment) }}"
            required
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          >
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="company_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Company/Individual Name <span class="text-[red]">*</span>
            </label>
            <input
              id="company_name"
              name="company_name"
              type="text"
              placeholder="Enter Company/Individual Name"
              value="{{ old('company_name', $address?->company_name) }}"
              required
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
          <div class="flex flex-col gap-2">
            <label for="pan" class="text-sm font-semibold leading-[1.4] text-text-primary">
              PAN<span class="text-[red]">*</span>
            </label>
            <input
              id="pan"
              name="pan"
              type="text"
              placeholder="Enter PAN"
              value="{{ old('pan', $address?->pan) }}"
              required
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Email address <span class="text-[red]">*</span>
            </label>
            <input
              id="email"
              name="email"
              type="email"
              placeholder="Enter Email address"
              value="{{ old('email', $address?->email) }}"
              required
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
          <div class="flex flex-col gap-2">
            <label for="phone" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Phone<span class="text-[red]">*</span>
            </label>
            <input
              id="phone"
              name="phone"
              type="tel"
              placeholder="Enter Phone"
              value="{{ old('phone', $address?->phone) }}"
              required
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="address_line_1" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Address<span class="text-[red]">*</span>
            </label>
            <input
              id="address_line_1"
              name="address_line_1"
              type="text"
              placeholder="Enter Address"
              value="{{ old('address_line_1', $address?->address_line_1) }}"
              required
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
          <div class="flex flex-col gap-2">
            <label for="country_code" class="text-sm font-semibold leading-[1.4] text-text-primary">Country</label>
            <div class="relative">
              <select
                id="country_code"
                name="country_code"
                class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="IN" @selected(old('country_code', $address?->country_code ?? 'IN') === 'IN')>India</option>
              </select>
              <img
                src="{{ asset('images/profile/chevron-down.svg') }}"
                alt=""
                class="pointer-events-none absolute top-1/2 right-3.5 size-5 -translate-y-1/2 rotate-90"
                width="20"
                height="20"
              >
            </div>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="state" class="text-sm font-semibold leading-[1.4] text-text-primary">State</label>
            <div class="relative">
              <select
                id="state"
                name="state"
                class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="" selected disabled>Select State</option>
              </select>
              <img
                src="{{ asset('images/profile/chevron-down.svg') }}"
                alt=""
                class="pointer-events-none absolute top-1/2 right-3.5 size-5 -translate-y-1/2 rotate-90"
                width="20"
                height="20"
              >
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <label for="city" class="text-sm font-semibold leading-[1.4] text-text-primary">City</label>
            <input
              id="city"
              name="city"
              type="text"
              placeholder="Enter City"
              value="{{ old('city', $address?->city) }}"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
        </div>

        <div class="flex flex-col gap-2 sm:max-w-[calc(50%-6px)]">
          <label for="postal_code" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Zip/Postal Code<span class="text-[red]">*</span>
          </label>
          <input
            id="postal_code"
            name="postal_code"
            type="text"
            placeholder="Enter Zip/Postal Code"
            value="{{ old('postal_code', $address?->postal_code) }}"
            required
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          >
        </div>
      </div>

      <div class="flex items-center justify-end">
        <button
          type="submit"
          class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          Save
        </button>
      </div>
    </form>

    <aside class="flex min-w-0 flex-1 flex-col gap-4 rounded-lg bg-elevated p-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Your Order</h2>
        <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          You’re subscribing to plan&nbsp;Ginger Trial Plan, and your subscription will be due on&nbsp;25 February 2027 08:06
        </p>
      </div>

      <div class="overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-20">
          <div class="flex flex-col gap-4 text-sm whitespace-nowrap">
            <div class="flex items-start justify-between gap-4">
              <p class="font-medium text-text-muted">Ginger Trial Plan</p>
              <p class="font-normal text-text-body">₹ 5,000.00</p>
            </div>
            <div class="flex items-start justify-between gap-4">
              <p class="font-medium text-text-muted">GST (18%)</p>
              <p class="font-normal text-text-body">₹ 900.00</p>
            </div>
          </div>

          <div class="flex flex-col gap-4">
            <div class="border-t border-dashed border-border"></div>
            <div class="flex items-start justify-between gap-4 text-sm whitespace-nowrap">
              <p class="font-medium text-text-muted">Payment service fee</p>
              <p class="font-normal text-[red]">-₹0.00</p>
            </div>
            <div class="border-t border-dashed border-border"></div>
            <div class="flex items-start justify-between gap-4 text-sm whitespace-nowrap">
              <p class="font-medium text-text-body">Estimated Total</p>
              <p class="font-semibold text-green-700">₹ 5,900</p>
            </div>
            <div class="border-t border-dashed border-border"></div>
          </div>
        </div>
      </div>

      <div class="flex gap-3 rounded-xl bg-stat-orange/15 p-3.5">
        <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
        <p class="text-sm font-medium leading-[1.4] text-text-body">
          By clicking the&nbsp;Proceed with payment&nbsp;button, you agree to our&nbsp;Term of Use&nbsp;and&nbsp;Privacy Policy.
        </p>
      </div>

      <a
        href="{{ route('profile.subscription.payment') }}"
        class="fd-btn flex w-full items-center justify-center overflow-hidden rounded-xl p-3.5 text-center text-base font-extrabold leading-[1.5] text-white opacity-50 transition-opacity hover:opacity-70"
        style="background: linear-gradient(179.25deg, #6dbb48 0%, rgba(17, 153, 170, 0.557) 100%);"
      >
        Check Out
      </a>
    </aside>
  </section>
</x-profile.layout>
