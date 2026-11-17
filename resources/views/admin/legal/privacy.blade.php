@php
  $docTitle = 'Privacy Policy';
  $docSubtitle = "How {$productName} processes personal data under India’s Digital Personal Data Protection Act, 2023 (DPDP Act) and the Digital Personal Data Protection Rules, 2025.";
@endphp

<x-admin.legal-document
  :title="$docTitle"
  :subtitle="$docSubtitle"
  active="admin.legal.privacy"
  :effective-date="$effectiveDate"
>
  @include('legal.privacy-policy-body')
</x-admin.legal-document>
