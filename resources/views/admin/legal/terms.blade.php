@php
  $docTitle = 'Terms of Service';
  $docSubtitle = "The agreement between you and {$companyName} for use of {$productName}, including WhatsApp Business messaging rules and data-protection responsibilities under Indian law.";
@endphp

<x-admin.legal-document
  :title="$docTitle"
  :subtitle="$docSubtitle"
  active="admin.legal.terms"
  :effective-date="$effectiveDate"
>
  @include('legal.terms-of-service-body')
</x-admin.legal-document>
