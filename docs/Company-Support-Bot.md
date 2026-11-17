# WapApp company support bot (Inbox handoff)

Internal runbook for the Tittu/WapApp **own** WhatsApp support number — not a multi-tenant product feature.

Stack:

1. **Chatbot flow** — welcome menu, FAQ branches, Talk to agent
2. **Support AiBot + Knowledge Base (Chroma)** — free-text answers
3. **`handoff_to_inbox`** — real human queue in WapApp Inbox

Later: swap `SupportEscalationChannel` binding from Inbox → Tittu Support tickets.

---

## 1. Ops guards (required)

On the **support tenant** (the account that owns the support WhatsApp line):

| Setting | Value | Why |
|--------|--------|-----|
| AI Assistant → **Auto response** (`ai_auto_response_enabled`) | **Off** | If On, `human_response` chats can still get AI replies and undo handoff |
| AI answers | Only via Chatbot **Natural Language** nodes | Menu + FAQ stay deterministic; AI is opt-in in the flow |
| Inbox | Agents watch the support line + **Unassigned** filter | Handoff leaves chats unassigned on purpose |

After handoff, `SupportHandoffService` sets `response_type=human_response`, clears `active_ai_bot_id`, completes active chatbot states, and sends the confirmation WhatsApp text.

If auto-response is left On, handoff logs a warning; turn it Off before go-live.

---

## 2. Create the Support AiBot + KB

1. Open **AI Assistant → Bots** → create bot named e.g. `WapApp Support`, `type` / notes = support.
2. System prompt (keep short and strict), for example:

```
You are WapApp customer support on WhatsApp.
Answer only from the knowledge base and business info.
If you are not sure, say you are not sure and suggest talking to a human agent.
Never invent billing, refund, or account changes — suggest an agent.
Keep replies short for WhatsApp.
```

3. **Knowledge Base** tab: upload / scrape Help Center FAQs, pricing notes, template/QR/messaging-limit troubleshooting, onboarding steps. Chroma is source of truth (see `docs/Knowledge-Base-Chroma.md`). Do not rely on MySQL for KB corpus.
4. Prefer this bot on the Natural Language node in the support flow (pick bot by id). Default bot is fine if this is the only bot on the tenant.

---

## 3. Build the Chatbot flow

**Triggers:** `hi`, `hello`, `help`, `support`, `menu` (welcome keyword / list as your builder allows).

**Main interactive menu (buttons or list):**

- Billing / Payments
- Technical issue
- Account / WhatsApp line
- Ask a question (AI)
- Talk to agent

### Category branches

Short fixed text answers + follow-up buttons: *Was this helpful?* / *Ask AI* / *Talk to agent*.

### Ask a question (AI)

1. Wait for response (user free text)
2. **Natural Language** node: mode `knowledge_base`, select Support AiBot, send directly
3. Interactive: *Did this help?* → Yes (back to menu) / No → handoff confirm

### Talk to agent

1. Confirm: “Agent se baat karni hai?” → Yes / No  
2. On Yes: **Function Call** node:
   - Function name: `handoff_to_inbox`
   - Optional parameter `confirmation_message` to override the default copy
3. Do **not** add fake “agent connecting” text without this function — it would not stop the bot.

Registered in `config/chatbot.php` → `function_registry.handoff_to_inbox` → `HandoffToInboxHandler`.

### Offline / business hours

Use DateTime / offline message on welcome or agent path, e.g.:

> Agents available Mon–Sat 10:00–19:00 IST. You can still ask the AI, or choose Talk to agent to join the queue.

Handoff still queues in Inbox for the next agent shift.

---

## 4. Confirmation copy

Defaults in `config/support.php` (env overrides):

- `SUPPORT_HANDOFF_MESSAGE` — first handoff  
- `SUPPORT_HANDOFF_ALREADY_QUEUED_MESSAGE` — user asks again while already queued  

Default first message: *Humara agent jaldi aapse connect karega. Aap Inbox queue mein ho.*

---

## 5. Agent checklist

1. Open **Inbox** → support WhatsApp line  
2. Filter **Unassigned**  
3. Assign to yourself and reply (service window: customer must have messaged within 24h)  
4. Keep chat on **human** response type until resolved  

---

## 6. Launch test script

1. Message support number → menu appears  
2. Category FAQ reply works  
3. Ask AI → KB answer  
4. Talk to agent → confirmation SMS/WhatsApp text; Inbox shows human + unassigned; further user messages do **not** get AI  
5. Agent reply from Inbox delivers  

---

## 7. Code map

| Piece | Path |
|-------|------|
| Handoff orchestration | `app/Domains/Support/Services/SupportHandoffService.php` |
| Inbox channel | `app/Domains/Support/Services/InboxEscalationChannel.php` |
| Swap later (Tittu) | `app/Domains/Support/Contracts/SupportEscalationChannel.php` |
| Chatbot function | `app/Domains/Support/Chatbot/HandoffToInboxHandler.php` |
| Binding | `AppServiceProvider` → `SupportEscalationChannel` → `InboxEscalationChannel` |
| Tests | `tests/Feature/Support/SupportHandoffTest.php` |
