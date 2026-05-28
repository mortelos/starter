# Interview Engine

The quality of the whole portal depends on this phase. The goal is a capability
map with **no gaps**, every decision the build plan needs is either answered or
recorded as a confirmed assumption.

Style: **deep, one question at a time.** Conversational, never a wall of
questions. But the depth is governed by a coverage map so it stays complete. You
hold the coverage map in your head (or a scratch list), ask one sharp question
for the most relevant open dimension, and listen, most answers open new
sub-questions.

## Contents

- [How to run it](#how-to-run-it)
- [The coverage map](#the-coverage-map), the 13 dimensions and their questions
- [Protocol rules](#protocol-rules), sequencing, branching, assumptions, scope, stop
- [Worked fragment](#worked-fragment), what good questioning sounds like

## How to run it

1. **One question per message.** Prefer a concrete question with a recommended
   default over an open prompt, but open is fine when the space is genuinely open.
   Offer 2–3 options when there is a natural choice; always allow "something else".
2. **Sequence by relevance, not by table order.** Start with identity + goal, then
   follow the user's energy. The numbered map below is a checklist of what must be
   covered, not a script to read top to bottom.
3. **Listen for branches.** An answer like "we pull invoices from Moneybird" opens
   the whole Connector dimension (auth, sync direction, frequency, failure). A
   "managers approve it first" opens the Workflow/Inbox dimension. Chase those
   before moving on.
4. **Reflect back.** Every few answers, summarize what you now understand in one or
   two sentences and let the user correct it. This catches misunderstandings cheaply.
5. **Track open dimensions.** Keep a running note of which of the 13 are still open.
   You are done with the interview only when each is answered or a confirmed
   assumption.
6. **Write the map.** When the coverage is complete, fill
   `references/capability-map.md` into `docs/portals/<slug>/capability-map.md` and
   ask the user to confirm it before any wiring.

## The coverage map

Each dimension lists its intent, sample questions (ask one at a time), the branches
an answer can open, and the bar for "covered".

### 1. Portal identity + goal, ask first

- Intent: a name → `<slug>` (kebab-case, used in every path) and the **goal** that
  becomes the north star for the build loop.
- Questions: "What is this portal called, and in one sentence, what is it for?" ·
  "When it is working, what is the single most important thing it lets people do?"
- Covered when: you have a slug and a one-sentence goal you can put at the top of
  the plan.

### 2. Actors & roles, capability-first

- Intent: who uses the portal and what each role can do. Drive from capabilities,
  never from pages.
- Questions: "Who are the kinds of users, customers, account managers, finance,
  operators, admins?" · "For <role>, what should they be able to *do*?" (repeat per
  role) · "Is there a super-admin / internal view that differs from the customer
  view?"
- Branches: each capability becomes a row that pulls dimensions 3–8.
- Covered when: every role has an explicit list of capabilities.

### 3. Per capability, data, sources, approvals, surfaces, audit, reuse (§1)

Ask this cluster *per capability*, but conversationally, not as a form:

- Read data: "What does the user see here, and where does that data come from?"
- Write data: "What can they change or create? What is the effect?"
- External sources: "Does this touch any external system (CRM, finance, storage,
  email, ticketing, transcription, a customer API)?" → if yes, branch to §5.
- Approval moments: "Does anything here need human review or approval before it
  takes effect?" → if yes, branch to §8.
- Surfaces: "Where does this live, a full page, a dashboard widget, something in
  chat, a module inside another page?" → §6.
- Audit/reporting: "Does anyone need to see a history or report of this later?"
- Reuse: "Would other MortelOS customers want this same capability?" → §11.
- Covered when: each capability has read/write, sources, approvals, surface, and a
  reuse hint.

### 4. Domain model (§3)

- Intent: map customer concepts to entities, links, lifecycle/state, and events.
- Questions: "What are the core *things* here, customer, project, document,
  dossier, invoice?" · "How do they relate, does a customer own dossiers, does a
  document belong to a project?" · "Does <thing> move through states (draft →
  submitted → approved)?" · "Which moments matter as events (uploaded, synced,
  approved)?"
- Note: domain rules belong behind actions/projections/policies, not in Blade.
- Covered when: you can name the entities, their key links, and any stateful
  lifecycle.

### 5. Connectors (§5), branch, only if external systems exist

- Intent: the integration boundary for each external system.
- Questions: "Which system, and what do we read from / write to it?" · "How does it
  authenticate, OAuth, API key, something else?" · "Which direction does data flow,
  and how often (on demand, scheduled, on event)?" · "What happens when it fails or
  the auth expires, who gets told, does it retry?" · "Is any of this data sensitive
  / classified?"
- Covered when: per external system you have provider, auth model, sync
  direction/frequency, and failure/reauth behavior.

### 6. Surfaces per capability (§6)

- Intent: pick the smallest surface that matches each workflow.
- Map: Starter page (core shell: dashboard/inbox/users/settings) · package route
  (reusable feature workspace) · dashboard widget (dense overview/metric) · chat
  widget (interactive task/proposal/form in chat) · page widget (reusable Livewire
  module embedded in a portal page).
- Questions: "Is this an everyday dense overview (widget) or a focused workspace
  (page/route)?" · "Should it be reachable from chat?"
- Covered when: each capability has a chosen surface type.

### 7. Policy matrix (§7)

- Intent: who can see and do what, deny-by-default.
- Questions: "Who is allowed to see <entity/data>?" · "Who can perform <mutating
  action>?" · "Should navigation items / widgets be hidden for some roles?"
- Rule: every mutating action and sensitive surface needs a policy ability; use
  policies instead of component-level conditionals when the rule affects tenant
  data, role capability, navigation, widgets, or agent tools.
- Covered when: you have a who-can-what matrix and the list of abilities to seed.

### 8. Workflows / inbox (§8), branch, only if approvals exist

- Intent: coordinate actions, approvals, consequences through inbox/proposal flows.
- Questions: "What triggers the review?" · "Who is assigned, which user or role?" ·
  "What does approve do, what does reject do?" · "What audit event and follow-up
  (notification, projection update) results?"
- Covered when: each reviewed action has trigger → assignee → approve/reject →
  audit → follow-up.

### 9. Tenant identity (§9), host-owned

- Intent: be explicit about tenancy, but remember the host owns the model.
- Questions: "Is this single-customer or multi-tenant?" · "Do tenants need their own
  branding?" · "How strict is data isolation between tenants?" · "Is there a
  super-admin that crosses tenants?"
- Rule: document these as host requirements and seed safe policy defaults; do not
  build a membership/role/invitation model inside the portal.
- Covered when: tenancy model and isolation expectations are documented as host
  requirements.

### 10. Observability (§10)

- Intent: make operational state inspectable.
- Questions: "Six months in, what will an operator need to answer, connector
  health, last sync, why something was hidden, who approved what, why a number
  looks wrong?"
- Covered when: you know which of connector health, projection drift, policy
  denials, agent/widget runs, and audit trail this portal must expose.

### 11. Packaging decision per surface (§2)

- Intent: choose the package boundary so reuse is governed.
- Map: `package-now` (reusable across installs now) · `package-ready` (host wiring
  first, boundary explicit) · `workspace-only` (customer-specific by design).
- Questions: "Earlier you said <capability> could be reused, should it be a real
  package now, or workspace-only for this customer?"
- Covered when: every new surface has a decision + a one-line reason.

### 12. Non-functional

- Intent: constraints that change the design.
- Questions: "Roughly how much data / how many users / how often?" · "Does any read
  need to be exactly consistent within the request (sync projection) or is
  eventual fine (async)?" · "Any GDPR/retention rules, personal data, how long do
  we keep it, right to deletion?" · "What trust level / data classification applies
  to agent access here?"
- Covered when: volume, consistency need, GDPR/retention, and trust level are
  captured (even if just "low volume, standard retention").

### 13. First vertical slice

- Intent: pick where to start building.
- Questions: "Of everything we mapped, which capability proves the portal works and
  unblocks the most other work?"
- Covered when: one capability is chosen as the first slice (usually the most
  foundational entity + its primary surface).

## Protocol rules

- **Scope guard (do this early).** If the request spans several independent
  subsystems ("a portal with chat, billing, file storage, and analytics"), stop and
  decompose before refining details. Name the independent pieces, pick the first to
  kick off, and note the rest as future portals each with their own kickoff. Do not
  spend questions detailing a portal that should be split.
- **Branch, don't drone.** Only open §5 (connectors), §8 (inbox), or the
  multi-tenant parts of §9 when an answer makes them relevant. No dead questions.
- **Assumption ledger.** When the user says "I don't know" or "you decide", record an
  explicit assumption ("Assuming single-tenant, standard 7-year retention") and mark
  it for confirmation in the capability map. Never silently invent requirements.
- **Reuse radar.** Proactively flag capabilities that look reusable across MortelOS
  installations and suggest `package-now`, this is cheaper to decide now than to
  refactor later.
- **Stop / self-check.** Before writing the plan in phase [4], re-scan the 13
  dimensions and ask only the still-open ones. The bar to stop: you could write the
  build plan with no `TBD`. Resist over-interviewing once that bar is met.

## Worked fragment

> **You:** What's this portal called, and in one sentence, what is it for?
> **User:** "Klantdossier", customers see their dossiers and upload documents.
> **You:** Got it (slug `klantdossier`). When it's working, what's the single most
> important thing it lets people do? *(captures the goal / north star)*
> **User:** Account managers can review and approve uploaded documents fast.
> **You:** So there are at least two roles, customer and account manager. For the
> customer, beyond viewing dossiers and uploading, can they do anything else?
> **User:** No, just view and upload.
> **You:** And when a customer uploads, does the account manager have to approve it
> before it counts? *(branches to §8, inbox)*
> **User:** Yes, they review each upload.
> **You:** Then we'll route uploads through the inbox. Who's assigned, a specific
> account manager per customer, or any of them? ...

Notice: each question is single, concrete, and follows the previous answer. The
coverage map runs underneath, but the user only feels a conversation.
