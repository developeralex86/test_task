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