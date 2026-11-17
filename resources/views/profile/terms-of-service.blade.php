<x-profile.legal-document
  title="Terms of Service"
  subtitle="The agreement between you and {{ $companyName }} for use of {{ $productName }}, including WhatsApp Business messaging rules and data-protection responsibilities under Indian law."
  active="profile.terms"
  :effective-date="$effectiveDate"
>
  @include('legal.terms-of-service-body')
</x-profile.legal-document>
