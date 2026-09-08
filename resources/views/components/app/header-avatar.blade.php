@props(['src' => null])

@php
    $avatarSrc = $src ?? ($currentAccountAvatar ?? asset('images/profile/photo-sample.png'));
@endphp

<div class="relative size-[38px] shrink-0">
    <div class="size-full overflow-hidden rounded-full">
        <img src="{{ $avatarSrc }}" alt="User avatar" class="size-full object-cover" width="38" height="38">
    </div>
    <span class="absolute bottom-[3.32%] right-[5.89%] block size-3">
        <img src="{{ asset('images/icons/header/status-dot-fd.svg') }}" alt="" class="size-full" width="12" height="12">
    </span>
</div>
