# Part 2 — Lead Notes Feature

> Add notes to leads • List notes • Efficient querying

---

## What Was Built

Sales reps can now add timestamped notes to any lead and retrieve the full note history. Two API endpoints were implemented:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/leads/{lead}/notes` | Add a note to a lead |
| GET | `/api/v1/leads/{lead}/notes` | List all notes for a lead (newest first) |

---

## Database Schema

### Table: lead_notes

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | No | Auto-increment |
| lead_id | bigint FK | No | References leads.id — cascade delete |
| user_id | bigint FK | No | Rep who wrote the note |
| note | text | No | The note content |
| created_at | timestamp | No | Immutable — no updated_at |

> Notes are immutable. No `updated_at` column. Corrections are new notes, not edits.

### Migration

```bash
php artisan make:migration create_lead_notes_table
```

```php
Schema::create('lead_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->text('note');
    $table->timestamp('created_at');
    $table->index(['lead_id', 'created_at']);  // key index
});
```

```bash
php artisan migrate
```

---

## Models

### LeadNote — `app/Models/LeadNote.php`

```bash
php artisan make:model LeadNote
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    use HasFactory;

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['lead_id', 'user_id', 'note', 'created_at'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Add to Lead — `app/Models/Lead.php`

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// All notes — newest first
public function notes(): HasMany
{
    return $this->hasMany(LeadNote::class)->latest();
}

// Latest note only — uses subquery, not a loop
public function latestNote(): HasOne
{
    return $this->hasOne(LeadNote::class)->latestOfMany();
}
```

---

## Form Request

Validates the incoming note before the controller runs. Returns `422` automatically if note is missing or too short.

```bash
php artisan make:request StoreLeadNoteRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3'],
        ];
    }
}
```

---

## Controller

```bash
php artisan make:controller LeadNoteController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadNoteRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Http\JsonResponse;

class LeadNoteController extends Controller
{
    // GET /api/v1/leads/{lead}/notes
    public function index(Lead $lead): JsonResponse
    {
        $notes = $lead->notes()->with('user')->get();

        return response()->json([
            'data' => $notes->map(fn($n) => [
                'id'         => $n->id,
                'note'       => $n->note,
                'created_by' => $n->user?->name,
                'created_at' => $n->created_at,
            ])
        ]);
    }

    // POST /api/v1/leads/{lead}/notes
    public function store(StoreLeadNoteRequest $request, Lead $lead): JsonResponse
    {
        $note = LeadNote::create([
            'lead_id'    => $lead->id,
            'user_id'    => $request->user()->id,
            'note'       => $request->validated('note'),
            'created_at' => now(),
        ]);

        return response()->json([
            'id'         => $note->id,
            'note'       => $note->note,
            'created_by' => $request->user()->name,
            'created_at' => $note->created_at,
        ], 201);
    }
}
```

---

## Routes

Add to `routes/api.php`:

```php
use App\Http\Controllers\LeadNoteController;

Route::prefix('v1')->group(function () {
    Route::get('/leads',                 [LeadController::class, 'index']);
    Route::get('/leads/{lead}',          [LeadController::class, 'show']);
    Route::patch('/leads/{lead}/assign', [LeadController::class, 'assign']);

    // Lead notes
    Route::get('/leads/{lead}/notes',    [LeadNoteController::class, 'index']);
    Route::post('/leads/{lead}/notes',   [LeadNoteController::class, 'store']);
});
```

---

## Testing with curl

### Add a note to a lead

```bash
curl -X POST http://localhost:8000/api/v1/leads/1/notes \
  -H "Content-Type: application/json" \
  -d '{"note": "Called buyer, interested in organic line"}'
```

Expected response — `201 Created`:

```json
{
  "id": 1,
  "note": "Called buyer, interested in organic line",
  "created_by": "Sarah Chen",
  "created_at": "2026-03-18T10:22:00.000000Z"
}
```

### Get all notes for a lead

```bash
curl http://localhost:8000/api/v1/leads/1/notes
```

Expected response — `200 OK`:

```json
{
  "data": [
    {
      "id": 2,
      "note": "Wants wholesale pricing sheet",
      "created_by": "Sarah Chen",
      "created_at": "2026-03-18T10:30:00.000000Z"
    },
    {
      "id": 1,
      "note": "Called buyer, interested in organic line",
      "created_by": "Sarah Chen",
      "created_at": "2026-03-18T10:22:00.000000Z"
    }
  ]
}
```

### Verify empty note is rejected

```bash
curl -X POST http://localhost:8000/api/v1/leads/1/notes \
  -H "Content-Type: application/json" \
  -d '{"note": ""}'
```

Returns `422 Unprocessable Content`:

```json
{
  "message": "The note field is required."
}
```

---

## How Latest Note Query Works

The `index()` method uses `$lead->notes()` which is already defined as `->latest()` on the relationship — newest notes come first automatically.

For loading the latest note alongside lead listings (e.g. a leads dashboard), use eager loading:

```php
// Always 2 queries — never N+1
$leads = Lead::with('latestNote')->paginate(50);

// Each lead already has its latest note loaded
echo $lead->latestNote?->note;
```

> `latestOfMany()` generates a single subquery internally — `SELECT MAX(id) GROUP BY lead_id`. One database round-trip for all leads, not one per lead.

---

## Design Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Note mutability | Append-only | Audit trail — corrections are new notes, not edits |
| Timestamps | `created_at` only | No `updated_at` needed on immutable records |
| Lead delete | `cascadeOnDelete` | Notes are owned by the lead — removed together |
| User delete | `restrictOnDelete` | Cannot delete a user who has written notes |
| Ordering | `latest()` on relationship | Newest notes always appear first without extra sorting |
| Latest note | `latestOfMany()` | Single subquery — avoids N+1 on lead listings |
| Index | `(lead_id, created_at)` | One index covers both list and latest-note queries |