@php $modal = config('inbox-modals.send-contact'); @endphp

<x-inbox.modal id="send-contact" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-4">
            {{-- Upload Contact --}}
            <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold leading-[1.5] text-text-body" style="font-family: var(--font-display)">Upload Contact</p>
                <div class="flex h-[88px] flex-col items-center justify-center gap-2 rounded-md border border-dashed border-divider bg-elevated px-4 py-3">
                    <img src="{{ asset('images/inbox/modals/vcf-upload.svg') }}" alt="" class="size-6" width="24" height="24">
                    <p class="text-center text-xs font-medium text-text-body" style="font-family: var(--font-display)">Upload VCF Card</p>
                </div>
            </div>

            {{-- Name Information --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Name Information</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Name <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Enter Name" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">First Name <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Enter First Name" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Enter last Name" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Profflix <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Mr." class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Suffix <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Jr" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phone Numbers --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Phone Numbers</p>
                <button type="button" class="self-start text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add Phone Number</button>
            </div>
            <div class="flex flex-col gap-2">
                <p class="fd-label">Phone Numbers</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3 sm:flex-row sm:items-end">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Phone Number <span class="text-red-500">*</span></label>
                        <input type="text" placeholder="Enter phone Number" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Number Type <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                            <span class="flex-1 text-sm font-medium text-text-muted">Type</span>
                            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                        </div>
                    </div>
                    <button type="button" class="flex h-12 w-12 shrink-0 items-end justify-center pb-3.5" aria-label="Remove phone">
                        <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                    </button>
                </div>
                <button type="button" class="self-end text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add</button>
            </div>

            {{-- Email Address --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Email Address</p>
                <button type="button" class="self-start text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add Email</button>
            </div>
            <div class="flex flex-col gap-2">
                <p class="fd-label">Email Address</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3 sm:flex-row sm:items-end">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Email Id <span class="text-red-500">*</span></label>
                        <input type="email" placeholder="Enter Email Id" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Email IdType <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                            <span class="flex-1 text-sm font-medium text-text-muted">Type</span>
                            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                        </div>
                    </div>
                    <button type="button" class="flex h-12 w-12 shrink-0 items-end justify-center pb-3.5" aria-label="Remove email">
                        <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                    </button>
                </div>
                <button type="button" class="self-end text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add</button>
            </div>

            {{-- Address --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Address</p>
                <button type="button" class="self-start text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add Address</button>
            </div>
            <div class="flex flex-col gap-2">
                <p class="fd-label">Address</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3">
                    <div class="flex items-end gap-2">
                        <div class="flex w-full max-w-[340px] flex-col gap-1">
                            <label class="fd-label">Number Type <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                                <span class="flex-1 text-sm font-medium text-text-muted">Type</span>
                                <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                            </div>
                        </div>
                        <button type="button" class="flex h-12 w-12 shrink-0 items-end justify-center pb-3.5" aria-label="Remove address">
                            <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="fd-label">Street <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                            <span class="flex-1 text-sm font-medium text-text-muted">Street</span>
                            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">City <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="City" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">State <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                                <span class="flex-1 text-sm font-medium text-text-muted">State</span>
                                <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Zip Code <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Zip Code" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Country <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                                <span class="flex-1 text-sm font-medium text-text-muted">Country</span>
                                <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="self-end text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add</button>
            </div>

            {{-- Organization --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Organization</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3">
                    <div class="flex flex-col gap-1">
                        <label class="fd-label">Company <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                            <span class="flex-1 text-sm font-medium text-text-muted">Company</span>
                            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Department <span class="text-red-500">*</span></label>
                            <input type="text" placeholder="Department" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="fd-label">Title <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                                <span class="flex-1 text-sm font-medium text-text-muted">Title</span>
                                <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- URLs --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">URLs</p>
                <div class="flex flex-col gap-2 rounded-lg bg-elevated p-3 sm:flex-row sm:items-end">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Link <span class="text-red-500">*</span></label>
                        <input type="url" placeholder="Link" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label class="fd-label">Link Type <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                            <span class="flex-1 text-sm font-medium text-text-muted">Type</span>
                            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                        </div>
                    </div>
                    <button type="button" class="flex h-12 w-12 shrink-0 items-end justify-center pb-3.5" aria-label="Remove link">
                        <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                    </button>
                </div>
                <button type="button" class="self-end text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">Add</button>
            </div>

            {{-- Birthday --}}
            <div class="flex flex-col gap-2">
                <p class="fd-label">Birthday</p>
                <div class="rounded-lg bg-elevated p-3">
                    <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                        <span class="flex-1 text-sm font-medium text-text-muted">Select date</span>
                        <img src="{{ asset('images/inbox/modals/calendar-2.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
                    </div>
                </div>
            </div>

            <x-inbox.modal-actions submit="Create Contact" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
