<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Events\LeadAssigned;
use App\Http\Requests\AssignLeadRequest;
use App\Http\Resources\LeadResource;

class LeadController extends Controller
{
    // FIXED — always 2 queries regardless of lead count
    public function index(Request $request) {
        $leads = Lead::query()
            ->with('assignedUser')   // kills N+1
            ->when($request->search, fn($q,$s) => $q->search($s))
            ->latest()
            ->paginate(50);
        return LeadResource::collection($leads);
    }

    // NEW — assignment with audit trail
    public function assign(AssignLeadRequest $request, Lead $lead) {
        $prev = $lead->assigned_user_id;
        $next = $request->validated('user_id');

        if ($prev === $next) {
            return response()->json(['message' => 'Already assigned.'], 422);
        }

        $lead->update(['assigned_user_id' => $next]);

        LeadActivity::create([
            'lead_id'            => $lead->id,
            'changed_by_user_id' => $request->user()->id,
            'action'             => 'assigned',
            'from_user_id'       => $prev,
            'to_user_id'         => $next,
            'occurred_at'        => now(),
        ]);

        event(new LeadAssigned($lead, $prev, $next));

        return response()->json([
            'message' => 'Lead assigned successfully.',
            'lead'    => new LeadResource($lead->load('assignedUser')),
        ]);
    }
}
