<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LatestNoteQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_note_loads_in_two_queries_not_n_plus_1(): void
    {
        $rep = User::factory()->create();
        Lead::factory(10)->create()->each(fn($lead) =>
            LeadNote::factory(3)->create([
                'lead_id' => $lead->id,
                'user_id' => $rep->id,
            ])
        );

        // Use query log instead of DB::listen for sqlite compatibility
        DB::enableQueryLog();

        Lead::with('latestNote')->get();

        $queryCount = count(DB::getQueryLog());

        $this->assertSame(2, $queryCount,
            "Expected 2 queries, got {$queryCount}. N+1 bug detected."
        );
    }
}