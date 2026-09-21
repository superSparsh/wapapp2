<x-auth.onboarding-layout
    :step="1"
    title="Business Details"
    subtitle="Tell us about your business so we can connect WhatsApp."
>
    <form method="POST" action="{{ route('onboarding.add-isv-terms') }}" class="flex flex-col gap-6" data-validate-form>
        @csrf

        <x-form.input
            id="business_name"
            name="business_name"
            placeholder="Your business name"
            :value="old('business_name', $terms['business_name'] ?? '')"
            :error="$errors->first('business_name')"
            autocomplete="organization"
            required
        >
            <x-slot:label>Business Name <span class="text-red-500">*</span></x-slot:label>
        </x-form.input>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="flex w-full flex-col gap-3">
                <x-form.label for="bm_id">
                    Business Portfolio ID <span class="text-red-500">*</span>
                    <span
                        class="ml-1 inline-flex cursor-help text-text-muted"
                        title="Find it in Meta Business Suite: Settings → Business Info → Business Portfolio ID (sometimes shown as Business Manager ID), or in the URL as business_id=."
                    >ⓘ</span>
                </x-form.label>
                <input
                    id="bm_id"
                    name="bm_id"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                    value="{{ old('bm_id', $terms['bm_id'] ?? '') }}"
                    placeholder="Enter Business Portfolio ID"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 {{ $errors->first('bm_id') ? 'border-red-500' : '' }}"
                >
                @error('bm_id')
                    <p class="text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <x-form.input
                id="website_email"
                name="website_email"
                type="email"
                placeholder="you@example.com"
                :value="old('website_email', $terms['website_email'] ?? '')"
                :error="$errors->first('website_email')"
                autocomplete="email"
                required
            >
                <x-slot:label>Business Email <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
        </div>

        <x-form.input
            id="use_case"
            name="use_case"
            placeholder="Example: Order notifications"
            :value="old('use_case', $terms['use_case'] ?? '')"
            :error="$errors->first('use_case')"
            required
        >
            <x-slot:label>Use Case <span class="text-red-500">*</span></x-slot:label>
        </x-form.input>

        <div class="flex w-full flex-col gap-3">
            <x-form.label for="business_address">Business Address <span class="text-red-500">*</span></x-form.label>
            <textarea
                id="business_address"
                name="business_address"
                rows="3"
                required
                placeholder="Full address"
                class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 {{ $errors->first('business_address') ? 'border-red-500' : '' }}"
            >{{ old('business_address', $terms['business_address'] ?? '') }}</textarea>
            @error('business_address')
                <p class="text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.button type="submit" class="rounded-xl p-3.5">Save &amp; Continue</x-ui.button>
    </form>
</x-auth.onboarding-layout>
