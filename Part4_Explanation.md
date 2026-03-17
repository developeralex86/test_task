# Part 4 — Engineering Thinking

---

## If This CRM Grows to 50 Sales Reps and 500k Leads

### Database

The first thing to address is indexing and query performance. At 500k leads, any unindexed filter — by status, assigned rep, company — becomes a full table scan. Composite indexes on the most common filter combinations (`status + assigned_user_id`, `company + status`) would be the first move, with zero downtime.

Pagination is already in place, but response caching with Redis would significantly reduce database load for the most-queried pages like "my open leads" — a 5-minute TTL covers most use cases without stale data risk.

For heavy reporting queries (monthly pipeline summaries, rep performance dashboards), a read replica takes the load off the primary database and keeps write performance consistent even under dashboard traffic.

If search becomes a pain point — `LIKE '%organic%'` against 500k rows will be slow — moving to **Elasticsearch** for full-text search is the right call. MySQL handles transactions; a search engine handles text.

### Application

With 50 reps working simultaneously, a **queue system (Laravel Horizon + Redis)** becomes important. Email notifications, Slack alerts, webhook triggers, and PDF exports should all run in background jobs — not in the HTTP request cycle.

**API rate limiting** per user prevents one rep's bulk import from degrading the experience for everyone else.

For lead imports (common in B2B CRM), chunked background processing via `chunk()` or `lazy()` avoids memory exhaustion on large CSV uploads.

### Architecture

At this scale, it is worth separating read and write paths. The leads listing endpoint (read-heavy, called constantly) and the assignment/note creation endpoints (write operations) have different scaling needs. A CQRS-lite approach — separate query services from command handlers — keeps the codebase clean as complexity grows.

Feature flags (via **Laravel Pennant**) allow gradual rollouts to subsets of reps without full deployments, reducing risk when shipping changes to a live sales team.

---

## Where AI Automation Could Help

### Lead Scoring
Train a simple model on historical data — which leads converted, how quickly, what their initial note content looked like — and surface a score alongside each lead. Reps focus on the highest-probability leads first rather than working the list top to bottom.

### Note Summarisation
A rep who inherits a lead should not have to read 40 notes to get context. An LLM call against the full note history can generate a 3-sentence summary: current status, last action, recommended next step. This is a direct productivity win with minimal engineering effort.

### Smart Follow-up Reminders
Parse note content to detect intent — "follow up Friday", "call back next week", "waiting on pricing approval" — and automatically create a follow-up task. No manual CRM data entry required.

### Auto-tagging and Categorisation
Classify leads by industry, interest level, or product fit based on note content. Useful for filtering and reporting without requiring reps to manually fill in fields they routinely skip.

### Suggested Reply Drafts
When a rep opens a lead to write a note or send an email, pre-populate a draft based on the lead's history and the previous interaction. The rep edits and approves — AI does the first draft.

### Anomaly Detection
Flag leads that have gone quiet — no notes in 14 days, assigned but never contacted — so managers can intervene before a warm lead goes cold.

---

## Summary

| Area | Priority | Approach |
|------|----------|----------|
| Indexing | Immediate | Composite indexes on filter columns |
| Caching | Short term | Redis for hot-path read queries |
| Search | Short term | Meilisearch for full-text lead search |
| Background jobs | Short term | Laravel Horizon for notifications and exports |
| Read replica | Medium term | Separate reporting queries from transactional writes |
| Lead scoring | Medium term | ML model trained on conversion history |
| Note summarisation | Quick win | LLM API call on note history |
| Follow-up detection | Medium term | NLP on note content to create tasks |