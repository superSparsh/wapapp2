@php
  $selectedRole = old('role', 'member');
@endphp

<x-team.form-page
  title="Create team member"
  subtitle="Add login credentials and basic details. You will set module permissions on the next step."
  :back-url="route('my-team.index')"
  back-label="Back to team list"
  layout-active="my-team.index"
  :show-stepper="true"
  step-label="Details → Roles & access"
>
  <form
    action="{{ route('my-team.store') }}"
    method="post"
    class="flex flex-col overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]"
  >
    @csrf

    <x-team.role-selector :roles="$roles" :selected="$selectedRole" />
    <x-team.member-fields />

    @if (! empty($whatsappLines))
      <div class="border-b border-divider p-5 sm:p-6">
        <x-team.whatsapp-lines :whatsapp-lines="$whatsappLines" />
      </div>
    @endif

    <x-team.form-footer
      :cancel-url="route('my-team.index')"
      submit-label="Create & continue"
      hint="Next step: configure inbox, templates, audience & campaign access."
      :show-arrow="true"
    />
  </form>
</x-team.form-page>
