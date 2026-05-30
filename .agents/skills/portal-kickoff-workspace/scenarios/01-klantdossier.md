# Scenario brief 01, Klantdossier-portal

Ground-truth answers a real stakeholder would give. Use these to simulate
realistic interview responses. Reveal only what the skill asks; do not dump the
brief.

## Portal identity & goal
- **Name:** Klantdossier
- **Slug:** klantdossier
- **One-sentence goal (north star):** Klanten zien hun dossier en uploaden documenten; account managers reviewen en keuren uploads goed via de inbox.
- **Primary outcome:** vertrouwd, AVG-conform digitaal dossier waar uploads betrouwbaar worden gereviewed.

## Roles
- **customer**, eindklant (extern). Ziet alleen het eigen dossier.
- **account_manager**, interne medewerker, toegewezen aan een of meerdere klanten.
- **admin**, interne admin (governance, geen dagelijks gebruik).

## Capabilities (capability-first)
1. **Customer · view own dossier(s)**, alleen-lezen overzicht: documenten + status (pending_review / approved / rejected).
2. **Customer · upload document into own dossier**, pdf/jpg/png, max 25MB.
3. **Account manager · review uploads in inbox**, keurt elk geuploade document goed of af, met reden bij afwijzen.
4. **Account manager · view dossiers of assigned customers**, alleen toegewezen klanten.
5. **Account manager · see audit timeline per dossier**, wie uploadde, wie keurde, wanneer.

## Per capability: data, sources, approvals, surfaces
- **External data sources:** **geen** voor v1 (geen connectors). Externe sync (Moneybird o.i.d.) komt mogelijk later, niet in scope.
- **Approval moment:** elke document-upload moet AM-approval krijgen voordat de status "approved" wordt en de klant 'm officieel ziet.
- **Surfaces:**
  - Klant-dossieroverzicht: starter-page (`/dossier`), page-level, niet als widget.
  - Document-upload: in dezelfde dossier-page, modale upload-flow.
  - AM-review: inbox-flow (review = inbox item).
  - AM-overzicht eigen klanten: starter-page (`/klanten`).
- **Audit/reporting:** wie uploadde, wie keurde goed/af, wanneer + afwijsreden. Per dossier zichtbaar.
- **Reuse potential:** **package-ready**, herbruikbaar concept (klant + dossier + reviewed document-upload), maar tenant/membership-mapping is host-specifiek.

## Domain model
- **Entities:**
  - `Customer`, id, tenant_id, name, email, assigned_account_manager_id.
  - `Dossier`, id, customer_id, opened_at, archived_at (nullable).
  - `Document`, id, dossier_id, uploader_id, filename, mime, size_bytes, state, reviewed_by_id (nullable), reviewed_at (nullable), rejection_reason (nullable).
- **Links:** Customer 1, n Dossier; Dossier 1, n Document; Customer n, 1 AccountManager (user).
- **Lifecycle/state (Document):** `pending_review` → `approved` | `rejected`. Geen andere overgangen.
- **Events:** `DocumentUploaded`, `DocumentApproved`, `DocumentRejected`.

## Connectors
- **Geen** voor v1.

## Policies (deny-by-default)
- `dossier.view`, customer ziet alleen eigen dossier; AM ziet eigen toegewezen klanten; admin alles.
- `document.upload`, alleen de customer van het dossier.
- `document.view`, customer eigen documenten; AM toegewezen-klant-documenten.
- `document.review`, alleen AM van de toegewezen klant, en admin.
- `inbox.review_document_uploads`, alleen AM/admin.
- Visibility van het Klanten-nav-item: alleen AM/admin (verbergen voor customer).

## Workflows / inbox
- **Trigger:** `DocumentUploaded` event.
- **Assignee:** account_manager van de customer van de dossier.
- **Approve:** zet `Document.state = approved`, emit `DocumentApproved`, update projectie, notificatie naar customer.
- **Reject:** vereist `rejection_reason`, zet `state = rejected`, emit `DocumentRejected`, notificatie naar customer.
- **Audit event:** in beide gevallen een audit-record met reviewer + tijd + reden (indien afgewezen).
- **Follow-up:** customer-notificatie (email + in-app), projectie-update voor dossier-summary.

## Tenant identity (host-owned)
- **Multi-tenant:** ja, maar één klant per tenant (klanten zijn tenants in de host).
- **Branding:** licht (logo + accentkleur per tenant), niet in scope voor v1, alleen voorbereiden (config-hook).
- **Data-isolatie:** strikt, geen kruislekken tussen tenants. Host bepaalt tenant_id via huidige sessie.
- **Super-admin:** alleen voor support, niet in dagelijks gebruik.
- **Host levert:** users, tenant-memberships, role-mapping (customer/account_manager/admin), invitations, tenant_select-controller, post_login_redirect.

## Observability
- Audit-timeline per dossier (uploads + reviews).
- Inbox-doorlooptijd per AM (hoeveel pending, hoe lang).
- Policy-denials geteld (Policy Studio).
- Geen connector-health (geen connectors in v1).

## Packaging-besluit (per §2)
- **`mortelos/customer-dossier`** als `package-ready`: herbruikbaar concept, maar tenant/membership-mapping host-specifiek. Reden: "Reusable dossier + reviewed document-upload shell with host-specific tenant policy and branding."

## Non-functioneel
- **Volume:** ~100 klanten, ~50 documenten/maand per klant → ~5k/maand totaal. Geen piekbelasting.
- **Consistentie:** projecties mogen async; dossier-listing moet synchroon consistent zijn binnen request.
- **AVG/retentie:** persoonlijke data; retentie 7 jaar na archivering van het dossier; recht op verwijdering ondersteund (soft-delete + cascade).
- **Trust-level / data-classificatie:** financiële/persoonlijke documenten → medium-high; agent-access alleen via expliciete grant + audit.

## Eerste verticale slice (waar de bouw start)
- **Capability:** Customer-upload + AM-review (capabilities 2 + 3) als één samenhangende slice, dat is het hart van het portal. Bevat Dossier, Document, upload-action, inbox-flow, approval-policy, deny-by-default, audit, en de surfaces (dossier-page + inbox-item-detail).
- **Reden:** zonder review-flow heeft uploaden geen betekenis; samen levert het direct waarde + bewijst alle primitieven (entity, event, projection, policy, inbox, observability).

## Wat NIET in scope is voor v1 (zou je antwoorden als gevraagd)
- Branding-customizer UI (alleen config-hook voorbereiden).
- Externe connectors (Moneybird etc.).
- Cross-tenant rapportages.
- Mobiele app.

## Aannamen die je expliciet bevestigt als de skill ernaar vraagt
- Een dossier hoort bij precies één customer (geen gedeelde dossiers).
- Een document kan niet opnieuw gereviewed worden na approval/rejection (definitief).
- Uploads worden opgeslagen op de standaard Laravel-storage; geen externe doc-store in v1.
