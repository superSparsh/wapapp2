@props(['active' => true, 'submit' => false])

<button
  type="{{ $submit ? 'submit' : 'button' }}"
  role="switch"
  aria-checked="{{ $active ? 'true' : 'false' }}"
  {{ $attributes->class([
    'relative inline-flex h-[18px] w-10 shrink-0 rounded-full shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors cursor-pointer',
    'bg-green-500' => $active,
    'bg-green-50' => ! $active,
    'opacity-50 !cursor-not-allowed' => $attributes->has('disabled'),
  ]) }}
>
  <span
    @class([
      'pointer-events-none absolute top-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left]',
      'left-[24px]' => $active,
      'left-[2px]' => ! $active,
    ])
  ></span>
</button>
