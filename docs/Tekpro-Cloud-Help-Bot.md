# Tekpro Cloud universal help bot (second WhatsApp number)

Bank-style WhatsApp assistant on the **Tekpro Cloud** number: **inform** about services, **resolve** queries, **guide** next steps, and **assign an agent** via Inbox handoff.

Same stack as WapApp help bot, on the Tekpro tenant / WhatsApp number.

Files:

| Piece | Path |
|-------|------|
| Chatbot flow | `public/examples/chatbot/tekpro-help-bot.json` (also `docs/flows/`) |
| System prompt | `public/examples/kb/tekpro-help-bot-system-prompt.txt` |
| Knowledge Base PDF | `public/examples/kb/tekpro-help-guide.pdf` |
| Knowledge Base markdown | `public/examples/kb/tekpro-help-guide.md` |

## Experience

1. Welcome greets with `{{first_name}}` and positions Tekpro Cloud.
2. Menu **Services and help**:
   - Explore services (overview, then Ask / Agent / Menu)
   - Cloud consulting / migration / managed support / workspace products (info card → question → AI)
   - Ask a question (free-form → AI)
   - Chat with agent (`handoff_to_inbox`)
   - Book a call (Calendly only on this path)
3. AI answers are informative and support-oriented. **15 to 30 minutes** only on agent handoff.

## Setup on the other number

1. Open the WapApp account that owns the **Tekpro Cloud** WhatsApp number (not the WapApp product support number).
2. **AI Assistant → Bots** → create bot e.g. `Tekpro Cloud Help`.
3. Paste system prompt from `tekpro-help-bot-system-prompt.txt` → Save.
4. **Knowledge Base** → remove old PDFs → upload `tekpro-help-guide.pdf` → wait until indexed.
5. Prefer AI Auto response **Off** on this tenant (flow NL nodes still answer).
6. **Chatbot** → create / open a flow for this number → **Import** `tekpro-help-bot.json` → Save → set Active / bind to the Tekpro WhatsApp number.
7. On the Natural Language node, select the `Tekpro Cloud Help` AI bot.
8. Inbox: agents watch this number + **Unassigned** for handoffs.

Trigger keyword: `help`.
