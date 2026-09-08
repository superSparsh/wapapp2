@props(['showModal' => false])

<x-auth.signup-layout :step="5">
    <form action="{{ route('signup.step-5.store') }}" method="POST" class="flex flex-col gap-6">
        @csrf

        <x-auth.signup-question
            name="answer"
            description="Business Manager Verification: Required for access to advanced WhatsApp features."
            question='Q. Do you have your Meta Business Manager verified? <span class="text-red-500">*</span>'
        />

        @error('answer')
            <p class="text-sm font-medium text-red-500">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex gap-4">
            <x-ui.link-button href="{{ route('signup.step-4') }}" variant="outline" class="flex-1 rounded-xl border border-border bg-elevated p-3.5 text-base font-extrabold text-primary-2">Back</x-ui.link-button>
            <x-ui.button type="submit" class="flex-1 rounded-xl p-3.5">SUBMIT</x-ui.button>
        </div>
    </form>

    @if ($showModal)
        <x-slot:modal>
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-overlay p-4">
                <div class="w-full max-w-[681px] rounded-2xl bg-elevated p-5 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-green-50 text-green-500">i</span>
                            <h2 class="fd-modal-title">Information</h2>
                        </div>
                        <a href="{{ route('signup.step-5') }}" class="text-2xl leading-none text-text-muted" aria-label="Close">&times;</a>
                    </div>

                    <div class="flex flex-col gap-5 px-5 py-4 text-sm font-medium leading-[1.4] text-text-subtle">
                        <div>
                            <p class="font-semibold text-text-body">Proactive Messaging Limit:</p>
                            <p class="mt-2">Please note that if your account is not verified, there is a limitation in place. You will be able to send proactive messages to only 250 unique contacts every 24 hours.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-text-body">Unlimited Replies:</p>
                            <p class="mt-2">Despite the limitation on proactive messages, you can always send unlimited replies to user-initiated conversations.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-text-body">Account Deletion:</p>
                            <p class="mt-2">If you wish to associate a phone number that is already registered with WhatsApp, you can initiate the deletion process. However, keep in mind that it may take up to 3 minutes for the disconnected number to become available for association.</p>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end px-5">
                        <a href="{{ route('signup.register') }}" class="rounded-lg bg-green-500 px-6 py-3 text-base font-semibold text-white">Okay</a>
                    </div>
                </div>
            </div>
        </x-slot:modal>
    @endif
</x-auth.signup-layout>
