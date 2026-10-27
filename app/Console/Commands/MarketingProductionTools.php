<?php

namespace App\Console\Commands;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionBudget;
use App\Models\MarketingProductionPlan;
use App\Services\Marketing\MarketingProductionProcessor;
use App\Services\Marketing\MarketingVoiceLibrary;
use App\Services\Marketing\MarketingProductionFailure;
use Illuminate\Console\Command;

class MarketingProductionTools extends Command
{
    protected $signature = 'marketing:production-tools {action : budget, import-voice, or interrupted} {--project=} {--production=} {--audio=} {--alignment=} {--journal=}';

    protected $description = 'Operator-only local production test setup, verified voice reuse and interrupted-task review';

    public function handle(MarketingVoiceLibrary $voices, MarketingProductionProcessor $processor): int
    {
        try {
            if ($this->argument('action') === 'budget') {
                $id = (string) config('marketing.production.test_budget');
                if ($id === '') {
                    throw new \RuntimeException('Configure the internal test budget identifier first.');
                }
                $budget = MarketingProductionBudget::firstOrCreate(['id' => $id], ['ceiling_microusd' => 5000000, 'reserved_microusd' => 1993000, 'note' => 'User-authorized US$5 local P2 test. Prior reservation: Seedance returned US$0.993 + US$1 conservatively held for previous real ElevenLabs speech (USD billing unavailable). Not a daily quota.']);
                $this->line(json_encode($budget->only(['id', 'ceiling_microusd', 'reserved_microusd'])));

                return self::SUCCESS;
            }
            if ($this->argument('action') === 'interrupted') {
                $task = MarketingProduction::findOrFail($this->option('production'));
                if (! $task->started_at || $task->started_at->gt(now()->subMinutes(20))) {
                    throw new \RuntimeException('The task may still be running. Do not mark its outcome unknown yet.');
                }
                $processor->interrupted($task->id);
                $this->info('Recorded the interrupted outcome. No supplier request was repeated.');

                return self::SUCCESS;
            }
            if ($this->argument('action') !== 'import-voice') {
                throw new \RuntimeException('Unknown operator action.');
            }
            $plan = MarketingProductionPlan::where('project_id', $this->option('project'))->where('status', 'budget_confirmed')->latest()->firstOrFail();
            $paths = [];
            foreach (['audio' => 5000000, 'alignment' => 1000000, 'journal' => 50000] as $key => $limit) {
                $path = (string) $this->option($key);
                if (! is_file($path) || filesize($path) > $limit) {
                    throw new \RuntimeException('Provide bounded local audio, alignment and completed-probe journal files.');
                }
                $paths[$key] = $path;
            }
            $data = json_decode(file_get_contents($paths['alignment']), true, 64, JSON_THROW_ON_ERROR);
            $journal = json_decode(file_get_contents($paths['journal']), true, 64, JSON_THROW_ON_ERROR);
            $spec = $voices->spec($plan);
            if (($data['text'] ?? null) !== $spec['text'] || ($journal['state'] ?? '') !== 'completed' || ($journal['http_status'] ?? 0) !== 200 || ($journal['model'] ?? '') !== $spec['model'] || ($journal['voice'] ?? '') !== 'stock Rachel' || ($journal['text_sha256'] ?? '') !== hash('sha256', $spec['text'])) {
                throw new \RuntimeException('Probe provenance does not match this exact approved voice specification.');
            }
            $voice = $voices->import($plan, file_get_contents($paths['audio']), $data['alignment'], ['character_cost' => $journal['provider_character_cost'] ?? null, 'usd_cost' => null, 'prior_paid_submission' => true, 'provenance' => 'validated_completed_local_probe']);
            $this->line(json_encode(['voice_id' => $voice->id, 'status' => $voice->status, 'duration_seconds' => $voice->duration_seconds, 'new_paid_requests' => 0]));

            return self::SUCCESS;
        } catch (MarketingProductionFailure $error) {
            $this->error('Operation stopped: '.$error->reason.'. No paid request was made by this command.');

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('Operation did not complete. Check the selected project, completed probe metadata and local media runtime; no paid request was made by this command.');

            return self::FAILURE;
        }
    }
}
