# Integrations

Phase 1 installs the queue-ready Laravel foundation but does not call external APIs.

Planned service boundaries:

- Google: Drive, Docs, Analytics, Search Console, Ads, Gmail services.
- HubSpot: explicit contact/company/deal operations only.
- Semrush: snapshots, keywords, cache, retry and rate-limit handling.
- Screaming Frog: queued import/crawl workflow.

Integration credentials must be encrypted with Laravel encryption and must not be returned by API resources, MCP tools, logs, exceptions, or frontend state.
