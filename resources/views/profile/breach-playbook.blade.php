<x-profile.legal-document
  title="Personal Data Breach Playbook"
  subtitle="Internal incident response plan for {{ $productName }} under DPDP Act, 2023 Section 8(6) — roles, contact tree, decision points, and the {{ $breachBoardHours }}-hour Board report template."
  active="profile.breach-playbook"
  :effective-date="$breachPlaybookDate"
>
  @include('legal.breach-playbook-body')
</x-profile.legal-document>
