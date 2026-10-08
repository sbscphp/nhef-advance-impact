<?php

use App\Enums\AuditActionEnum;
use App\Enums\ProspectPipelineStageEnum;
use App\Models\AuditLog;
use App\Models\Prospect;
use App\Services\Crm\ProspectService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->string('stage');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'stage']);
            $table->index(['prospect_id', 'exited_at']);
        });

        $this->backfillFromAuditTrail();
    }

    /**
     * Every prospect starts at Identification ({@see ProspectService::create()})
     * and every later move is already recorded, from/to and timestamped, in audit_logs
     * (PROSPECT_STAGE_CHANGED). Reconstruct real per-stage history from that instead of leaving
     * every existing prospect with a blank pipeline.
     */
    private function backfillFromAuditTrail(): void
    {
        Prospect::query()->orderBy('id')->chunkById(200, function ($prospects): void {
            foreach ($prospects as $prospect) {
                $rows = [];
                $stage = ProspectPipelineStageEnum::IDENTIFICATION->value;
                $enteredAt = $prospect->created_at;

                $transitions = AuditLog::query()
                    ->where('model', Prospect::class)
                    ->where('model_id', $prospect->uuid)
                    ->where('action', AuditActionEnum::PROSPECT_STAGE_CHANGED->value)
                    ->orderBy('created_at')
                    ->get(['created_at', 'metadata']);

                foreach ($transitions as $transition) {
                    $to = $transition->metadata['data']['to'] ?? null;
                    if (! is_string($to) || $to === '') {
                        continue;
                    }

                    $rows[] = [
                        'prospect_id' => $prospect->id,
                        'stage' => $stage,
                        'entered_at' => $enteredAt,
                        'exited_at' => $transition->created_at,
                        'created_at' => $enteredAt,
                        'updated_at' => $transition->created_at,
                    ];

                    $stage = $to;
                    $enteredAt = $transition->created_at;
                }

                $rows[] = [
                    'prospect_id' => $prospect->id,
                    'stage' => $stage,
                    'entered_at' => $enteredAt,
                    'exited_at' => null,
                    'created_at' => $enteredAt,
                    'updated_at' => $enteredAt,
                ];

                DB::table('prospect_stage_histories')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_stage_histories');
    }
};
