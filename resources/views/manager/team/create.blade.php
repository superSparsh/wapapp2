<x-team.form-page
  title="Create team member"
  subtitle="Add their details — we’ll email an invite so they can set a password. They’re assigned to you automatically."
  :back-url="route('manager.team.index')"
  back-label="Back to assigned members"
  layout-active="manager.team.index"
  :show-stepper="true"
  step-label="Details → Roles & access"
>
  <form
    action="{{ route('manager.team.store') }}"
    method="post"
    class="flex flex-col overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]"
  >
    @csrf
    <input type="hidden" name="role" value="member">

    <x-team.member-fields :invite-mode="true" />

    @if (! empty($whatsappLines))
      <div class="border-b border-divider p-5 sm:p-6">
        <x-team.whatsapp-lines :whatsapp-lines="$whatsappLines" />
      </div>
    @endif

    <x-team.form-footer
      :cancel-url="route('manager.team.index')"
      submit-label="Create & continue"
      hint="Member will be assigned to you automatically."
      :show-arrow="true"
    />
  </form>
</x-team.form-page>
