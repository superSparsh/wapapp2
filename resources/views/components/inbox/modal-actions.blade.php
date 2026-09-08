@props(['label' => 'Cancel', 'submit' => 'Send'])

<div class="flex items-center justify-between border-t border-[rgba(90,90,90,0.15)] pt-4">
    <button type="button" data-modal-close class="fd-btn rounded-lg border border-green-500 px-6 py-3 text-base text-green-500 hover:bg-green-50">
        {{ $label }}
    </button>
    <button type="submit" class="fd-btn rounded-lg bg-green-500 px-6 py-3 text-base text-primary-2 hover:opacity-90">
        {{ $submit }}
    </button>
</div>
