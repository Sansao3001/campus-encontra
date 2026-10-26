<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, array $overrides = []): Item
    {
        return $user->items()->create(array_merge([
            'title' => 'Objecto',
            'category' => 'Outros',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ], $overrides));
    }

    public function test_totais_do_dashboard_correspondem_aos_dados(): void
    {
        $admin = User::factory()->admin()->create();
        $studentOne = User::factory()->create();
        $studentTwo = User::factory()->suspended()->create();

        $lost = $this->makeItem($studentOne, ['type' => 'lost', 'status' => 'open']);
        $found = $this->makeItem($studentTwo, ['type' => 'found', 'status' => 'pending']);
        $returned = $this->makeItem($studentOne, ['type' => 'found', 'status' => 'returned']);
        $moderated = $this->makeItem($studentOne, ['type' => 'lost', 'status' => 'open']);

        $moderated->forceFill([
            'moderated_at' => now(),
            'moderation_reason' => 'Conteúdo inadequado.',
            'moderated_by_id' => $admin->id,
        ])->save();

        Claim::create([
            'item_id' => $lost->id,
            'claimant_id' => $studentTwo->id,
            'status' => Claim::STATUS_PENDING,
        ]);
        Claim::create([
            'item_id' => $found->id,
            'claimant_id' => $studentOne->id,
            'status' => Claim::STATUS_ACCEPTED,
        ]);
        Claim::create([
            'item_id' => $returned->id,
            'claimant_id' => $studentTwo->id,
            'status' => Claim::STATUS_COMPLETED,
        ]);

        Report::create([
            'reporter_id' => $studentOne->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $lost->id,
            'reason' => 'spam',
            'status' => Report::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('users.total', 3)
            ->assertJsonPath('users.admins', 1)
            ->assertJsonPath('users.students', 2)
            ->assertJsonPath('users.suspended', 1)
            ->assertJsonPath('items.total', 4)
            ->assertJsonPath('items.published', 3)
            ->assertJsonPath('items.moderated', 1)
            ->assertJsonPath('items.lost', 2)
            ->assertJsonPath('items.found', 2)
            ->assertJsonPath('items.open', 2)
            ->assertJsonPath('items.pending', 1)
            ->assertJsonPath('items.returned', 1)
            ->assertJsonPath('claims.total', 3)
            ->assertJsonPath('claims.pending', 1)
            ->assertJsonPath('claims.accepted', 1)
            ->assertJsonPath('claims.rejected', 0)
            ->assertJsonPath('claims.cancelled', 0)
            ->assertJsonPath('claims.completed', 1)
            ->assertJsonPath('reports.total', 1)
            ->assertJsonPath('reports.pending', 1)
            ->assertJsonStructure([
                'users' => ['total', 'admins', 'students', 'suspended'],
                'items' => ['total', 'published', 'moderated', 'lost', 'found', 'open', 'pending', 'returned'],
                'claims' => ['total', 'pending', 'accepted', 'rejected', 'cancelled', 'completed'],
                'reports' => ['total', 'pending', 'reviewing', 'resolved', 'dismissed'],
                'recent_actions',
            ]);
    }
}
