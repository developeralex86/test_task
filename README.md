# Super Organics CRM — Technical Assessment

A B2B lead CRM built with Laravel 13. This submission covers all four parts of the assessment — debugging, data modeling, feature implementation, and engineering thinking.

---

## Submission Structure

| File | Description |
|------|-------------|
| `Part1_Explanation.md` | Bug analysis, N+1 fix, architecture improvements, tradeoffs |
| `Part2_Explanation.md` | Lead notes data modeling — schema, migration, models, efficient querying |
| `Part3_Explanation.md` | Feature implementation — `POST /api/leads/{lead}/notes` endpoint |
| `Part4_Explanation.md` | Engineering thinking — scaling to 500k leads, AI automation opportunities |

---

## Quick Summary

### Part 1 — Debug & Review

The original `LeadController` had an **N+1 query problem** — it fired one database query per lead to fetch the assigned user. For 500 leads that is 501 queries.

**Fix:** Replaced raw `DB::table()` queries with Eloquent eager loading using `with('assignedUser')`. Query count dropped from N+1 to always 2, regardless of table size.

**Key tradeoffs explained** — why eager loading over lazy loading, why `paginate(50)` over returning all records, why API Resources over raw model responses.

→ See [`Part1_Explanation.md`](Part1_Explanation.md)

---

### Part 2 — Data Modeling

Designed the `lead_notes` table to support multiple timestamped notes per lead, each attributed to the rep who wrote it.

**Key decisions:**
- Append-only notes — no `updated_at`, corrections become new notes
- Composite index on `(lead_id, created_at)` — covers both list and latest-note queries
- `latestOfMany()` relationship on `Lead` — fetches the latest note per lead in a single subquery, not a loop

→ See [`Part2_Explanation.md`](Part2_Explanation.md)

---

### Part 3 — Feature Implementation

Implemented the `POST /api/leads/{lead}/notes` endpoint end-to-end.

**Pieces delivered:**
- `LeadNote` model with `HasFactory`, immutable timestamps, and relationships
- Migration with composite index
- `StoreLeadNoteRequest` for validation — returns `422` automatically on invalid input
- `LeadNoteController@store` — saves note, associates with `Auth::user()`, returns `201`

```bash
curl -X POST http://localhost:8000/api/leads/1/notes \
  -H "Content-Type: application/json" \
  -d '{"note": "Buyer wants to review wholesale pricing."}'
```

→ See [`Part3_Explanation.md`](Part3_Explanation.md)

---

### Part 4 — Engineering Thinking

**Scaling to 50 reps and 500k leads:**
Composite indexes, Redis caching, read replica for reporting, Elasticsearch for full-text search, Laravel Horizon for background jobs.

**AI automation opportunities:**
Lead scoring, note summarisation, smart follow-up reminders, auto-tagging, suggested reply drafts, anomaly detection on cold leads.

→ See [`Part4_Explanation.md`](Part4_Explanation.md)

---

## Running the Project

```bash
# Install dependencies
composer install

# Copy environment file and set DB credentials
cp .env.example .env
php artisan key:generate

# Register API routes (Laravel 13)
# Add to bootstrap/app.php → withRouting():
# api: __DIR__.'/../routes/api.php',

# Run migrations
php artisan migrate

# Seed test data (optional)
php artisan db:seed

# Start the server
php artisan serve
```

---

## Tech Stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 13 |
| Database | MySQL |
| Auth | Laravel Sanctum (assumed for spec) |
| Testing | PHPUnit via `php artisan test` |
| Queue (recommended) | Laravel Horizon + Redis |
| Search (recommended) | Meilisearch |