# WapApp 2.0 — Database Migration Plan (Legacy → New)

**Source:** `wapdev.tittu.in` (525 migration files, 173 tables)  
**Target:** Master DB + Tenant DB architecture  
**Date:** 27 July 2026

---

## Summary

| Category | Count | Action |
|----------|------:|--------|
| Total legacy tables | 173 | Audited |
| **Keep → Master DB** | 47 | Platform / shared config |
| **Keep → Tenant DB** | 89 | Per-customer operational data |
| **Exclude** | 37 | Acelle email-only (not needed) |

**Legacy tenancy:** `customer_id` = tenant. No `tenant_id` column exists.  
**Line scoping:** `new_contact_id` = WhatsApp phone number / WABA line within a tenant.

---

## Naming Changes (New Project)

| Legacy | New (Recommended) | DB |
|--------|---------------------|-----|
| `customers` | `tenants` | Master |
| `customer_id` | `tenant_id` | All tenant tables |
| `new_contacts` | `whatsapp_lines` | Tenant |
| `new_contact_id` | `whatsapp_line_id` | Tenant |
| `new_templates` | `templates` | Tenant |
| `new_campaigns` | `campaigns` | Tenant |
| `mail_lists` | `lists` | Tenant |
| `subscribers` | `contacts` | Tenant |
| `sub_replies` + `conversations` | `conversations` + `messages` | Tenant (merged/cleaned) |
| `webhooks` (staging) | `webhook_events` | Tenant (append-only, no delete) |
| `webhook_settings` | `webhook_subscriptions` | Tenant |
| `webhook_logs` | `webhook_deliveries` | Tenant |
| `processed_webhooks` | `processed_events` | Master |
| `automation_bots` | `chatbot_flows` | Tenant |

---

## EXCLUDE — Do Not Migrate (37 tables)

Legacy Acelle email infrastructure. WhatsApp project mein zaroorat nahi.

### Email Sending
- `sending_servers`, `sending_domains`, `senders`, `sub_accounts`
- `bounce_handlers`, `bounce_logs`, `feedback_loop_handlers`, `feedback_logs`
- `plans_sending_servers`, `mail_lists_sending_servers`, `customer_group_sending_servers`

### Email Campaigns & Tracking
- `campaigns` (email version), `campaign_links`, `campaign_webhooks`
- `emails`, `email_links`, `attachments`
- `click_logs`, `open_logs`, `tracking_logs`, `tracking_domains`, `unsubscribe_logs`

### Email Templates & Automation
- `templates` (email version), `template_categories`, `templates_categories`
- `automation2s`, `auto_triggers`, `timelines`, `drip_marketing_failed_log`

### Email Verification & Pages
- `email_verification_servers`, `email_verifications`
- `layouts`, `pages`, `blacklists`

### Other
- `email_webhooks`, `products` (Acelle catalog)

---

## MASTER DB — Platform Tables (47 tables)

### Tenant Registry & Auth
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `customers` | `tenants` | Tenant root — name, status, timezone, plan ref |
| `users` | `users` | Login accounts linked to tenant |
| `admins` | `admins` | Super admin users |
| `admin_groups` | `admin_groups` | Admin RBAC |
| `password_resets` | `password_reset_tokens` | Laravel default |
| `user_activations` | — | Merge into users / email verification |
| `sessions` | `sessions` | Laravel default |
| `fcm_tokens` | `fcm_tokens` | Push notifications |
| `domains` | `domains` | **New** — stancl/tenancy subdomain mapping |

### Billing & Plans (Platform Level)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `plans` | `plans` | Subscription plans |
| `subscriptions` | `subscriptions` | Tenant ↔ plan mapping |
| `subscription_logs` | `subscription_logs` | Subscription events |
| `razorpay_subscriptions` | `razorpay_subscriptions` | Payment mandates |
| `zoho_customers` | `zoho_customers` | Zoho Books sync |

### Pricing (Global)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `country_pricing` | `country_pricing` | Per-country Meta message rates |
| `country_pricing_logs` | `country_pricing_logs` | Pricing change audit |
| `country_meta_market` | `country_meta_market` | Country → Meta market |
| `meta_pricings` | — | Merge into country_pricing |
| `conversion_logs` | `conversion_logs` | FX conversion audit |
| `pricing_audit_logs` | `pricing_audit_logs` | Bulk pricing edits |
| `cloud_bill_uploads` | `cloud_bill_uploads` | Meta cloud bill imports |

### Platform Config & Content
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `settings` | `settings` | App key-value config |
| `languages` | `languages` | i18n |
| `countries` | `countries` | Reference data |
| `currencies` | `currencies` | Reference data |
| `customer_groups` | `plan_groups` | Rename for clarity |
| `plugins` | `plugins` | Plugin registry |
| `faq` | `faqs` | Help center |
| `tutorial_videos` | `tutorial_videos` | Tutorials |
| `platform_announcements` | `announcements` | In-app announcements |
| `platform_announcement_user` | `announcement_reads` | Read receipts |
| `announcement` | — | Merge into announcements |
| `customer_onboarding_submissions` | `onboarding_submissions` | Sales forms |
| `api_demo_users` | `demo_users` | Demo signups |
| `api_demo_users_messagess` | `demo_messages` | Demo log |

### Webhooks (Platform Level)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `processed_webhooks` | `processed_events` | Idempotency — message_id dedup |
| `calendly_webhook_logs` | `integration_webhook_events` | Unified ingress log |
| `google_calendar_webhook_logs` | ↑ same table | source = calendly / google_calendar |

### Infrastructure
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `jobs` | `jobs` | Laravel queue |
| `failed_jobs` | `failed_jobs` | Laravel queue |
| `job_batches` | `job_batches` | Laravel queue |
| `job_monitors` | — | Use Horizon instead |
| `ip_locations` | `ip_locations` | GeoIP cache |
| `activity_logs` | `activity_logs` | Platform audit (nullable tenant_id) |
| `wa_health_admin_audit_logs` | `health_audit_logs` | Admin health actions |
| `wa_health_saved_filters` | `health_saved_filters` | Admin filters |
| `new_template_categories` | `template_categories` | Global WA template categories |
| `contacts` | — | Acelle billing address — simplify into tenants |
| `customer_forms` | `public_forms` | Public onboarding (no tenant yet) |
| `tenant_provisioning_logs` | `tenant_provisioning_logs` | **New** — provisioning audit |

---

## TENANT DB — Per-Customer Tables (89 tables)

### Users & Team
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `team_members` | `team_members` | Sub-users / agents |
| `manager_member_assignments` | `team_assignments` | Manager → member mapping |
| `inbox_user` | — | Merge into team_members |
| `notification_contacts` | `notification_contacts` | Alert contacts |

### WhatsApp Lines
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `new_contacts` | `whatsapp_lines` | Phone numbers / WABA lines |
| `phone_number_profiles` | `line_profiles` | Business profile per line |
| `business_infos` | `waba_accounts` | Meta WABA onboarding data |
| `isv_customer_terms` | `isv_terms_acceptances` | Meta ISV terms |

### Messaging / Inbox (CLEANED UP)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `sub_replies` | `conversations` | Thread per contact |
| `conversations` | `messages` | Individual messages in thread |
| `inboxes` | `message_logs` | Campaign + system message log |
| `inboxerror_log` | `inbox_errors` | Processing errors |
| `business_conversation_sessions` | `conversation_sessions` | Meta 24h window |
| `twentyfour_hour_conversation` | — | Merge into conversation_sessions |
| `conversation_states` | `flow_states` | Chatbot runtime state |
| `interactive_messages` | `interactive_messages` | Saved interactive templates |
| `send_test_messages` | `test_messages` | Campaign test sends |

### Templates
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `new_templates` | `templates` | WhatsApp templates |
| `template_cards` | `template_cards` | Carousel cards |
| `template_card_buttons` | `template_card_buttons` | Card buttons |
| `template_variables` | `template_variables` | Template ↔ variable pivot |
| `template_variable_value` | `template_variable_values` | Per-phone values |
| `variables` | `variables` | Custom variables |
| `template_send_response` | `template_responses` | Reply tracking |

### Campaigns
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `new_campaigns` | `campaigns` | WhatsApp campaigns |
| `campaign_audience_consents` | `campaign_consents` | Cold-lead consent |
| `campaigns_lists_segments` | `campaign_audiences` | Campaign ↔ list/segment |

### Audience
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `mail_lists` | `lists` | Audience lists |
| `subscribers` | `contacts` | Contacts in lists |
| `subscriber_fields` | `contact_field_values` | Custom field values |
| `fields` | `custom_fields` | Field definitions |
| `field_options` | `field_options` | Dropdown options |
| `segments` | `segments` | Audience segments |
| `segment_conditions` | `segment_conditions` | Segment rules |
| `forms` | `signup_forms` | Signup forms |
| `formbuilder` | `form_builders` | Drag-drop forms |
| `sources` | `import_sources` | Import sources |
| `websites` | `websites` | Website integrations |
| `whatsapp_consent_events` | `consent_events` | Opt-in/opt-out audit |

### Automation
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `automation_bots` | `chatbot_flows` | Chatbot flow definitions |
| `automation_events` | `automation_events` | Scheduled events |
| `flows` | `meta_flows` | Meta Flow definitions |
| `flow_stats` | `flow_stats` | Flow analytics |
| `trigger_variables` | `trigger_variables` | Campaign trigger vars |

### Webhooks (Tenant — REDESIGNED)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `webhook_settings` | `webhook_subscriptions` | Customer outbound config |
| `webhook_logs` | `webhook_deliveries` | Outbound delivery audit |
| `chatbot_webhook_logs` | ↑ merge into webhook_deliveries | source = chatbot |
| `chatbot_webhook_failures` | `webhook_delivery_failures` | Failed retries |
| `chatbot_webhook_responses` | `webhook_response_data` | Response variables |
| `webhooks` (staging) | `webhook_events` | **NEW** — inbound append-only log |

### Billing / Wallet (Tenant Level)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `wallet_transactions` | `wallet_transactions` | Debit/credit ledger |
| `wallet_auto_recharge_settings` | `auto_recharge_settings` | Auto-recharge config |
| `wallet_auto_recharge_logs` | `auto_recharge_logs` | Auto-recharge history |
| `wallet_zoho_credit_requests` | `zoho_credit_requests` | Zoho invoice credits |
| `wallet_razorpay_credit_requests` | `razorpay_credit_requests` | Razorpay top-up |
| `wallet_display_currency_logs` | `currency_preference_logs` | Currency audit |
| `recharges` | — | Merge into wallet_transactions |
| `new_transactions` | — | Merge into wallet_transactions |
| `invoices` | `invoices` | Tenant invoices |
| `invoice_items` | `invoice_items` | Line items |
| `transactions` | `payment_transactions` | Payment records |
| `recharge_subscriptions` | `recharge_plans` | Recharge plans |
| `renew_subscriptions` | `renewal_tracking` | Renewal tracking |
| `billing_addresses` | `billing_addresses` | Billing address |
| `payments` | `payments` | Razorpay payments |
| `payment_configs` | `payment_configs` | Razorpay config |

### Commerce
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `orders` | `orders` | WhatsApp catalog orders |
| `shopifydetails` | `shopify_stores` | Shopify connection |
| `shopifysenddata` | `shopify_notifications` | Shopify → WA sends |

### Integrations
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `integrations` | `integrations` | Generic integrations |
| `installations` | `installations` | App installations |
| `calendly_integrations` | `calendly_integrations` | Calendly OAuth |
| `calendly_events` | `calendly_events` | Bookings |
| `calendly_message_logs` | `calendly_message_logs` | Calendly → WA |
| `google_calendar_integrations` | `google_calendar_integrations` | GCal OAuth |
| `google_calendar_events` | `google_calendar_events` | GCal events |
| `google_calendar_message_logs` | `google_calendar_message_logs` | GCal → WA |
| `google_calendar_booking_links` | `booking_links` | Public booking links |

### AI
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `ai_bots` | `ai_bots` | AI chatbot config |
| `provider_api_keys` | `ai_api_keys` | Tenant-level AI keys |
| `line_provider_api_keys` | `line_ai_api_keys` | Per-line AI keys |
| `line_ai_settings` | `line_ai_settings` | Per-line AI settings |
| `token_usage_logs` | `ai_token_usage` | Token billing |

### Health & Monitoring (Tenant)
| Legacy Table | New Table | Notes |
|-------------|-----------|-------|
| `wa_health_snapshots` | `health_snapshots` | Daily WA health |
| `wa_health_alerts` | `health_alerts` | Health alerts |
| `wa_template_status_logs` | `template_status_logs` | Template status history |
| `logs` | `activity_logs` | Tenant activity |
| `notifications` | `notifications` | In-app notifications |
| `customer_requests` | `announcement_acknowledgements` | Announcement reads |

---

## Implementation Phases (Which Tables When)

### Phase 1 — Foundation (Week 1-2)
**Master DB:**
- tenants, domains, admins, plans, settings
- countries, currencies, languages
- processed_events, jobs, failed_jobs

**Tenant DB:**
- users, team_members, roles, permissions
- whatsapp_lines, line_profiles, waba_accounts

### Phase 2 — Messaging (Week 3-4)
**Tenant DB:**
- contacts, lists, custom_fields
- conversations, messages, message_logs
- webhook_events, webhook_subscriptions, webhook_deliveries
- conversation_sessions, flow_states, inbox_errors

### Phase 3 — Campaigns & Templates (Week 5-6)
**Tenant DB:**
- templates, template_cards, template_card_buttons, variables
- campaigns, campaign_audiences, campaign_consents
- segments, segment_conditions, test_messages

### Phase 4 — Automation (Week 7-8)
**Tenant DB:**
- chatbot_flows, automation_events, meta_flows, flow_stats
- trigger_variables, interactive_messages
- ai_bots, ai_api_keys, line_ai_settings, ai_token_usage

### Phase 5 — Billing & Commerce (Week 9-10)
**Master DB:** country_pricing, zoho_customers
**Tenant DB:** wallet_transactions, auto_recharge_settings, payments, payment_configs, orders, shopify_stores

### Phase 6 — Integrations (Week 11-12)
**Tenant DB:** calendly_*, google_calendar_*, integrations, installations
**Master DB:** integration_webhook_events

---

## Important Notes

1. **`sub_replies` + `conversations`** — Legacy mein CREATE migration nahi hai. Production DB se schema introspect karna padega migration ke time.

2. **`webhooks` staging table** — Legacy mein migration file nahi mili. Yeh manually bani hogi. New project mein replace with `webhook_events` (append-only).

3. **37 email tables exclude** — Inhe migrate mat karo. Naya project WhatsApp-only hai.

4. **Webhook consolidation** — `webhook_settings` + `webhook_logs` + `chatbot_webhook_*` → 3 clean tables: `webhook_subscriptions`, `webhook_deliveries`, `webhook_delivery_failures`.

5. **Inbox cleanup** — Legacy 3-table model (`inboxes`, `sub_replies`, `conversations`) → New 2-table model (`conversations`, `messages`).

6. **Artisan migration commands**
   - Per-customer tenant data: `php artisan legacy:migrate-customer {email|id} --force`
   - Modules include: templates, interactive_messages, variables, forms, trigger_templates, campaigns (+ inbox recipients), integrations, etc.
   - Central FAQs + tutorials (not per tenant): `php artisan help-center:import-legacy --force`
   - Optional video files: `--copy-videos` with `LEGACY_TUTORIAL_VIDEO_PATH` / `LEGACY_APP_PATH`

---

— End of Document —
