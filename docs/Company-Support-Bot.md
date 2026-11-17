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

## 3. Import the ready Chatbot flow (recommended)

Import file: [`docs/flows/wapapp-company-support-bot.json`](flows/wapapp-company-support-bot.json)

Format is the same as **Export** from the React builder: top-level `{ nodes, edges, viewport }`.

Public downloads (Import popup se bhi):

- Simple demo: [`/examples/chatbot/example-welcome-menu.json`](../public/examples/chatbot/example-welcome-menu.json)
- Full support bot: [`/examples/chatbot/wapapp-company-support-bot.json`](../public/examples/chatbot/wapapp-company-support-bot.json)

1. Deploy / pull latest code (handoff + Import popup).
2. Open chatbot builder → **Import**.
3. Popup mein drag & drop / choose file, ya **Download simple / full example**.
4. **Import to canvas** → nodes dikhne chahiye → **Save Flow**.
5. Full support example: Natural Language node pe Support AiBot select karo.
6. FAQ / copy edit → Save → enable/active.

Included paths: menu (Billing / Technical / Account / Ask AI / Talk to agent) → FAQ follow-up → AI Q&A → confirm → `handoff_to_inbox`.

### Manual build (only if you prefer not to import)

**Triggers:** `hi`, `hello`, `help`, `support`, `menu`

**Main menu:** Billing, Technical, Account, Ask AI, Talk to agent

**Ask AI:** Wait → Natural Language (`knowledge_base`) → helpful? / agent / menu

**Talk to agent:** Confirm Yes → Function Call `handoff_to_inbox` (do not fake “agent connecting” without this function).

Registered in `config/chatbot.php` → `function_registry.handoff_to_inbox`.

---

## 4. Confirmation copy

Defaults in `config/support.php` (env overrides):

- `SUPPORT_HANDOFF_MESSAGE` — first handoff  
- `SUPPORT_HANDOFF_ALREADY_QUEUED_MESSAGE` — user asks again while already queued  

Default first message: *Thank you. An agent will connect with you shortly. You are now in the support queue.*

Agent hours in the example flows: **Monday–Friday, 10:00 AM – 6:00 PM IST**.

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
