@props(['name', 'class' => 'size-5'])

@php
$iconMap = [
    'smart-home' => 'icons/smart-home.svg',
    'device-message' => 'icons/device-message.svg',
    'programming-arrows' => 'icons/programming-arrows.svg',
    'clipboard-text' => 'icons/clipboard-text.svg',
    'profile-2user' => 'icons/profile-2user.svg',
    'document-text' => 'icons/document-text.svg',
    'data' => 'icons/data.svg',
    'shopping-cart' => 'icons/shopping-cart.svg',
    'logout' => 'icons/logout.svg',
    'search' => 'icons/header/search-normal.svg',
    'search-inbox' => 'icons/search-inbox.svg',
    'bell' => 'icons/header/bell-base.svg',
    'refresh' => 'icons/refresh-2.svg',
    'refresh-2' => 'icons/refresh-2.svg',
    'arrow-down' => 'icons/arrow-down-linear.svg',
    'arrow-right' => 'icons/arrow-right-linear.svg',
    'chevron-right' => 'icons/chevron-right-pg2.svg',
    'add' => 'icons/add.svg',
    'send' => 'charts/send-2.svg',
    'warning' => 'charts/warning-2.svg',
    'tick-circle' => 'charts/tick-circle-linear.svg',
    'task' => 'charts/task-linear.svg',
    'task-square' => 'charts/task-square-linear.svg',
    'group' => 'charts/group-linear.svg',
    'rotate-right' => 'icons/rotate-right-linear.svg',
    'wallet' => 'icons/wallet.svg',
    'microphone' => 'icons/microphone.svg',
    'arrow-square-left' => 'icons/arrow-square-left.svg',
    'eye' => 'icons/eye.svg',
    'tick-square' => 'icons/tick-square.svg',
    'hierarchy-3' => 'icons/hierarchy-3.svg',
    'graph' => 'dashboard/graph.svg',
    'presentation-chart' => 'dashboard/presentation-chart.svg',
    'personal-card' => 'dashboard/personal-card.svg',
    'danger' => 'dashboard/danger.svg',
    'edit' => 'charts/edit.svg',
    'copy' => 'charts/copy.svg',
    'trash' => 'charts/trash.svg',
    'eye-view' => 'icons/eye.svg',
    'chart' => 'charts/chart-bulk.svg',
    'user-add' => 'icons/user-add.svg',
    'info-circle' => 'charts/info-circle.svg',
    'chevron-left' => 'icons/chevron-right.svg',
    'box' => 'icons/box.svg',
    'circle-square' => 'icons/circle-square.svg',
    'ticket' => 'icons/ticket.svg',
];
$path = $iconMap[$name] ?? null;
@endphp

@if ($path)
  <img src="{{ asset('images/' . $path) }}" alt="" {{ $attributes->merge(['class' => $class]) }} width="20" height="20" />
@else
  <span {{ $attributes->merge(['class' => $class . ' inline-block rounded bg-border-light']) }}></span>
@endif
