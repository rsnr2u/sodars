# SODARS Master Product Backlog
## Document-Driven Engineering Backlog

Last updated: 2026-05-28

This backlog was refreshed after reviewing the SODARS documentation workspace: 191 Markdown specification files across root, architecture, backend, database, modules, portals, menus, API docs, integrations, security, storage, search, reports, testing, DevOps, UI/UX, and future planning folders.

The backlog follows the implementation order defined by `development_guide.md`, `README.md`, `system_flows.md`, `portal_menus_and_pages.md`, `database/database_schema.md`, `backend/api_system.md`, and the module-level documents:

1. Foundations: environment, database, auth, RBAC, locations.
2. Provider onboarding and inventory ingestion.
3. Campaigns, booking holds, availability, and provider approvals.
4. Finance, invoices, payments, settlements, and commissions.
5. Marketplace search, maps, storage, notifications, analytics, and reports.
6. Testing, DevOps, observability, security hardening, and future capabilities.

## Priority Legend

- `[Must]`: Required for MVP or data integrity.
- `[Should]`: Required for launch-quality operations.
- `[Could]`: Enhancement or scale capability.
- `[Future]`: Explicitly deferred product expansion.
- `[S1]..[S8]`: Proposed sprint sequencing.
- `[ ]`: Not started.
- `[/]`: In progress.
- `[x]`: Complete or already documented as complete.

---

# 1. Sprint Plan

## Sprint 1: Foundations, Database, Auth, and Locations

- `[x] [Must] [S1]` Create the Laravel 12 API application, module structure, route groups, standardized JSON response envelopes, exception responses, and API versioning.
- `[x] [Must] [S1]` Create frontend application shells for Website, Admin, Business, and Agents portals using the documented stack split: Blade/Tailwind for website and React/Vite/Redux/shadcn for portals.
- `[x] [Must] [S1]` Convert the 49-table `database/database_schema.md` SQL blueprint into Laravel migrations with foreign keys, soft deletes where required, and deterministic migration order.
- `[x] [Must] [S1]` Add base seeders for roles, permissions, users, tax settings, core settings, and initial location taxonomy.
- `[x] [Must] [S1]` Implement Sanctum authentication for users and provider staff with login, logout, profile, password reset, token revocation, and session tracking.
- `[x] [Must] [S1]` Implement Spatie permission gates for Admin, Branch Manager, Provider Owner, Provider Staff, Agent, Advertiser, Finance, and Operations roles.
- `[x] [Must] [S1]` Build countries, states, districts, cities, areas, landmarks, and roads CRUD APIs plus dependent dropdown endpoints.
- `[x] [Must] [S1]` Add geospatial indexes and lookup caches for high-volume location filters.
- `[x] [Must] [S1]` Configure local environment files, queue connection, cache connection, database connection, storage disks, and development setup notes.
- `[x] [Should] [S1]` Add user sessions, activity logs, and audit logs for sensitive identity and configuration changes.
- `[x] [Should] [S1]` Add API request validation classes, resource serializers, pagination, filtering, sorting, and status conventions across foundation modules.

## Sprint 2: Provider Onboarding and Inventory Core

- `[x] [Must] [S2]` Build provider registration, profile update, KYC document upload, PAN/GST validation fields, and status lifecycle: Pending, Approved, Rejected, Suspended, Inactive.
- `[x] [Must] [S2]` Build Admin provider approval, rejection, suspension, document review, bank account review, and marketplace enablement workflows.
- `[x] [Must] [S2]` Build provider staff accounts with provider-scoped permissions and Business Portal access.
- `[x] [Must] [S2]` Build inventory CRUD for hoardings, digital screens, transit media, bus shelters, mall media, and airport media.
- `[x] [Must] [S2]` Build inventory gallery upload APIs for images, video, drone media, primary media selection, ordering, and deletion.
- `[x] [Must] [S2]` Build inventory pricing rules for daily, weekly, monthly, festival, special, and date-bounded seasonal pricing.
- `[x] [Must] [S2]` Build inventory maintenance and blocked-date records that feed availability checks.
- `[x] [Must] [S2]` Build map coordinate capture and Google Maps/Places integration hooks for inventory geotags.
- `[x] [Should] [S2]` Build provider and inventory verification queues for Admin and Branch portals.
- `[x] [Should] [S2]` Build Business Portal inventory list, add/edit forms, media gallery, pricing, map pin, and maintenance screens.
- `[x] [Should] [S2]` Add image optimization, video processing, and WebP compression pipeline for uploaded inventory media.

## Sprint 3: Campaigns, Booking Holds, and Availability Engine

- `[x] [Must] [S3]` Build campaign CRUD as the parent entity for multi-inventory advertising schedules.
- `[x] [Must] [S3]` Build campaign location targets, budget fields, advertiser/customer fields, campaign status lifecycle, and allocations.
- `[x] [Must] [S3]` Build booking creation through a Booking Engine service, not direct CRUD, enforcing campaign, inventory, provider, date, pricing, and tax snapshots.
- `[x] [Must] [S3]` Implement the Availability Engine with checks for booking calendar overlap, temporary Redis holds, maintenance blocks, provider manual blocks, and duplicate reservations.
- `[x] [Must] [S3]` Implement Redis atomic 30-minute hold locks for inventory/date ranges and release expired holds through a scheduled worker.
- `[x] [Must] [S3]` Write `booking_calendar` rows as the availability source of truth, with composite uniqueness around `inventory_id` and `date`.
- `[x] [Must] [S3]` Build provider approval and rejection flow before invoice/payment release.
- `[x] [Must] [S3]` Build booking lifecycle transitions: Draft, Temporary Reserved, Approval Pending, Reserved, Confirmed, Active, Completed, Cancelled, Rejected, Expired.
- `[x] [Should] [S3]` Build conflict logging into `booking_conflicts` and dashboards to resolve overlap, maintenance, provider block, and duplicate reservation cases.
- `[x] [Should] [S3]` Build booking calendar views for Admin and Business portals.
- `[x] [Should] [S3]` Build artwork upload and approval flow before campaign activation.
- `[x] [Should] [S3]` Build booking logs for all status transitions with portal source, user, remarks, and IP address.

## Sprint 4: Marketplace, Search, Maps, and Lead Capture

- `[x] [Must] [S4]` Build public marketplace inventory listing, inventory detail pages, featured listings, provider directory, and city landing pages.
- `[x] [Must] [S4]` Build search filters for country, state, district, city, area, landmark, road, media type, price, dimensions, lighting, traffic score, and availability.
- `[x] [Must] [S4]` Build dynamic cascading location dropdowns on website and portal forms.
- `[x] [Must] [S4]` Build map view with inventory pins, coordinate display, clustered results, and nearby inventory lookup.
- `[x] [Must] [S4]` Build marketplace inquiries and route them into CRM leads.
- `[x] [Should] [S4]` Add Meilisearch indexing for marketplace search, autocomplete, fuzzy matching, and hot keyword logging.
- `[x] [Should] [S4]` Build `marketplace_search_logs` analytics for location/media demand.
- `[ ] [Should] [S4]` Build SEO pages, banners, blogs, testimonials, homepage sections, and featured inventory management for Admin marketplace curation.
- `[ ] [Could] [S4]` Add route-based advertising search that groups inventory along travel corridors.

## Sprint 5: Finance, Billing, Payments, and Settlements

- `[ ] [Must] [S5]` Build invoice generation from confirmed reservations with subtotal, GST, CGST, SGST, IGST, total, payment status, and invoice numbers.
- `[ ] [Must] [S5]` Build Razorpay and Stripe payment gateway integrations plus webhook verification and transaction logging.
- `[ ] [Must] [S5]` Build payments ledger with transaction IDs, modes, statuses, and reconciliation fields.
- `[ ] [Must] [S5]` Build provider payout compiler with platform commission, GST deduction, TDS, final amount, and payment state.
- `[ ] [Must] [S5]` Build agent commission calculation and release workflow tied to completed bookings.
- `[ ] [Should] [S5]` Build refunds flow for cancelled bookings, including approval, ledger reversal, and payment gateway refund status.
- `[ ] [Should] [S5]` Build expenses, outstanding reports, GST reports, revenue reports, and settlement exports.
- `[ ] [Should] [S5]` Build PDF invoice/receipt generation and email dispatch.
- `[ ] [Should] [S5]` Enforce proof-verified settlements: payouts and commissions release only after required mounting/night proof approval.

## Sprint 6: Portals, Dashboards, Reports, and Notifications

- `[ ] [Must] [S6]` Build Admin Portal sidebar, topbar, dashboard, users/RBAC, branches, locations, providers, inventory, campaigns, bookings, finance, CRM, reports, analytics, notifications, settings, and system tools shell.
- `[ ] [Must] [S6]` Build Business Portal dashboard, inventory, availability, bookings, campaigns, artworks, proof uploads, finance, reports, staff, notifications, and settings shell.
- `[ ] [Must] [S6]` Build Agents Portal dashboard, leads, customers, campaigns, bookings, provider directory, commissions, reports, notifications, and profile shell.
- `[ ] [Must] [S6]` Build CRM leads, customers, sales pipeline, follow-ups, meetings, notes, conversions, and agent assignment workflows.
- `[ ] [Must] [S6]` Build notification system for email, SMS, WhatsApp, push, in-app alerts, templates, logs, and broadcast messages.
- `[ ] [Should] [S6]` Build analytics cache jobs for revenue, bookings, occupancy, provider, campaign, traffic, and geo analytics.
- `[ ] [Should] [S6]` Build report exports for revenue, provider, campaign, inventory, occupancy, finance, analytics, PDF, Excel, and CSV.
- `[ ] [Should] [S6]` Build dashboard cards and charts using the documented UI/UX system, Recharts, Lucide icons, and portal-specific layouts.
- `[ ] [Should] [S6]` Build branch portal MVP for district-scoped providers, inventory, campaigns, bookings, finance, CRM, reports, staff, and settings.

## Sprint 7: Storage, Security, DevOps, and Quality Gates

- `[ ] [Must] [S7]` Configure Cloudflare R2/AWS S3 Flysystem disks for inventory media, artwork files, proofs, provider documents, exports, and backups.
- `[ ] [Must] [S7]` Add upload validation for MIME type, size, image dimensions, video limits, malware scanning hook, and access control.
- `[ ] [Must] [S7]` Build Docker setup for Laravel API, web servers, portal apps, MySQL, Redis, queues, and local development.
- `[ ] [Must] [S7]` Configure Nginx, SSL, Cloudflare, CDN, Redis, queues, Supervisor, cron jobs, and backup/restore routines.
- `[ ] [Must] [S7]` Add API, booking engine, frontend, security, load, and QA test suites from the testing documents.
- `[ ] [Must] [S7]` Add CI/CD pipeline with migrations, tests, linting, static analysis, build artifacts, and deployment checks.
- `[ ] [Should] [S7]` Add monitoring for API uptime, queue failures, cron health, database errors, storage errors, and payment webhook failures.
- `[ ] [Should] [S7]` Add security hardening: rate limits, CORS policy, input escaping, encrypted sensitive fields, audit trails, permission regression tests, and backup encryption.
- `[ ] [Should] [S7]` Add performance work for query optimization, indexing strategy, analytics cache, read replicas, CDN caching, and load testing.

## Sprint 8: Future and Scale Capabilities

- `[ ] [Could] [S8]` Add AI recommendation engine for matching advertisers to inventory.
- `[ ] [Could] [S8]` Add AI pricing and yield recommendations based on occupancy, city demand, traffic scores, and seasonality.
- `[ ] [Could] [S8]` Add heatmaps and geo-intelligence analytics for road, landmark, traffic, and occupancy patterns.
- `[ ] [Could] [S8]` Add mobile apps for customers, providers, and agents.
- `[ ] [Could] [S8]` Add SaaS subscription model for providers and premium marketplace listings.
- `[ ] [Could] [S8]` Add IoT screen integrations, MQTT/WebSocket play logs, loop scheduling, and digital proof-of-play.
- `[ ] [Future] [S8]` Plan Kubernetes/microservices extraction for booking, search, finance, notification, and analytics services.
- `[ ] [Future] [S8]` Plan international expansion with multi-currency, timezone, VAT/GST variants, and country-specific legal settings.

---

# 2. Domain Backlog

## Architecture and Backend

- `[ ] [Must]` Establish `apis/Modules/{Domain}` structure with Controllers, Services, Repositories, Requests, Resources, Policies, and Routes.
- `[ ] [Must]` Enforce standardized success and error envelopes for all endpoints.
- `[ ] [Must]` Add centralized authorization and policy checks before service execution.
- `[ ] [Must]` Ensure financial, booking, and calendar writes run inside database transactions.
- `[ ] [Should]` Add OpenAPI/Postman collections for all documented endpoint families.
- `[ ] [Should]` Add API monitoring endpoints, health checks, and system status surfaces.

## Database

- `[ ] [Must]` Implement all schema tables from `database/database_schema.md`: auth, branches, locations, providers, inventory, campaigns, bookings, finance, CRM, marketplace, notifications, settings, and analytics.
- `[ ] [Must]` Add foreign keys, uniqueness constraints, indexes, and composite calendar constraints.
- `[ ] [Must]` Add migration tests that validate table existence, key columns, indexes, and enum lifecycle coverage.
- `[ ] [Should]` Add backup and restore scripts with restore verification.
- `[ ] [Should]` Add query optimization pass for booking calendar, marketplace search, analytics, reports, and activity logs.
- `[ ] [Could]` Add archival/sharding plan for `booking_calendar`, `activity_logs`, and `audit_logs`.

## Security

- `[ ] [Must]` Build Sanctum token auth, provider staff auth, password reset, password change, and logout flows.
- `[ ] [Must]` Build Spatie roles/permissions registry and seeders for every portal.
- `[ ] [Must]` Enforce scoped data access for branch, provider, agent, advertiser, finance, and admin contexts.
- `[ ] [Must]` Protect uploads, payment webhooks, finance exports, and raw analytics with strict permissions.
- `[ ] [Should]` Add MFA hooks through SMS TOTP or authenticator apps.
- `[ ] [Should]` Encrypt sensitive identity, bank, tax, and settlement data at rest where applicable.
- `[ ] [Should]` Add audit logs for role, permission, finance, payout, provider approval, booking status, and settings changes.

## Locations and Geo Intelligence

- `[ ] [Must]` Build location master CRUD and cascade endpoints for Country, State, District, City, Area, Landmark, and Road.
- `[ ] [Must]` Enforce latitude/longitude validation and coordinate bounds.
- `[ ] [Must]` Attach inventory to full location hierarchy and optional landmark/road relations.
- `[ ] [Should]` Add road traffic score management and location analytics.
- `[ ] [Should]` Add map clustering and nearby inventory search.
- `[ ] [Could]` Add heatmaps, route search, and predictive geo demand scoring.

## Providers and Branches

- `[ ] [Must]` Build provider KYC, documents, bank accounts, staff, approvals, and marketplace enablement.
- `[ ] [Must]` Build branch CRUD, district mapping, branch staff, commission setup, revenue sharing, and branch-scoped reports.
- `[ ] [Should]` Add branch-based provider and inventory verification queues.
- `[ ] [Should]` Add provider analytics for revenue, occupancy, pending approvals, and payout status.

## Inventory

- `[ ] [Must]` Build complete inventory CRUD with media type, category, dimensions, facing, lighting, traffic type, coordinates, visibility score, traffic score, base prices, featured flag, marketplace flag, and status.
- `[ ] [Must]` Build gallery, pricing, availability, maintenance, map location, and analytics submodules.
- `[ ] [Should]` Add inventory verification workflow before marketplace publication.
- `[ ] [Should]` Add digital screen-specific loop duration and frequency support through booking records.
- `[ ] [Could]` Add dynamic pricing suggestions and premium featured listing monetization.

## Campaigns and Bookings

- `[ ] [Must]` Treat Campaign as the parent entity and Booking as each inventory-level reservation.
- `[ ] [Must]` Route booking creation through Booking Engine and Availability Engine services only.
- `[ ] [Must]` Implement 30-minute Redis holds, provider approval, calendar writes, invoice generation, payment confirmation, activation, proof upload, completion, and settlement release.
- `[ ] [Must]` Enforce `booking_calendar` as the source of truth for availability.
- `[ ] [Should]` Add booking extensions, cancellations, conflict resolution, and booking logs.
- `[ ] [Should]` Add artwork approvals and proof upload gates with EXIF/geotag validation within 100 meters where image metadata is available.

## Finance

- `[ ] [Must]` Build tax settings and GST/CGST/SGST/IGST/TDS calculation services.
- `[ ] [Must]` Build invoices, payments, provider payouts, commissions, expenses, refunds, and finance reports.
- `[ ] [Must]` Build payment gateway integrations and webhook security.
- `[ ] [Should]` Add payout approval, bank transfer tracking, settlement export, and reconciliation status.
- `[ ] [Should]` Add automated invoice email/WhatsApp dispatch.

## CRM

- `[ ] [Must]` Build leads with lifecycle: New, Contacted, Interested, Negotiation, Converted, Lost.
- `[ ] [Must]` Build follow-ups, customers, sales pipeline, agent assignment, proposal-to-campaign conversion, and conversion logs.
- `[ ] [Should]` Add reminders and notification triggers for follow-ups and meetings.
- `[ ] [Could]` Add lead scoring and agent performance predictions.

## Marketplace and Search

- `[ ] [Must]` Build public listing, detail, provider, city, featured, nearby, map, and campaign inquiry pages.
- `[ ] [Must]` Build `/marketplace` and `/inventory/search` APIs with public-safe filters.
- `[ ] [Should]` Add Meilisearch indexing and background reindex jobs.
- `[ ] [Should]` Add search analytics and hot location dashboards.
- `[ ] [Could]` Add AI-assisted campaign inventory recommendations.

## Portals and UI/UX

- `[ ] [Must]` Implement design system foundations: emerald/saffron palette, Inter/Plus Jakarta fonts, sidebar, topbar, tables, forms, cards, badges, charts, icons, and motion rules.
- `[ ] [Must]` Build Admin Portal as the operational control center.
- `[ ] [Must]` Build Business Portal as provider operations console.
- `[ ] [Must]` Build Agents Portal as CRM and commissions console.
- `[ ] [Must]` Build Website as B2B marketplace and inquiry surface.
- `[ ] [Should]` Build responsive layouts and mobile-friendly portal drawers.
- `[ ] [Should]` Add empty, loading, error, permission-denied, and export states across all major screens.

## Storage and Integrations

- `[ ] [Must]` Implement uploads for inventory media, artwork, campaign proofs, and provider documents.
- `[ ] [Must]` Configure Cloudflare R2/AWS S3 disks and CDN URLs.
- `[ ] [Should]` Integrate Google Maps, Google Places, email, SMS, WhatsApp, Firebase push, Razorpay, and Stripe.
- `[ ] [Should]` Add storage lifecycle cleanup for deleted records and failed uploads.
- `[ ] [Could]` Add digital screen IoT proof-of-play integrations.

## DevOps and Operations

- `[ ] [Must]` Build Docker, Nginx, Redis, Supervisor, queue, cron, SSL, Cloudflare, and CDN setup.
- `[ ] [Must]` Build database backup/restore and application backup strategy.
- `[ ] [Must]` Build CI/CD pipeline and release process.
- `[ ] [Should]` Add monitoring dashboards and alerting for queues, jobs, API, database, storage, and payments.
- `[ ] [Should]` Add production scaling plan for MySQL replicas, Redis, CDN, and queue workers.

## Testing and Quality

- `[ ] [Must]` Add API tests for auth, locations, providers, inventory, campaigns, bookings, finance, CRM, uploads, reports, and settings.
- `[ ] [Must]` Add booking engine tests for race conditions, expired holds, conflict checks, provider rejection, payment confirmation, and calendar integrity.
- `[ ] [Must]` Add frontend tests for core portal flows and role-based visibility.
- `[ ] [Must]` Add security tests for auth, RBAC, upload access, rate limits, and sensitive exports.
- `[ ] [Should]` Add load tests for search, availability checks, booking creation, dashboards, and reports.
- `[ ] [Should]` Add QA release checklist based on the testing documentation set.

---

# 3. MVP Acceptance Criteria

- `[ ]` A user can authenticate securely and see only role-permitted modules.
- `[ ]` Admin can manage locations, users, roles, branches, providers, inventory, campaigns, bookings, finance, reports, notifications, and settings.
- `[ ]` Provider can onboard, upload KYC, manage staff, add inventory, set pricing, block availability, approve bookings, upload proofs, and view payouts.
- `[ ]` Agent can create/manage leads, build campaign proposals, check inventory availability, assist bookings, and track commissions.
- `[ ]` Public website users can search, filter, view map/list inventory, inspect listing details, and submit inquiries.
- `[ ]` Booking creation cannot double-book inventory dates under concurrent requests.
- `[ ]` Temporary holds expire automatically after 30 minutes.
- `[ ]` Provider approval is required before invoice/payment release.
- `[ ]` Payment confirmation generates ledger records and moves booking state forward.
- `[ ]` Provider payouts and agent commissions remain gated until required proof approval.
- `[ ]` All critical operations are audited and covered by automated tests.

---

# 4. Documentation Follow-Up Tasks

- `[ ] [Should]` Reconcile README file counts with current workspace count of 191 Markdown files.
- `[ ] [Should]` Normalize mojibake/encoding artifacts in documentation headings, diagrams, and symbols.
- `[ ] [Should]` Replace generic template text in repeated module docs with module-specific requirements, endpoints, validation, permissions, and acceptance criteria.
- `[ ] [Should]` Add cross-links from module docs to exact backlog items or epic IDs.
- `[ ] [Should]` Add OpenAPI schema references to API documentation.
- `[ ] [Could]` Generate a requirements traceability matrix mapping each document folder to backlog epics and tests.

---

# 5. Current Critical Path

1. Migrations and seeders for the 49-table schema.
2. Sanctum authentication, Spatie permissions, and scoped access.
3. Location hierarchy APIs and caches.
4. Provider onboarding and inventory ingestion.
5. Availability Engine and Redis 30-minute booking holds.
6. Booking lifecycle with provider approval and calendar source of truth.
7. Invoice, payment, payout, commission, and proof-gated settlement flows.
8. Portal dashboards and public marketplace search.
9. Storage, notifications, testing, DevOps, and monitoring.

---

*SODARS Enterprise ERP and B2B Marketplace - master backlog refreshed from the complete documentation corpus.*
