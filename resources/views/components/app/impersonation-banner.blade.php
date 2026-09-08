@if (! empty($isAdminImpersonating))
  <div class="flex items-center justify-between gap-4 border-b border-amber-500/30 bg-amber-50 px-4 py-2 text-sm text-amber-900">
    <span>
      Admin view as customer
      @if (! empty($adminImpersonatorName))
        (via <strong>{{ $adminImpersonatorName }}</strong>)
      @endif
    </span>
    <form method="post" action="{{ route('admin.impersonation.stop') }}">
      @csrf
      <button type="submit" class="font-semibold text-amber-900 underline">Return to admin</button>
    </form>
  </div>
@elseif (! empty($isImpersonating) && $impersonatedMember)
  <div class="flex items-center justify-between gap-4 border-b border-amber-500/30 bg-amber-50 px-4 py-2 text-sm text-amber-900">
    <span>Impersonating <strong>{{ $impersonatedMember->displayName() }}</strong></span>
    <form method="post" action="{{ route('manager.back-to-me') }}">
      @csrf
      <button type="submit" class="font-semibold text-amber-900 underline">Back to manager</button>
    </form>
  </div>
@endif
