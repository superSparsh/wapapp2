<x-team.form-page
  title="Edit team member"
  :subtitle="$member->displayName().' · update profile details and WhatsApp line access.'"
  :back-url="route('my-team.index')"
  back-label="Back to team list"
  layout-active="my-team.index"
>
  <form
    action="{{ route('my-team.update', $member) }}"
    method="post"
    class="flex flex-col overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]"
  >
    @csrf
    @method('PUT')

    <x-team.member-fields
      :member="$member"
      :show-email="false"
      :password-required="false"
      password-label="New password"
      password-confirm-label="Confirm new password"
      password-placeholder="Leave blank to keep current"
      password-confirm-placeholder="Re-enter new password"
    />

    @if (! empty($whatsappLines))
      <div class="border-b border-divider p-5 sm:p-6">
        <x-team.whatsapp-lines :whatsapp-lines="$whatsappLines" :assigned-line-ids="$assignedLineIds ?? []" />
      </div>
    @endif

    <div class="flex flex-col gap-3 border-b border-divider bg-surface/30 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
      <p class="text-xs text-text-subtle opacity-70">
        Need to change permissions?
        <a href="{{ route('my-team.roles', $member) }}" class="font-semibold text-green-500 hover:underline">Open roles &amp; access</a>
      </p>
    </div>

    <x-team.form-footer
      :cancel-url="route('my-team.index')"
      submit-label="Save changes"
    />
  </form>
</x-team.form-page>
