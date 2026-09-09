@props([
    'actions' => ['edit', 'copy', 'trash'],
    'links' => [],
    'methods' => [],
    'confirm' => 'Delete this item? This action cannot be undone.',
    'confirmTitle' => 'Delete item',
    'confirmLabel' => 'Delete',
])

@php
    $iconMap = [
        'eye' => ['images/icons/table/eye.svg', 'images/templates/eye.svg'],
        'eye-view' => ['images/icons/table/eye.svg', 'images/templates/eye.svg'],
        'edit' => ['images/icons/table/edit.svg', 'images/templates/edit.svg', 'images/campaigns/edit.svg'],
        'trash' => ['images/icons/table/trash.svg', 'images/templates/trash.svg', 'images/campaigns/trash.svg'],
        'delete' => ['images/icons/table/trash.svg', 'images/templates/trash.svg', 'images/campaigns/trash.svg'],
        'copy' => ['images/campaigns/copy-figma.svg', 'images/campaigns/copy.svg'],
        'chart' => ['images/campaigns/chart.svg'],
        'user-add' => ['images/campaigns/add.svg'],
        'login' => ['images/campaigns/add.svg'],
        'login-as' => ['images/campaigns/add.svg'],
        'toggle' => ['images/campaigns/refresh.svg'],
        'approve' => ['images/campaigns/add.svg'],
    ];

    $resolveIcon = function (string $action) use ($iconMap): ?string {
        foreach ($iconMap[$action] ?? [] as $path) {
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    };

    $deleteActions = ['trash', 'delete'];
@endphp

<div {{ $attributes->class('flex items-center justify-center gap-6') }}>
  @foreach ($actions as $action)
    @php
      $href = $links[$action] ?? null;
      $method = strtoupper((string) ($methods[$action] ?? (in_array($action, $deleteActions, true) ? 'DELETE' : 'GET')));
      $iconSrc = $resolveIcon($action);
      $label = ucfirst(str_replace(['-', '_'], ' ', $action));
      $isForm = $href && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
      $isDelete = in_array($action, $deleteActions, true);
    @endphp

    @if ($isForm)
      <form
        method="POST"
        action="{{ $href }}"
        class="inline"
        @if ($isDelete)
          data-confirm="{{ $confirm }}"
          data-confirm-title="{{ $confirmTitle }}"
          data-confirm-label="{{ $confirmLabel }}"
          data-confirm-variant="danger"
        @elseif ($action === 'approve')
          data-confirm="{{ $confirm }}"
          data-confirm-title="{{ $confirmTitle }}"
          data-confirm-label="{{ $confirmLabel }}"
        @endif
      >
        @csrf
        @if ($method !== 'POST')
          @method($method)
        @endif
        <button type="submit" class="flex size-5 items-center justify-center" aria-label="{{ $label }}" title="{{ $label }}">
          @if ($iconSrc)
            <img src="{{ $iconSrc }}" alt="" class="size-5" width="20" height="20">
          @else
            <span class="text-[10px] font-bold text-text-subtle">{{ strtoupper(substr($action, 0, 1)) }}</span>
          @endif
        </button>
      </form>
    @else
      @php $tag = $href ? 'a' : 'button'; @endphp
      <{{ $tag }}
        @if ($href) href="{{ $href }}" @else type="button" @endif
        class="flex size-5 items-center justify-center"
        aria-label="{{ $label }}"
        title="{{ $label }}"
      >
        @if ($iconSrc)
          <img src="{{ $iconSrc }}" alt="" class="size-5" width="20" height="20">
        @else
          <span class="text-[10px] font-bold text-text-subtle">{{ strtoupper(substr($action, 0, 1)) }}</span>
        @endif
      </{{ $tag }}>
    @endif
  @endforeach
</div>
