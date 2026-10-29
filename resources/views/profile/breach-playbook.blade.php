<x-profile.legal-document
  title="Personal Data Breach Playbook"
  subtitle="Internal incident response plan for {{ $productName }} under DPDP Act, 2023 Section 8(6) — roles, contact tree, decision points, and the {{ $breachBoardHours }}-hour Board report template."
  active="profile.breach-playbook"
  :effective-date="$breachPlaybookDate"
>
  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">0. Document control</h2>
    <ul class="list-disc space-y-1 pl-5">
      <li>Version: <strong class="font-semibold text-text-primary">{{ $breachPlaybookVersion }}</strong></li>
      <li>Owner: <strong class="font-semibold text-text-primary">{{ $breachOwnerName }}</strong> — <a href="mailto:{{ $breachOwnerEmail }}" class="font-semibold text-primary-2 underline">{{ $breachOwnerEmail }}</a></li>
      <li>Escalation: <a href="mailto:{{ $breachEscalationEmail }}" class="font-semibold text-primary-2 underline">{{ $breachEscalationEmail }}</a></li>
      <li>Audience: leadership, engineering on-call, support, and legal counsel only. Not a public customer page.</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">1. What counts as a personal data breach</h2>
    <p>Treat as a potential breach any accidental or unlawful <strong class="font-semibold text-text-primary">destruction, loss, alteration, unauthorised disclosure of, or access to</strong> personal data processed by {{ $productName }}, including:</p>
    <ul class="list-disc space-y-1 pl-5">
      <li>account credentials leaked or admin panel compromise;</li>
      <li>tenant data exposed across tenants or to the public;</li>
      <li>exports, backups, logs, or tickets containing personal data sent to the wrong recipient;</li>
      <li>vendor / BSP incidents (e.g. Alibaba CAMS, Meta, hosting, email) that may affect our customers’ personal data;</li>
      <li>ransomware, insider misuse, or lost devices with production access.</li>
    </ul>
    <p>If unsure, open an incident and escalate — do not wait for perfect confirmation.</p>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">2. Roles (who does what)</h2>
    <div class="overflow-x-auto rounded-xl border border-border-light">
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="bg-surface">
          <tr>
            <th class="p-3 font-semibold text-text-primary">Role</th>
            <th class="p-3 font-semibold text-text-primary">Owner</th>
            <th class="p-3 font-semibold text-text-primary">Duties</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-t border-divider">
            <td class="p-3 font-medium text-text-primary">Incident Commander</td>
            <td class="p-3">{{ $breachOwnerName }}</td>
            <td class="p-3">Declares incident, runs timeline, decides containment / notify / escalate.</td>
          </tr>
          <tr class="border-t border-divider">
            <td class="p-3 font-medium text-text-primary">Technical Lead</td>
            <td class="p-3">Engineering on-call</td>
            <td class="p-3">Contain, preserve evidence, assess systems/data affected, restore service securely.</td>
          </tr>
          <tr class="border-t border-divider">
            <td class="p-3 font-medium text-text-primary">Customer / Support Lead</td>
            <td class="p-3">Support</td>
            <td class="p-3">Tenant outreach drafts, grievance inbox monitoring, FAQ for affected customers.</td>
          </tr>
          <tr class="border-t border-divider">
            <td class="p-3 font-medium text-text-primary">Legal / Compliance</td>
            <td class="p-3">Leadership + counsel</td>
            <td class="p-3">Board notification wording, counsel review, regulatory questions.</td>
          </tr>
          <tr class="border-t border-divider">
            <td class="p-3 font-medium text-text-primary">Communications</td>
            <td class="p-3">CEO / Manager designee</td>
            <td class="p-3">External statements; no public claims without Incident Commander + Legal sign-off.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">3. Contact tree (first 60 minutes)</h2>
    <ol class="list-decimal space-y-2 pl-5">
      <li>Anyone who discovers a suspected breach emails <a href="mailto:{{ $breachOwnerEmail }}" class="font-semibold text-primary-2 underline">{{ $breachOwnerEmail }}</a> with subject <code class="rounded bg-surface px-1.5 py-0.5 text-xs">[BREACH] short summary</code> and pages engineering on-call.</li>
      <li>Incident Commander acknowledges within 30 minutes (business hours) / 60 minutes (off-hours) and opens a private incident channel + written timeline.</li>
      <li>If personal data of customers or end users may be affected, CC escalation <a href="mailto:{{ $breachEscalationEmail }}" class="font-semibold text-primary-2 underline">{{ $breachEscalationEmail }}</a> and Legal.</li>
      <li>If a vendor is involved, open a ticket with that vendor immediately and record the vendor incident ID in the timeline.</li>
    </ol>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">4. Decision points</h2>
    <ul class="list-disc space-y-2 pl-5">
      <li><strong class="font-semibold text-text-primary">Is it a personal data breach?</strong> If personal data was (or is reasonably likely to have been) accessed, disclosed, altered, lost, or destroyed without authorisation → Yes. Proceed.</li>
      <li><strong class="font-semibold text-text-primary">Is {{ $companyName }} the Data Fiduciary for the affected data?</strong> Account/billing/team data → usually Yes. End-customer data inside a tenant workspace → tenant is typically Fiduciary; we still contain, notify the tenant promptly, and assist; Board duties may still apply where we are Fiduciary or as required by law/counsel.</li>
      <li><strong class="font-semibold text-text-primary">Notify the Data Protection Board?</strong> Default: prepare Board packet whenever a confirmed personal data breach exists. Counsel confirms final send. Target: detailed report within <strong class="font-semibold text-text-primary">{{ $breachBoardHours }} hours</strong> of becoming aware.</li>
      <li><strong class="font-semibold text-text-primary">Notify affected Data Principals / tenants?</strong> Yes when there is risk of harm, or when counsel / Board expectations require it. Prefer clear facts over speculation.</li>
      <li><strong class="font-semibold text-text-primary">Suspend a tenant?</strong> If misuse, compromised credentials, or policy breach by a customer creates ongoing risk, Admin may set tenant status to <strong class="font-semibold text-text-primary">suspended</strong> (existing Admin → Customers → toggle status) while investigating.</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">5. Response timeline</h2>
    <ul class="list-disc space-y-2 pl-5">
      <li><strong class="font-semibold text-text-primary">0–1 hour — Contain &amp; record.</strong> Revoke compromised keys/sessions, rotate secrets, isolate affected hosts, preserve logs (do not wipe evidence), start timeline (UTC + IST).</li>
      <li><strong class="font-semibold text-text-primary">1–6 hours — Scope.</strong> What data categories? Which tenants / users? Approximate volume? Attack vector? Still ongoing?</li>
      <li><strong class="font-semibold text-text-primary">Within {{ $breachBoardHours }} hours — Board report.</strong> Complete Section 7 template; Legal reviews; send / file as counsel directs. Keep proof of submission.</li>
      <li><strong class="font-semibold text-text-primary">Same window — Affected parties.</strong> Notify impacted tenants / Data Principals with facts, likely consequences, and steps they should take (password reset, review inbox, etc.).</li>
      <li><strong class="font-semibold text-text-primary">After containment — Remediate &amp; learn.</strong> Root cause, patch, access review, post-incident note, schedule a rehearsal within 12 months.</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">6. Evidence &amp; logging checklist</h2>
    <ul class="list-disc space-y-1 pl-5">
      <li>UTC timestamps for discovery, containment, and notifications</li>
      <li>Systems / tables / buckets / queues touched</li>
      <li>Personal data categories (names, phones, messages, billing, auth logs, etc.)</li>
      <li>Number of Data Principals / tenants potentially affected (best estimate + confidence)</li>
      <li>Actions taken to mitigate and prevent recurrence</li>
      <li>Vendor incident IDs and correspondence</li>
      <li>Copies of notices sent (Board, tenants, individuals)</li>
    </ul>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">7. {{ $breachBoardHours }}-hour Data Protection Board report template</h2>
    <p>Copy into a secure doc when an incident is declared. Fill every field; mark “Unknown — investigating” rather than leaving blank.</p>
    <div class="space-y-3 rounded-xl border border-border-light bg-surface p-4 text-sm">
      <p><strong class="font-semibold text-text-primary">A. Reporter</strong><br>Organisation: {{ $companyName }} ({{ $productName }})<br>Location: {{ $companyLocation }}<br>Contact: {{ $breachOwnerName }} / {{ $breachOwnerEmail }} / {{ $grievanceEmail }}</p>
      <p><strong class="font-semibold text-text-primary">B. Awareness</strong><br>Date/time we became aware (IST + UTC): _______________<br>How discovered: _______________</p>
      <p><strong class="font-semibold text-text-primary">C. Nature of the breach</strong><br>Description: _______________<br>Systems involved: _______________<br>Still ongoing? Yes / No — containment status: _______________</p>
      <p><strong class="font-semibold text-text-primary">D. Personal data affected</strong><br>Categories: _______________<br>Approximate number of Data Principals: _______________<br>Whether children’s data involved: Yes / No / Unknown<br>Cross-border / {{ $processingRegion }} / vendor involvement: _______________</p>
      <p><strong class="font-semibold text-text-primary">E. Likely consequences</strong><br>Identity theft / fraud / spam / confidentiality harm / other: _______________</p>
      <p><strong class="font-semibold text-text-primary">F. Measures taken / proposed</strong><br>Containment: _______________<br>Notification to affected persons / tenants: _______________<br>Prevention of recurrence: _______________</p>
      <p><strong class="font-semibold text-text-primary">G. Attachments</strong><br>Timeline, logs summary, notices, vendor tickets.</p>
    </div>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">8. Affected person / tenant notice (short form)</h2>
    <div class="space-y-2 rounded-xl border border-border-light bg-surface p-4 text-sm">
      <p>We are writing to inform you of a personal data incident affecting {{ $productName }} on [date].</p>
      <p>What happened: [plain language].</p>
      <p>What data may be involved: [categories].</p>
      <p>What we have done: [containment].</p>
      <p>What you should do: [reset passwords / review account activity / contact us].</p>
      <p>Contact for questions or grievances: <a href="mailto:{{ $grievanceEmail }}" class="font-semibold text-primary-2 underline">{{ $grievanceEmail }}</a>.</p>
    </div>
  </section>

  <section class="space-y-2">
    <h2 class="text-lg font-semibold text-text-primary">9. Rehearsal</h2>
    <p>
      Run a tabletop exercise at least once every twelve months: pick a scenario (e.g. leaked export, cross-tenant bug, vendor notice),
      walk Sections 3–7, and record gaps. Update this playbook after each rehearsal or real incident.
    </p>
  </section>
</x-profile.legal-document>
