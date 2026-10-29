<x-profile.legal-document
  title="Terms of Service"
  subtitle="The agreement between you and {{ $companyName }} for use of {{ $productName }}, including WhatsApp Business messaging rules and data-protection responsibilities under Indian law."
  active="profile.terms"
  :effective-date="$effectiveDate"
>
  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">1. Agreement</h2>
    <p>
      These Terms of Service (“Terms”) govern access to and use of
      <strong class="font-semibold text-text-primary">{{ $productName }}</strong>, operated by
      <strong class="font-semibold text-text-primary">{{ $companyName }}</strong> (“we”, “us”, “our”), {{ $companyLocation }}.
      By creating an account, inviting team members, or using the service, you agree to these Terms and our
      <a href="{{ route('profile.privacy-policy') }}" class="font-semibold text-primary-2 underline">Privacy Policy</a>.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">2. The service</h2>
    <p>
      {{ $productName }} provides software tools to manage WhatsApp Business messaging, templates, campaigns, inbox,
      automation (including chatbots and flows), audience management, integrations, and related analytics.
      Message delivery depends on third-party networks, including
      <strong class="font-semibold text-text-primary">{{ $whatsappBsp }}</strong>
      and <strong class="font-semibold text-text-primary">{{ $platformName }}</strong>.
      Availability, throughput, and template approval are subject to those providers’ policies and uptime.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">3. Accounts and eligibility</h2>
    <ul class="list-disc space-y-1 pl-5">
      <li>You must provide accurate business and account information and keep it updated.</li>
      <li>You are responsible for all activity under your workspace, including actions by team members you invite.</li>
      <li>You must keep login credentials confidential and enable available security controls (such as two-factor authentication).</li>
      <li>You may use {{ $productName }} only for lawful business purposes and in compliance with WhatsApp Business terms, Meta Commerce / messaging policies, and applicable Indian law (including the DPDP Act, 2023).</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">4. Your content and messaging responsibilities</h2>
    <p>You retain ownership of content you submit to {{ $productName }}. You grant us a limited licence to host, process, transmit, and display that content solely to operate the service for you.</p>
    <p>You represent and warrant that:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li>you have a lawful basis (including notice and consent where required) to message recipients and process their personal data;</li>
      <li>your templates, campaigns, chatbots, and flows do not violate Meta / WhatsApp rules, spam rules, or Indian law;</li>
      <li>you will honour opt-outs and do-not-disturb / quiet-hours obligations applicable to your use case;</li>
      <li>you will not use the service for harassment, fraud, deceptive practices, or prohibited categories of content.</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">5. Data protection roles</h2>
    <p>
      For account and billing data about you and your team, we generally act as a <strong class="font-semibold text-text-primary">Data Fiduciary</strong>.
      For personal data of your end customers that you upload or process through {{ $productName }}, you are typically the Data Fiduciary
      and we act as a <strong class="font-semibold text-text-primary">Data Processor</strong> on your instructions, except where law requires otherwise.
      You must not instruct us to process personal data in a manner that would cause a breach of the DPDP Act or Rules.
    </p>
    <p>
      Messaging infrastructure may process data in <strong class="font-semibold text-text-primary">{{ $processingRegion }}</strong>
      and other locations used by Alibaba Cloud CAMS and Meta / WhatsApp, as described in the Privacy Policy.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">6. Plans, fees, and wallet</h2>
    <p>
      Paid plans, usage charges (including conversation / messaging related charges passed through from Meta or our BSP),
      and wallet top-ups are described in-product. Fees are non-refundable except where required by law or expressly stated by us.
      We may suspend access for non-payment or abusive use after reasonable notice where practicable.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">7. Acceptable use</h2>
    <p>You must not:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li>probe, overload, or disrupt the service or related networks;</li>
      <li>reverse engineer the product except to the extent permitted by law;</li>
      <li>resell or white-label the service without our written consent;</li>
      <li>bypass rate limits, security controls, or phone-line access restrictions;</li>
      <li>upload malware or content that infringes others’ rights.</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">8. Third-party services</h2>
    <p>
      Integrations (payments, calendars, commerce, AI providers you connect, and WhatsApp delivery partners) are governed by those providers’ terms.
      We are not responsible for outages, policy changes, template rejections, or number quality actions taken by Meta, Alibaba CAMS, or other third parties,
      though we will use commercially reasonable efforts to help you diagnose issues within our control.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">9. Intellectual property</h2>
    <p>
      {{ $productName }}, including its software, design, trademarks, and documentation, remains our property or that of our licensors.
      These Terms do not transfer any ownership rights to you other than a limited, revocable right to use the service during your subscription.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">10. Disclaimer and limitation of liability</h2>
    <p>
      The service is provided on an “as available” basis. To the maximum extent permitted by Indian law, we disclaim warranties not expressly stated in these Terms.
      We are not liable for indirect, incidental, special, consequential, or lost-profit damages, or for message non-delivery caused by third-party networks.
      Our aggregate liability arising out of these Terms is limited to the fees you paid to us for {{ $productName }} in the three (3) months preceding the claim,
      except where liability cannot be limited under applicable law (including proven wilful misconduct).
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">11. Suspension and termination</h2>
    <p>
      You may stop using the service at any time. We may suspend or terminate access for material breach, unlawful use, non-payment,
      or risk to the platform or other customers. Upon termination, your licence ends; we may delete or anonymise workspace data
      after any applicable retention / export window described in the Privacy Policy or in-product tools.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">12. Grievances and notices</h2>
    <p>
      Legal or service notices, and privacy / DPDP grievances, may be sent to
      <a href="mailto:{{ $grievanceEmail }}" class="font-semibold text-primary-2 underline">{{ $grievanceEmail }}</a>.
      We aim to respond within <strong class="font-semibold text-text-primary">{{ $grievanceDays }} business days</strong>
      of a complete request.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">13. Governing law</h2>
    <p>
      These Terms are governed by the laws of India. Courts at Bengaluru, Karnataka shall have exclusive jurisdiction,
      without prejudice to mandatory consumer or data-protection remedies available under Indian law.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">14. Changes</h2>
    <p>
      We may update these Terms by posting a revised version in the product with an updated effective date.
      If a change is material, we will provide reasonable notice through the product or email where practicable.
      Continued use after the effective date constitutes acceptance of the updated Terms.
    </p>
  </section>
</x-profile.legal-document>
