# Scenario brief 02, KlantFinancien-portal

Ground-truth answers a stakeholder would give. Reveal only what the skill asks; do not dump the brief.

## Portal identity & goal
- **Name:** KlantFinancien
- **Slug:** klantfinancien
- **One-sentence goal (north star):** Klanten zien hun openstaande facturen en saldo; finance kan vers omzetdata uit Moneybird ophalen en eenvoudige analyses zien.
- **Primary outcome:** klant heeft een eerlijk realtime financieel beeld, finance heeft betrouwbare omzetinzichten zonder spreadsheets.

## Roles
- **customer**, eindklant, ziet alleen eigen financiele data.
- **finance_user**, interne medewerker met financiele rechten.
- **admin**, governance.

## Capabilities (capability-first)
1. **Customer, view eigen lopende status**, openstaand saldo, openstaande facturen, betaalde facturen laatste 12 maanden.
2. **Customer, download invoice PDF**.
3. **Finance, trigger Moneybird sync**, haalt verse data op-aanvraag.
4. **Finance, view omzet-analyses**, per klant en aggregaat, simpele grafieken.
5. **Admin, see connector health**, last sync, last failure, reauth-status.

## Per capability: data, sources, approvals, surfaces
- **External data sources:** **Moneybird** is de enige bron voor facturen, contacten en grootboek-omzet. Eigen data komt mee via koppeling.
- **Approval moments:** **geen**, deze portal heeft geen review/approval flows. Alles is read-through en sync.
- **Surfaces:**
  - Customer-dashboard, starter-page op `/financien`, met saldo-card, factuurlijst en PDF-downloads.
  - Finance-dashboard, starter-page op `/finance`, met sync-knop, sync-historie en omzet-widgets.
  - Connector-health widget, ingebed in `/finance` en zichtbaar voor admin op `/governance`.
- **Audit/reporting:** wie syncte wanneer, sync-historie tabel; PDF-download logs voor klanten.
- **Reuse potential:** de connector (Moneybird-koppeling) is **package-now**, omdat hij herbruikbaar is across MortelOS-installaties. De portal-shell is workspace-only.

## Domain model
- **Entities:**
  - `Customer`, id, tenant_id, name, email, moneybird_contact_id.
  - `Invoice`, id, customer_id, moneybird_invoice_id, invoice_number, issued_at, due_at, state (open|paid|overdue|credited), amount_cents, currency, pdf_url.
  - `SyncRun`, id, tenant_id, triggered_by_user_id, started_at, finished_at (nullable), status (running|success|failed), error (nullable), invoices_synced (int), contacts_synced (int).
  - `RevenueProjection`, id, tenant_id, customer_id (nullable, aggregate row when null), month (YYYY-MM), revenue_cents, invoice_count.
- **Links:** Customer 1-n Invoice; SyncRun n-1 User (triggered_by); RevenueProjection n-1 Customer (or aggregate).
- **Lifecycle/state (Invoice):** `open` -> `paid` of `overdue` of `credited`, gespiegeld van Moneybird-status.
- **Events:** `MoneybirdSyncStarted`, `MoneybirdSyncCompleted`, `MoneybirdSyncFailed`, `InvoiceUpserted`.

## Connectors (de hoofdmoot)
- **System:** Moneybird, **provider id** `moneybird`.
- **Auth:** OAuth 2.0 (authorization-code flow). Refresh-token bewaard per tenant. Tokens zijn sensitive, niet in logs.
- **Setup:** OAuth flow met scope `sales_invoices:read`, `contacts:read`. Tenant-admin doet de eerste setup vanuit `/finance` of `/governance`.
- **Direction & frequency:** read-only naar host. Triggers: on-demand (finance klikt sync), plus scheduled hourly. Initieel een full backfill, daarna incrementeel via `updated_after`.
- **Failure/reauth:** retry met exponential backoff (max 3) bij 5xx; bij 401/403 markeer connector als `needs_reauth` en post een proposal naar Policy Studio plus notify admin via inbox.
- **Data classification:** medium-high, financiele en persoonlijke data, AVG-relevant.

## Surfaces per capability (uit §6)
- `/financien` voor customer (starter-page).
- `/finance` voor finance_user (starter-page met sync-knop, sync-historie, omzet-analyses widget).
- Connector-health als reusable widget, ingebed in `/finance` en in `/governance`.

## Policy matrix (deny-by-default)
- `invoice.view`, customer eigen invoices; finance en admin alle invoices binnen tenant.
- `invoice.download`, idem.
- `connector.moneybird.setup`, alleen admin.
- `connector.moneybird.trigger_sync`, finance en admin.
- `connector.moneybird.view_health`, finance en admin.
- `revenue.view_analytics`, finance en admin.
- `nav.finance.view`, finance en admin.

## Workflows / inbox
- **Geen approval-flows** voor customer/finance acties. Wel: bij `MoneybirdSyncFailed` met `needs_reauth` ontstaat een inbox-item voor admin, "Moneybird reauth nodig", met actie "Reauth starten".

## Tenant identity (host-owned)
- **Multi-tenant:** ja, een klant per tenant.
- **Branding:** geen extra customization in v1.
- **Data-isolatie:** strikt; Moneybird-tokens en sync-data per tenant.
- **Super-admin:** kan over tenants kijken voor support.
- **Host levert:** users, tenant-memberships, role-mapping (customer/finance_user/admin), tenant-selectie, post-login redirect.

## Observability
- **Connector health,** last_sync_at, last_failure_at, status (healthy|needs_reauth|degraded).
- **Sync history,** per SyncRun zichtbaar in `/finance`.
- **Policy-denials,** Policy Studio.
- **PDF-download audit log** voor customer-acties.

## Packaging-besluit (per §2)
- **`mortelos/connector-moneybird`** als **package-now**, want de connector is direct herbruikbaar across MortelOS-installaties. Reden: "Reusable Moneybird OAuth + sync + health connector with deny-by-default policies, ready for any MortelOS tenant."
- **portal-shell KlantFinancien** als **workspace-only**, klantspecifieke shell die de connector consumeert.

## Non-functioneel
- **Volume,** ~200 klanten per tenant, ~50 facturen/klant/jaar, Moneybird sync incrementeel dus laag verkeer.
- **Consistentie,** projecties async, dashboard mag eventually consistent zijn, sync-status synchroon zichtbaar.
- **AVG/retentie,** financiele data 7 jaar; recht-op-verwijdering via soft-delete plus markering in audit.
- **Trust-level,** medium-high; OAuth-tokens encrypted at rest.

## Eerste verticale slice (waar de bouw start)
- **Capability:** Capability 3 (Finance trigger Moneybird sync) PLUS capability 1 (Customer view eigen openstaande facturen) als één samenhangende slice.
- **Reden:** de sync moet werken voor de customer-view zinvol is. Samen valideert dit de connector-boundary (auth, sync, health), de policy-laag, en de read-surface.

## Niet in scope voor v1
- E-mailnotificaties bij overdue invoices (alleen status in dashboard).
- Betaal-integratie (alleen view + PDF-download).
- Andere financiele systemen dan Moneybird.

## Aannames die je expliciet bevestigt als de skill ernaar vraagt
- Moneybird is single source of truth voor facturen; host slaat alleen read-models op (geen edits terug naar Moneybird in v1).
- OAuth-tokens worden opgeslagen via Laravel's encrypted casts.
- Bij sync-conflicten (zelfde Moneybird-id, andere data) wint Moneybird altijd.
