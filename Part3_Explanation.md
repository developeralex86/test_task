# Part 3 — Feature Implementation

> `POST /api/leads/{lead}/notes` — Add a note to a lead

---

## Overview

Implement an API endpoint that allows a logged-in sales rep to add a note to a lead. The note is saved to the database, associated with the authenticated user, and the created note is returned in the response.

---

## Files Created

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_lead_notes_table.php` | Creates the `lead_notes` table |
| `app/Models/LeadNote.php` | Eloquent model |
| `app/Http/Requests/StoreLeadNoteRequest.php` | Validation |
| `app/Http/Controllers/LeadNoteController.php` | Controller with `store()` method |
| `routes/api.php` | Registers the endpoint |

---

## Migration

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
    $table->index(['lead_id', 'created_at']);
});
```

```bash
php artisan migrate
```

---

## Model

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

---

## Validation

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
        // Auth::user() is assumed to exist per spec
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
    public function store(StoreLeadNoteRequest $request, Lead $lead): JsonResponse
    {
        $note = LeadNote::create([
            'lead_id'    => $lead->id,
            'user_id'    => $request->user()->id,  // logged-in user
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

## Route

Add to `routes/api.php`:

```php
use App\Http\Controllers\LeadNoteController;

Route::post('/leads/{lead}/notes', [LeadNoteController::class, 'store']);
```

---

## Example Request

```bash
curl -X POST http://localhost:8000/api/leads/1/notes \
  -H "Content-Type: application/json" \
  -d '{"note": "Buyer wants to review wholesale pricing."}'
```

---

## Example Response — `201 Created`

```json
{
  "id": 1,
  "note": "Buyer wants to review wholesale pricing.",
  "created_by": "Sarah Chen",
  "created_at": "2026-03-18T10:22:00.000000Z"
}
```

---

## Validation Error — `422 Unprocessable Content`

If `note` is missing or empty:

```bash
curl -X POST http://localhost:8000/api/leads/1/notes \
  -H "Content-Type: application/json" \
  -d '{"note": ""}'
```

```json
{
  "message": "The note field is required.",
  "errors": {
    "note": ["The note field is required."]
  }
}
```

---

## How It Works

1. Request hits `StoreLeadNoteRequest` — validates `note` is present and at least 3 characters. Returns `422` automatically if invalid.
2. `store()` creates the note with `lead_id` from the URL, `user_id` from `Auth::user()`, and the validated note text.
3. Returns the created note as JSON with `201 Created`.

---

## Assumptions

- `Auth::user()` is available as stated in the spec — no authentication layer was built.
- `lead_id` comes from route model binding — Laravel automatically resolves `{lead}` to the `Lead` model and returns `404` if not found.
- Notes are immutable — no `updated_at` column, no edit endpoint.