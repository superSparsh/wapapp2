@php $modal = config('inbox-modals.export-by-date'); @endphp
<x-inbox.modal id="export-by-date" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-export-form>
            <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold text-text-primary">Date range</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-export-preset="today" class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium hover:border-green-500">Today</button>
                    <button type="button" data-export-preset="7d" class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium hover:border-green-500">Last 7 days</button>
                    <button type="button" data-export-preset="30d" class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium hover:border-green-500">Last 30 days</button>
                    <button type="button" data-export-preset="month" class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium hover:border-green-500">This month</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <label for="export_from" class="text-xs font-medium text-text-muted">From</label>
                        <input id="export_from" type="date" name="from" required class="w-full rounded-xl border border-border bg-elevated p-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="export_to" class="text-xs font-medium text-text-muted">To</label>
                        <input id="export_to" type="date" name="to" required class="w-full rounded-xl border border-border bg-elevated p-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold text-text-primary">What to export</p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="export_scope" value="all" checked>
                    All chats in this date range
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="export_scope" value="current" @disabled(empty($selectedConversation ?? null))>
                    Only the open chat
                </label>
            </div>

            <div class="flex flex-col gap-2">
                <label for="export_skip_phones" class="text-sm font-semibold text-text-primary">Skip these numbers (optional)</label>
                <textarea id="export_skip_phones" name="skip_phones" rows="3" placeholder="One phone number per line" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>

            <p class="hidden text-xs text-red-500" data-inbox-export-error></p>
            <x-inbox.modal-actions submit="Download CSV" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
