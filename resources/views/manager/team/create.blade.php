<x-team.form-page
  title="Create team member"
  subtitle="Add a team member to your group. They will be auto-assigned to you and you can set permissions next."
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

    <x-team.member-fields />

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
