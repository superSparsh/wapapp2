# Support Ticket System

The app now lives in its own project: `/Users/sparshthakur/Documents/tittu-support`.
Build from `docs/ARCHITECTURE.md` in that folder. This file is the earlier scope note.

Document type: Product and architecture scope
Status: Moved to tittu-support
Date: 21 October 2026

This document covers only what we decided to build. Features that were mentioned later as optional ideas are not part of this version.


## 1. What we are building

One central support website for all of our platforms.

Each product does not get its own ticket tool. Customers and teams raise tickets from those platforms, and every ticket is handled in this one place: filtered, assigned, and replied to from a single support email.


## 2. Why

Today support is split across platforms. Replies, ownership, and tracking are not in one system, so tickets get missed, duplicated, or passed around without a clear owner.

This hub gives one inbox, one reply address, and a clear owner for every ticket.


## 3. What this version includes

1. Tickets from every platform land in one system.
2. One reply email for the whole system. A customer reply stays on the same ticket.
3. Tickets are filtered and routed by reading the subject.
4. A ticket can be raised, assigned to a project, assigned to a team member, and replied to.
5. The same system is connected inside our platforms, so a ticket raised there is handled here.


## 4. How a ticket moves

```
Platform app / website form / inbound email
                |
                v
        Support Ticket Hub
                |
                +-- read subject and platform
                +-- route to a project
                +-- assign a team member (or leave unassigned)
                |
                v
        Agent replies from the single support email
                |
                v
        Customer reply comes back on the same ticket
```

Statuses used:

- Open
- Pending
- Resolved
- Closed


## 5. Functionality

### 5.1 Raise a ticket

A ticket can be created in three ways:

| Source | How it enters |
| --- | --- |
| Our platforms | API call with a platform key |
| Website or in-product form | Same API, or a small raise-ticket form embedded in the product |
| Email | Mail sent to the support address creates a ticket, or is added to an existing one |

Every ticket stores:

- Platform (which product it came from)
- Project
- Subject and message
- Customer name and email
- Source (in-app, form, or email)
- External reference (customer or account id from that platform, when we have it)
- Priority, when a routing rule sets it
- Status

### 5.2 One reply email

All agent replies go out from one address: `support@tittu.in`. The hub itself lives at `support.tittu.in`.

The subject of every outbound mail includes the ticket number:

`[TKT-10482] Re: original subject`

When the customer replies, the system attaches that mail to the same ticket. It matches in this order:

1. Ticket number in the subject (`TKT-xxxxx`)
2. Email headers (`In-Reply-To` and `References`), using the message id we stored when we sent the reply
3. If those are missing: same sender, a similar subject, and an open ticket

The customer can reply from Gmail or Outlook in the normal way. That reply must not open a second ticket.

### 5.3 Filter and route from the subject

Before matching, the subject is cleaned:

- Lowercase
- Remove `Re:`, `Fwd:`, and the `[TKT-xxxxx]` tag
- Collapse extra spaces

Routing rules live in the database so they can be changed without a code release. Examples:

| If | Then |
| --- | --- |
| Subject contains billing, invoice, or payment | Project = Billing |
| Subject contains whatsapp, template, or meta | Project = WapApp support |
| Ticket came from a known platform | Project = that platform's project |
| Subject contains urgent, down, or outage | Higher priority |
| Nothing matches | Unassigned queue |

An agent can always change the project, assignee, or priority after the rule runs.

The inbox can also be filtered by:

- Platform
- Project
- Assignee
- Status
- Priority
- Date
- Search on subject

### 5.4 Project, team member, and reply

1. Ticket arrives.
2. A routing rule sets the project and, when a rule says so, a team member.
3. If no rule matches, the ticket sits in the unassigned queue.
4. A lead or agent assigns the project, then assigns a team member.
5. The assignee replies. The reply is stored on the ticket and emailed from the single support address.
6. Status is updated as the ticket moves from open to pending, resolved, or closed.

Projects and team members are managed in this system:

- A project belongs to a platform.
- Team members are linked to the projects they work on.


## 6. Screens

| Screen | What it is for |
| --- | --- |
| Inbox | All tickets, with the filters above |
| Ticket detail | Full thread, reply box, project and member assignment |
| Raise ticket | Create a ticket for a customer, or an internal ticket |
| Projects and teams | Projects, members, and who can take tickets for a project |
| Routing rules | Subject and platform rules that set project, team, and priority |


## 7. Data we store

```
projects
  id, name, platform

team_members
  id, name, email, role

project_members
  project_id, team_member_id

tickets
  id, ticket_number          -- TKT-xxxxx
  platform, project_id
  assigned_to                -- team member
  subject, status, priority
  source, source_ref
  customer_email, customer_name
  created_at, last_reply_at

ticket_messages
  ticket_id
  direction                  -- in or out
  body
  from_email, to_email
  message_id                 -- email header, used to keep the thread
  author_type                -- customer, agent, or system
  created_at

routing_rules
  match_type, pattern
  project_id, team_member_id, priority

ticket_events
  ticket_id, action, actor, created_at
  -- assigned, status change, replied
```

Files that arrive with an email, or are attached to a reply, are stored against that message so the thread stays complete.


## 8. How platforms connect

Each platform gets an API key. It sends:

```
POST /api/v1/tickets
X-Platform-Key: <platform key>

platform
project
customer email and name
account or customer id
subject
message
```

A small raise-ticket form can sit inside each product and call the same API. The ticket is then filtered, assigned, and replied to only in the hub.

Adding another product later means issuing a key and connecting the form. It does not mean building another ticket system.


## 9. Build order

| Phase | We build |
| --- | --- |
| 1 | Tickets, message thread, and the single reply email |
| 2 | Project assign, team member assign, and inbox filters |
| 3 | Subject routing rules and platform API keys |
| 4 | Raise-ticket form inside our platforms |


## 10. Decisions

| Topic | Decision |
| --- | --- |
| Shape | One central hub. Platforms are clients of that hub. |
| Domain | Hub at `support.tittu.in`. Replies from `support@tittu.in`. |
| Email | One support address. Ticket number plus message-id threading. |
| Filtering | Configurable rules on the cleaned subject and the source platform. |
| Ownership | Assign a project, then assign a team member. |
| Inside products | API, plus a raise-ticket form per platform. |
