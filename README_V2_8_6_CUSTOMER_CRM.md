# ISO Admin Command Framework v2.8.6

## Customer CRM Update

This release turns the Customers area into a full CRM. It replaces the earlier orphaned "Clients" screens (which were never wired up to a database table or route) by extending the live Customers module instead, so no existing customer data or permissions are lost.

### Customers

- New fields: customer type (customer, prospect, supplier, partner, other), industry, website, account manager (assigned from active users).
- Existing fields kept: company name, customer code, contact person, email, phone, address, status, notes.

### Sites / locations

- Each customer can have any number of sites/locations.
- Captured fields: name, site code, status, location/address, notes.

### Contacts

- Contacts can be captured at the customer (head office) level or scoped to a specific site.
- Contact types: Engineer, Foreman, Stores, Accounts, Buyer, Storeman, Maintenance Manager, Production Manager, Safety Officer, Site Manager, Procurement, Operations Manager, Plant Manager, Other.
- Captured fields: name, position, contact type, email, phone, mobile, primary flag, status, notes.

### Interactions & follow-ups

- New activity log per customer: calls, emails, meetings, site visits, tasks/follow-ups and notes.
- Each entry can optionally link to a site and/or a contact, and can carry a follow-up date. Overdue follow-ups are highlighted on the customer profile.

### Permissions

- `clients.view` / `clients.manage` — unchanged, still control the base customer records.
- `customer_sites.manage` — add, edit and delete sites.
- `customer_contacts.manage` — add, edit and delete contacts.
- `customer_interactions.manage` — log, edit and delete interactions and follow-ups.
- System Administrator, Director and Manager roles are synced with the new permissions automatically. Review the permission matrix under Roles after applying this update if you use custom roles.

### Out of scope

No financial data (quotes, invoicing, deal values) is part of this module — that remains on the separate finance platform.

### Cleanup

The unused `ClientController` and `resources/views/clients` screens (never reachable — no route ever pointed at them) have been removed.

### Install

Upload the package and run:

`/updates/v2-8-6`
