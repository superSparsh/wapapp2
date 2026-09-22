@if (! empty($isAdminImpersonating) && ! empty($canAccessAdminView) && empty($isAdminOwnCustomer))
  <div class="flex items-center justify-between gap-4 border-b border-green-500/25 bg-green-50 px-4 py-2 text-sm text-green-700">
    <span>
      You are logged in as
      <strong>{{ $adminImpersonatedTenantName ?: ($currentAccountName ?? 'customer') }}</strong>
      @if (! empty($adminImpersonatorName))
        <span class="text-green-600/80">(via {{ $adminImpersonatorName }})</span>
      @endif
    </span>
    <form method="post" action="{{ route('admin.impersonation.stop') }}">
      @csrf
      <button type="submit" class="font-semibold text-green-800 underline hover:text-green-900">Return to admin</button>
    </form>
  </div>
@elseif (! empty($isAdminImpersonating) && empty($isAdminOwnCustomer))
  {{-- Regular login-as (not the admin's own customer account): keep a way back --}}
  <div class="flex items-center justify-between gap-4 border-b border-green-500/25 bg-green-50 px-4 py-2 text-sm text-green-700">
    <span>
      You are logged in as
      <strong>{{ $adminImpersonatedTenantName ?: ($currentAccountName ?? 'customer') }}</strong>
    </span>
    <form method="post" action="{{ route('admin.impersonation.stop') }}">
      @csrf
      <button type="submit" class="font-semibold text-green-800 underline hover:text-green-900">Return to admin</button>
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
