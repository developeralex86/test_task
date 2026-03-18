# Super Organics CRM — Technical Assessment

## Debug & Review

### Q1. What performance problem exists in this code?

The code has an **N+1 query problem**.

The controller fires one query to get all leads (`SELECT * FROM leads`), then fires
**one additional query per lead** to fetch its assigned user. For 500 leads, that's
501 database round-trips. For 10,000 leads, it's 10,001.

This compounds severely under load: each web request holds a database connection open
for the entire loop, blocking connection-pool slots, adding latency proportional to
table size, and making the endpoint progressively slower as the business grows.

```php
// BUGGY CODE — production right now
$leads = DB::table('leads')->get();        // Query 1
foreach ($leads as $lead) {
    $lead->user = DB::table('users')       // Query 2, 3, 4... N+1
        ->where('id', $lead->assigned_user_id)
        ->first();
}
```

### Q2. How would you fix it in Laravel?

**Short-term fix**: Use Eloquent with `with()` (eager loading). Laravel resolves
all users in a single `WHERE id IN (...)` query.

```php
// ONE query for leads + ONE query for all users = 2 total, always
$leads = Lead::with('assignedUser')->get();
return LeadResource::collection($leads);
```

**Why this is correct**: `with('assignedUser')` tells Eloquent to collect all
`assigned_user_id` values from the leads result, then issue a single
`SELECT * FROM users WHERE id IN (1, 4, 7, ...)` — regardless of how many leads
there are. The relationship is then hydrated in-memory.

**Additional improvements**:
- Eloquent Models (`Lead`, `User`) with typed casts and proper relationships
- API Resource classes for clean, versioned JSON output (no raw model leakage)
- Pagination to cap per-request payload at 50 leads
- Policy classes for authorization (not in raw DB query code)

### Q3. If the table grows to 1M leads, what database changes would you consider?

**1. Indexes first**

```sql
-- Already missing: the foreign key column has no index
CREATE INDEX idx_leads_assigned_user_id ON leads (assigned_user_id);

-- Common filter patterns need composite indexes
CREATE INDEX idx_leads_status_created ON leads (status, created_at DESC);
CREATE INDEX idx_leads_company_status ON leads (company_id, status);
```

**2. Pagination**

Return 50 rows at a time via `paginate(50)`. Fetching 1M rows into PHP memory
will crash the process regardless of query speed.

**3. Read replicas for reporting queries**

CRM dashboards ("show me all open leads this month") are expensive reads that
should hit a replica, keeping the primary free for writes.

```php
// In config/database.php
'mysql' => [
    'read'  => ['host' => env('DB_READ_HOST')],
    'write' => ['host' => env('DB_WRITE_HOST')],
]
```

**4. Cache hot-path queries with Redis**

```php
$leads = Cache::remember("leads.page.{$page}", 300, fn() =>
    Lead::with('assignedUser')->paginate(50)
);
```

**5. Consider partitioning by status or date**

For archival patterns (closed leads from 2021), MySQL range partitioning keeps
hot data in smaller partitions that fit in the buffer pool.

**6. Full-text search → Elasticsearch**

`LIKE '%organic%'` against 1M rows will table-scan. Extract search to a dedicated
engine and keep MySQL for transactional data.

**7. Architecture Improvements**

See `app/` directory for the refactored implementation.

### Key decisions:

| Before | After | Reason |
|---|---|---|
| `DB::table()` raw queries | Eloquent Models | Relationships, casts, scopes, events |
| No pagination | `paginate(50)` | Memory safety at scale |
| Raw model in JSON | API Resource classes | Stable contract, hide internals |
| No validation | Form Request classes | Separation of concerns |
| No authorization | Policy + Gates | RBAC without controller clutter |
| N+1 queries | Eager loading | Constant query count |

**8. Key Feature Implemented**

### Feature: Lead Assignment with Audit Trail

**What it does**:
- Sales managers can assign/reassign leads to team members
- Every assignment change is logged (who changed it, when, previous owner)
- Assignment is validated (can only assign to active users in same org)
- Emits a `LeadAssigned` event (extensible: send email, Slack notification, etc.)

**Files**:
```
app/Http/Controllers/LeadController.php   — assign() endpoint
app/Models/Lead.php                       — relationships + scopes
app/Models/LeadActivity.php               — audit trail model
app/Events/LeadAssigned.php               — event for listeners
app/Http/Requests/AssignLeadRequest.php   — validation
app/Http/Resources/LeadResource.php       — API response shaping
database/migrations/                      — all schema files
```

**API Endpoint**:
```
PATCH /api/leads/{lead}/assign
Body: { "user_id": 42 }
```

---

Test the endpoints:
```bash
# List leads (paginated, eager-loaded)
curl http://localhost:8000/api/v1/leads

# Assign lead #1 to user #3
curl -X PATCH http://localhost:8000/api/v1/leads/1/assign \
  -H "Content-Type: application/json" \
  -d '{"user_id": 3}'
```
