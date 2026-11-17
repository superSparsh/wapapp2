# WapApp Support Knowledge Base

Use this document as the primary Knowledge Base source for the WapApp company support AI bot.

**Support hours:** Monday to Friday, 10:00 AM – 6:00 PM IST  
**Channel:** WhatsApp support chatbot → human agents via Inbox when needed  

If this document does not clearly answer a question about billing disputes, refunds, account ownership transfer, or legal/compliance matters, the assistant must say it is unsure and suggest talking to a human agent.

---

## 1. What is WapApp?

WapApp is a WhatsApp Business messaging platform. Customers use it to connect official WhatsApp Business numbers, send template and service messages, run campaigns, automate replies with chatbots, manage Inbox conversations, build audiences, and integrate tools such as calendars and commerce features.

WapApp helps businesses:

- Connect and manage WhatsApp Business phone numbers
- Create and submit WhatsApp message templates
- Send campaigns to audience lists
- Reply to customers in Inbox
- Build chatbot and drip automations
- Track wallet usage and billing

---

## 2. Getting started

### How do I start using WapApp?

1. Log in to your WapApp account.
2. Complete business / WhatsApp onboarding if prompted.
3. Connect a WhatsApp Business phone number under Integration / phone number settings.
4. Create or sync message templates.
5. Add contacts to Audience (or import a list).
6. Send a test message or campaign, and monitor replies in Inbox.

### I just signed up. What should I do first?

1. Connect your WhatsApp Number under Integration.
2. Confirm template approvals in Templates.
3. Add a small test audience.
4. Send a test template from Inbox or Campaigns.
5. Open Inbox and confirm delivery / replies work.

---

## 3. WhatsApp connection and phone numbers

### How do I connect a WhatsApp number?

Use the Integration / Onboarding flow in WapApp to connect your WhatsApp Business Account (WABA) and phone number. Follow the on-screen Meta / embedded signup steps carefully. Do not close the browser until the connection finishes.

### My WhatsApp Number shows disconnected. What should I do?

1. Open Integration / phone number settings.
2. Reconnect the WhatsApp Number using the reconnect option.
3. Refresh the page and confirm the WhatsApp Number status is connected.
4. Send a test message to yourself.

If reconnect fails after multiple attempts, talk to an agent and share the exact error message shown on screen.

### QR code or embedded signup is not working

1. Refresh the Integration / Onboarding page.
2. Try another browser (Chrome recommended) or an Incognito window.
3. Disable popup blockers for WapApp and Meta login.
4. Confirm you have admin access to the Meta Business / WhatsApp Business Account.
5. Retry signup.

If it still fails, talk to an agent with a screenshot of the error.

### Can one account use multiple WhatsApp numbers?

Yes, depending on your plan and account setup. Numbers are managed under WhatsApp Number settings. Some chatbots or automations can be linked to a specific WhatsApp Number.

---

## 4. Messaging windows, delivery, and quality

### Why did my message not deliver?

Common reasons, explained simply:

- Your **WhatsApp Number** is disconnected or restricted
- You tried to send a normal free-form reply outside the **24-hour customer chat window** (use an approved template instead)
- The contact blocked your business or opted out with **STOP**
- Messaging quality is low, or you hit a messaging limit
- The phone number format is invalid
- Wallet balance is too low for billable sends

What to check first:
1. Integration → confirm the WhatsApp Number status is Connected
2. Inbox → open the chat and check delivery / error details
3. Wallet balance
4. Whether the customer messaged you in the last 24 hours

### What is the 24-hour messaging window?

When a customer messages your business, WhatsApp opens a customer service window (usually 24 hours). During that window you can send normal replies (text, media, buttons). Outside that window, business-initiated messages usually need an approved template.

### What are messaging limits?

Messaging limits control how many business-initiated conversations a WhatsApp Number can start in a rolling 24-hour period. New numbers often start lower and can grow with good quality and steady usage. If you hit a limit, wait for the window to refresh or improve quality before sending more volume.

### What is messaging quality?

Messaging quality is based on how people react to your messages (for example blocks, reports, and negative feedback). Low quality can reduce limits or cause delivery issues.

To protect quality:
- Send useful, opted-in messages
- Avoid spammy frequency
- Use the correct template category
- Honor STOP / unsubscribe requests immediately

### STOP and opt-out

If a user sends STOP (or a similar opt-out), stop marketing and automated outreach to that number according to platform rules. Do not keep messaging opted-out contacts.

### QR code during WhatsApp Number setup — what is it?

The QR code (or Meta signup screen) is part of connecting your **WhatsApp Business Number** to WapApp. It is not a customer-facing chatbot feature. You scan or complete it during Integration / Onboarding so WapApp can send and receive messages on that number.

If QR / signup fails:
1. Refresh the Integration page
2. Try Chrome or Incognito
3. Allow pop-ups for Meta login
4. Confirm you have admin access on the Meta Business / WhatsApp Business Account
5. Retry once

If it still fails, Talk to an agent with a screenshot of the exact error.

## 5. Templates

### What are WhatsApp message templates?

Templates are pre-approved message formats used for business-initiated conversations (marketing, utility, authentication, and related categories). They must be submitted and approved before use.

### How do I create a template?

1. Open Templates in WapApp.
2. Create a new template and choose the correct category.
3. Add header (optional), body, footer (optional), and buttons (optional).
4. Add sample values for variables.
5. Submit for Meta review.

### My template is Pending. What does that mean?

Pending means Meta is still reviewing the template. This can take time. Do not keep resubmitting duplicates. Wait for Approved or Rejected status.

### My template was Rejected. What should I do?

Common causes:

- Wrong category (for example marketing content marked as utility)
- Vague or placeholder-only body text
- Policy violations (misleading claims, prohibited content)
- Missing sample variables

Fix the content and category, then create a corrected template. For category disputes or unclear rejections, talk to an agent.

### Marketing vs Utility vs Authentication templates

- **Marketing:** Promotions, offers, announcements, retargeting
- **Utility:** Order updates, account alerts, reminders tied to an existing transaction or account action
- **Authentication:** One-time passwords and verification codes

Choosing the wrong category is a frequent rejection reason.

### Template variables

Use variables such as `{{1}}`, `{{2}}` for dynamic values (name, order id, date). Always provide sample values during creation. When sending, map each variable to real data from your audience fields or campaign settings.

---

## 6. Inbox and live conversations

### How do I reply to customers?

Open Inbox, select the WhatsApp Number, open the conversation, and reply. If the 24-hour window is open, you can send free-form messages. If not, send an approved template.

### Where do chatbot “Talk to agent” chats go?

When the support bot hands off to a human agent, the conversation is set to human mode and left unassigned in Inbox. Agents should open Inbox, filter **Unassigned** on the support WhatsApp Number, assign the chat, and reply.

### AI keeps answering after I took over the chat

For support operations, turn **AI Auto response** Off on the support tenant. After handoff, the conversation should stay in human mode so agents own the reply.

### Can I assign chats to team members?

Yes. Use Inbox assignment to assign conversations to users or team members who have access.

---

## 7. Chatbot and automation

### How does the chatbot work?

Chatbots are visual flows with nodes (welcome, interactive buttons/lists, wait for reply, conditions, AI / natural language, function calls, and more). Customers trigger a flow with keywords such as `hi`, `hello`, or `menu`, then follow menu options.

### My chatbot is not replying

Check:

1. The flow is saved and active / enabled
2. Trigger keywords match what the user typed
3. The flow is linked to the correct WhatsApp Number (if WhatsApp Number binding is used)
4. Wallet balance is sufficient for outbound messages
5. The conversation is not stuck in human-only mode when AI/chatbot should reply
6. Another active flow is not conflicting on the same keyword

### Interactive buttons and lists

- Buttons: up to 3 quick-reply style options
- Lists: more options via list rows
Button titles have length limits. Keep labels short and clear.

### What is `handoff_to_inbox`?

It is a chatbot Function Call used by the company support bot. It switches the chat to human response mode, ends chatbot ownership of the turn, leaves the chat unassigned in Inbox, and sends a confirmation that an agent will connect shortly.

### Drip / trigger automations

Drip and trigger flows can send templates, wait, apply conditions, and continue sequences based on customer actions or schedules. Use them for follow-ups, reminders, and lifecycle messaging — not for spam.

---

## 8. Campaigns and audience

### How do I send a campaign?

1. Create or select an audience list
2. Choose an approved template
3. Map template variables
4. Schedule or send
5. Track stats and failed sends

### Campaign failed or partially failed

Check wallet balance, template approval status, phone number formatting, messaging limits, and STOP / blacklist status. Use campaign stats / resend tools for failed recipients when available.

### How do I import contacts?

Use Audience import with a valid file (typically CSV). Include required phone columns in the correct format with country code. Remove duplicates and invalid numbers before large imports.

### What lists, tags, and segments are for

- **Lists:** Groups of contacts for campaigns and messaging
- **Tags / fields:** Extra attributes for personalization and filtering
- **Segments:** Filtered subsets based on rules or attributes

---

## 9. Billing, wallet, and plans

### Where do I manage billing?

Open Billing / Account billing in the WapApp dashboard to view plans, invoices, and payment status.

### How do wallet credits work?

Sending billable WhatsApp conversations / messages consumes wallet balance according to your pricing setup. If the wallet is too low, campaigns or chatbot outbound messages may be blocked until you recharge.

### Payment failed

1. Wait 10–15 minutes and retry
2. Confirm card/UPI/bank details with your payment provider
3. Check whether the plan or wallet was partially updated
4. If payment was deducted but plan/credits did not update, talk to an agent with the payment reference

### Invoices

Invoices are typically emailed to the account email on file. Check spam/junk. You can also review billing history in the dashboard when available.

### Refunds and charge disputes

The AI assistant must not promise refunds. Ask the customer to talk to a human agent and keep payment reference details ready.

### Plan upgrade or downgrade

Plan changes are handled from Billing in the dashboard. If the change does not reflect after a successful payment, talk to an agent.

---

## 10. Team access and account security

### How do I add a team member?

Open Team settings, invite the member by email, and assign the correct role / permissions.

### Team member cannot see Inbox or a WhatsApp Number

They may lack permission or WhatsApp Number access. An account admin should update roles and assigned WhatsApp Numbers.

### Login OTP not received

1. Check spam/junk
2. Confirm the login email is correct
3. Wait a minute and request OTP again
4. Try another browser / network

If still blocked, talk to an agent (do not share OTP codes in chat publicly).

### Account ownership transfer

Ownership or legal account transfer requires a human agent. The AI must not process ownership changes itself.

---

## 11. AI Assistant and Knowledge Base

### What does the AI Assistant do?

It answers questions using your configured bot prompt and Knowledge Base (uploaded docs / scraped sources). In WhatsApp, it can run inside chatbot Natural Language nodes or inbound AI auto-reply when enabled.

### Best practices for accurate AI answers

- Keep the Knowledge Base updated
- Use clear headings and FAQ style
- Tell the bot not to invent policies
- Turn global AI auto-reply Off when humans take over support chats

### The AI gave a wrong answer

Tell the customer you will verify with an agent if the topic is billing, legal, or account-sensitive. Update the Knowledge Base so future answers improve.

---

## 12. Integrations and advanced features

### Calendly / Google Calendar

Connected calendar apps can help with booking-related messaging workflows. Connect them from Connected Apps / Integrations and follow the on-screen authorization steps.

### WhatsApp Flows

WhatsApp Flows are structured forms inside WhatsApp (multi-screen inputs). Create and publish flows from the WhatsApp Flows area, then use them in interactive / flow messages where supported.

### Webhooks

Webhooks notify your systems about events (for example inbound messages or status updates). Configure endpoint URL and security settings carefully, then test delivery.

### API access

API credentials and developer settings are available in account / API sections when enabled on the plan. Do not share API keys in WhatsApp chats. If keys are exposed, rotate them and talk to an agent if needed.

---

## 13. Common troubleshooting checklist

Before escalating to an agent, ask the customer to confirm:

1. WhatsApp Number status = connected
2. Template status = approved (for outbound templates)
3. Wallet has sufficient balance
4. Phone numbers include country code
5. User is inside the 24-hour window for free-form replies
6. Contact has not opted out / STOP
7. Browser refresh done for onboarding / QR issues
8. Exact error message or screenshot is available

---

## 14. When to talk to a human agent

Always suggest a human agent for:

- Refunds, chargebacks, or payment deducted but not credited
- Account ownership / transfer requests
- Legal, privacy, or compliance disputes
- Meta business verification blockers that cannot be resolved with reconnect steps
- Suspected unauthorized access
- Repeated failures after the troubleshooting checklist

Support hours: Monday to Friday, 10:00 AM – 6:00 PM IST. Outside hours, customers can still use the menu / AI, or join the agent queue for the next business day.

---

## 15. Suggested AI behavior rules (for bot system prompt)

- Answer only from this Knowledge Base and confirmed business information
- Use short WhatsApp-friendly paragraphs and bullet points
- Speak in clear professional English
- If unsure, say: “I am not fully sure about that. Would you like to talk to a human agent?”
- Never invent pricing, refund promises, legal advice, or account changes
- Never ask for passwords, OTPs, or full card numbers
