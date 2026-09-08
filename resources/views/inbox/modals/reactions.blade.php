@php $modal = config('inbox-modals.reactions'); @endphp
<x-inbox.modal id="reactions" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6" data-inbox-reaction-form>
            <div class="flex flex-col gap-2">
                <x-form.label for="reaction_emoji">Reaction <span class="text-red-500">*</span></x-form.label>
                <select
                    id="reaction_emoji"
                    name="emoji"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    required
                >
                    <option value="👍">👍 Thumbs up</option>
                    <option value="❤️">❤️ Heart</option>
                    <option value="😂">😂 Laugh</option>
                    <option value="😮">😮 Wow</option>
                    <option value="😢">😢 Sad</option>
                    <option value="🙏">🙏 Thanks</option>
                </select>
            </div>
            <p class="text-xs text-text-subtle">Reactions are sent as a quick session message within the 24-hour window.</p>
            <p class="hidden text-xs text-red-500" data-inbox-reaction-error></p>
            <x-inbox.modal-actions submit="Send Reaction" />
        </form>
    </x-inbox.modal-form>
    <x-inbox.phone-preview />
</x-inbox.modal>
