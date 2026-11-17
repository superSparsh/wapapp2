<x-profile.legal-document
  title="Privacy Policy"
  subtitle="How {{ $productName }} processes personal data under India’s Digital Personal Data Protection Act, 2023 (DPDP Act) and the Digital Personal Data Protection Rules, 2025."
  active="profile.privacy-policy"
  :effective-date="$effectiveDate"
>
  @include('legal.privacy-policy-body')
</x-profile.legal-document>
