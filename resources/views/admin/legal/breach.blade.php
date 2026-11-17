@php
  $docTitle = 'Personal Data Breach Playbook';
  $docSubtitle = "Internal incident response plan for {$productName} under DPDP Act, 2023 Section 8(6) — roles, contact tree, decision points, and the {$breachBoardHours}-hour Board report template.";
@endphp

<x-admin.legal-document
  :title="$docTitle"
  :subtitle="$docSubtitle"
  active="admin.legal.breach"
  :effective-date="$breachPlaybookDate"
>
  @include('legal.breach-playbook-body')
</x-admin.legal-document>
