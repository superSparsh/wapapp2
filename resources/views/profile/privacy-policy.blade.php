<x-profile.legal-document
  title="Privacy Policy"
  subtitle="How {{ $productName }} processes personal data under India’s Digital Personal Data Protection Act, 2023 (DPDP Act) and the Digital Personal Data Protection Rules, 2025."
  active="profile.privacy-policy"
  :effective-date="$effectiveDate"
>
  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">1. Who we are</h2>
    <p>
      {{ $companyName }} (“we”, “us”, “our”), based in {{ $companyLocation }}, operates
      <strong class="font-semibold text-text-primary">{{ $productName }}</strong>, a WhatsApp Business messaging and automation platform.
      For personal data processed on {{ $productName }}, we act as a <strong class="font-semibold text-text-primary">Data Fiduciary</strong>
      under the DPDP Act. Individuals whose personal data we process are <strong class="font-semibold text-text-primary">Data Principals</strong>.
    </p>
    <p>
      Where you (our customer / tenant) upload or sync contacts, messages, or customer records into {{ $productName }},
      you are typically the Data Fiduciary for that end-customer data, and we process it as a <strong class="font-semibold text-text-primary">Data Processor</strong>
      on your documented instructions, except where law requires us to process it independently.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">2. Personal data we process</h2>
    <p>Depending on how you use {{ $productName }}, we may process:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li><span class="font-medium text-text-primary">Account data</span> — name, work email, phone, business details, billing and subscription information.</li>
      <li><span class="font-medium text-text-primary">Workspace data</span> — team members, roles, activity logs, security events (including IP and device signals where collected for security).</li>
      <li><span class="font-medium text-text-primary">Messaging &amp; audience data</span> — WhatsApp phone numbers, contact profiles, message content and metadata, templates, chatbot / flow responses, campaign recipients, and related media you store in the product.</li>
      <li><span class="font-medium text-text-primary">Integration data</span> — credentials and records needed to connect WhatsApp lines, payments, calendars, commerce, or other apps you enable.</li>
      <li><span class="font-medium text-text-primary">Support &amp; grievance data</span> — tickets, emails, and correspondence you send to us.</li>
    </ul>
    <p>Please avoid uploading children’s data or special categories of data unless you have a lawful basis and have configured the product accordingly.</p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">3. Purpose of processing</h2>
    <p>We process personal data only for lawful purposes, including to:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li>provide, operate, secure, and improve {{ $productName }};</li>
      <li>authenticate users, enforce access control, and prevent abuse or fraud;</li>
      <li>deliver WhatsApp Business messages and related automation that you configure;</li>
      <li>process billing, invoices, and plan entitlements;</li>
      <li>respond to support requests and grievances;</li>
      <li>comply with applicable law, Meta / WhatsApp policies, and lawful requests from authorities.</li>
    </ul>
    <p>We do not sell personal data. We do not use your workspace messaging content to train public AI models.</p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">4. Consent and notice</h2>
    <p>
      Where processing is based on consent, we seek free, specific, informed, unconditional, and clear consent for the stated purpose,
      and you may withdraw consent in the manner we provide (subject to law and residual contractual necessity).
      Where processing is for a legitimate use permitted under the DPDP Act (for example employment, employment-related purposes for our staff,
      or compliance with law), we rely on that basis and still apply purpose limitation and security safeguards.
    </p>
    <p>
      As a customer using {{ $productName }} to message end users, you are responsible for providing the required notice to Data Principals
      before collecting their personal data, obtaining valid consent or other lawful grounds, and honouring opt-outs for commercial communications
      in line with WhatsApp Business and applicable Indian law.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">5. Cross-border processing (Singapore &amp; messaging partners)</h2>
    <p>
      To deliver WhatsApp Business messaging, {{ $productName }} uses
      <strong class="font-semibold text-text-primary">{{ $whatsappBsp }}</strong>.
      Related processing for message routing, delivery, and template / quality operations may occur in
      <strong class="font-semibold text-text-primary">{{ $processingRegion }}</strong>
      and in other locations operated by Alibaba Cloud and
      <strong class="font-semibold text-text-primary">{{ $platformName }}</strong>.
    </p>
    <p>
      By using {{ $productName }}, you acknowledge that personal data necessary for WhatsApp delivery may be transferred outside India
      to these processors / platforms for the purpose of providing the service. We take reasonable steps to ensure such transfers
      are limited to what is needed for the purpose and are subject to contractual and technical safeguards available from those providers.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">6. Retention</h2>
    <p>
      We retain personal data only for as long as needed for the purposes above, or as required by law, Meta / WhatsApp rules,
      dispute resolution, or security logging. Account owners may export or schedule deletion of eligible tenant modules from
      Account tools where enabled. Backups and logs may persist for a limited additional period before irreversible deletion.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">7. Security safeguards</h2>
    <p>
      We apply reasonable security safeguards appropriate to the nature of the data and the risk of harm, including access controls,
      encryption in transit where supported, tenant isolation in the application layer, activity logging, and staff access on a need-to-know basis.
      No method of transmission or storage is perfectly secure; please protect your credentials and enable available security features such as two-factor authentication.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">8. Your rights as a Data Principal</h2>
    <p>Subject to the DPDP Act and applicable Rules, Data Principals may request:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li>a summary of personal data being processed and the processing activities;</li>
      <li>correction, completion, or updating of personal data;</li>
      <li>erasure of personal data that is no longer necessary for the stated purpose (subject to legal retention);</li>
      <li>withdrawal of consent where processing is consent-based;</li>
      <li>grievance redressal and nomination of another individual in case of death or incapacity, as provided under the Act / Rules.</li>
    </ul>
    <p>
      End customers of our tenants should first contact the business that messaged them. We will assist tenants with verifiable requests
      relating to data we process on their behalf.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">9. Grievance Officer / contact</h2>
    <p>
      For privacy or DPDP-related grievances about {{ $productName }}, contact us at
      <a href="mailto:{{ $grievanceEmail }}" class="font-semibold text-primary-2 underline">{{ $grievanceEmail }}</a>.
      We aim to acknowledge and address grievances within <strong class="font-semibold text-text-primary">{{ $grievanceDays }} business days</strong>
      of a complete request, or sooner where the Rules prescribe a shorter timeline once applicable to us.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">10. Children’s data</h2>
    <p>
      {{ $productName }} is intended for business users. We do not knowingly offer the service to children as defined under the DPDP Act.
      If you believe a child’s personal data has been provided to us without verifiable parental consent where required, contact
      <a href="mailto:{{ $grievanceEmail }}" class="font-semibold text-primary-2 underline">{{ $grievanceEmail }}</a>
      so we can take appropriate steps.
    </p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">11. Updates</h2>
    <p>
      We may update this Privacy Policy to reflect product, legal, or operational changes. Material updates will be reflected on this page
      with a revised effective date. Continued use of {{ $productName }} after an update constitutes notice of the revised policy for account holders.
    </p>
  </section>
</x-profile.legal-document>
