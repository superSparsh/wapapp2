# DPDP 100% build plan (WapApp / Tekpro)

**Publish rule:** Privacy Policy, Terms, Breach playbook, cookie banner, and public footer/login links stay **draft / login-gated** until leadership review. Flip with `LEGAL_PUBLIC_PUBLISH=true` only after sign-off.

## Product (in-app) — build in code

| Area | Status target | Notes |
|------|---------------|--------|
| Legal pages (privacy / terms / breach) | Done (draft) | Auth-only until publish flag |
| Meta policy links at signup | Done | Config + signup consents |
| Custom terms + PDF + admin review | Done | Central `business_custom_terms` |
| Signed onboarding PDF | Done | forms.tekprocloud.com |
| Account export / deletion | Partial | Exists; keep out of public nav until ready |
| **Consent event ledger** | Building | Central `consent_events` |
| **Rights + grievance requests** | Building | Central `data_principal_requests` + profile + admin |
| **Collection-point notice** | Building | Reusable Blade at signup / forms |
| **Children / age gate** | Building | Affirmation ≥ `LEGAL_MINIMUM_AGE` at register |
| Cookie / SDK banner | Stub (off) | Component exists; `LEGAL_COOKIE_BANNER_ENABLED=false` |
| AI processing disclosure | Follow-up | Surface on AI bot settings when bots process personal data |
| Public publish | Explicitly OFF | `LEGAL_PUBLIC_PUBLISH=false` |

## Org / infra (evidence outside app)

Track on Admin → DPDP readiness as pending/partial until evidence attached:

- Encryption at rest / in transit evidence
- Vendor DPA register (Meta, Alibaba CAMS, Tittu, hosting)
- Access control / logging evidence
- Incident drills against breach playbook
- Staff DPDP training records
- Significant Data Fiduciary (SDF) applicability (N/A until notified)
- Board / leadership legal review sign-off

## Publish checklist (after review)

1. Legal copy signed off
2. Set `LEGAL_PUBLIC_PUBLISH=true`
3. Link Privacy + Terms on login/footer
4. Optionally enable cookie banner
5. Re-run Admin DPDP readiness — public_publish → done
