@props([
    'actions' => ['edit', 'copy', 'trash'],
    'links' => [],
    'confirm' => 'Delete this item? This action cannot be undone.',
])

@php
    $iconMap = [
        'eye' => 'eye.svg',
        'eye-view' => 'eye.svg',
        'edit' => 'edit.svg',
        'trash' => 'trash.svg',
        'copy' => 'copy.svg',
        'chart' => 'chart.svg',
        'user-add' => 'user-add.svg',
    ];

    $deleteActions = ['trash', 'delete'];
@endphp

<div class="flex items-center gap-6">
  @foreach ($actions as $action)
    @php
      $icon = $iconMap[$action] ?? null;
      $href = $links[$action] ?? null;
      $isDelete = in_array($action, $deleteActions, true) && $href;
    @endphp

    @if ($isDelete)
      <form
        method="POST"
        action="{{ $href }}"
        class="inline"
        data-confirm="{{ $confirm }}"
        data-confirm-title="Delete item"
        data-confirm-label="Delete"
      >
        @csrf
        @method('DELETE')
        <button type="submit" class="flex size-5 items-center justify-center" aria-label="{{ ucfirst(str_replace('-', ' ', $action)) }}">
          @if ($icon && file_exists(public_path('images/templates/' . $icon)))
            <img src="{{ asset('images/templates/' . $icon) }}" alt="" class="size-5" width="20" height="20">
          @elseif ($icon && file_exists(public_path('images/icons/table/' . $icon)))
            <img src="{{ asset('images/icons/table/' . $icon) }}" alt="" class="size-5" width="20" height="20">
          @else
            <x-icons.nav-icon :name="$action" class="size-5" />
          @endif
        </button>
      </form>
    @else
      @php $tag = $href ? 'a' : 'button'; @endphp
      <{{ $tag }}
        @if ($href) href="{{ $href }}" @else type="button" @endif
        class="flex size-5 items-center justify-center"
        aria-label="{{ ucfirst(str_replace('-', ' ', $action)) }}"
      >
        @if ($icon && file_exists(public_path('images/templates/' . $icon)))
          <img src="{{ asset('images/templates/' . $icon) }}" alt="" class="size-5" width="20" height="20">
        @elseif ($icon && file_exists(public_path('images/icons/table/' . $icon)))
          <img src="{{ asset('images/icons/table/' . $icon) }}" alt="" class="size-5" width="20" height="20">
        @else
          <x-icons.nav-icon :name="$action" class="size-5" />
        @endif
      </{{ $tag }}>
    @endif
  @endforeach
</div>
