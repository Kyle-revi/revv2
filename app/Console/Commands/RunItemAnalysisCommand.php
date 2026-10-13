<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\ItemAnalysis\ItemAnalysisService;
use Illuminate\Console\Command;

class RunItemAnalysisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'item-analysis:run {--module= : Specific module ID to analyze} {--stage= : Quiz stage (pre_test, post_test, or leave empty)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and persist psychometric item difficulty, discrimination, distractor efficiency, and KR-20 reliability metrics for quiz modules';

    /**
     * Execute the console command.
     */
    public function handle(ItemAnalysisService $service): int
    {
        $moduleId = $this->option('module');
        $stage = $this->option('stage');

        if ($moduleId) {
            $module = Module::find($moduleId);
            if (! $module) {
                $this->error("Module ID {$moduleId} not found.");

                return 1;
            }

            $this->info("Analyzing module #{$module->id}: {$module->title}".($stage ? " [Stage: {$stage}]" : '').'...');
            $result = $service->analyzeQuiz((int) $moduleId, $stage ? (string) $stage : null, true);

            $this->displaySummary($result);

            return 0;
        }

        // Loop through all quiz modules with completed attempts
        $modules = Module::where('is_quiz', true)
            ->orWhere('is_formal_assessment', true)
            ->orWhereHas('attempts', fn ($q) => $q->where('status', 'completed'))
            ->get();

        $this->info("Analyzing {$modules->count()} quiz/assessment modules...");

        $successCount = 0;
        foreach ($modules as $mod) {
            // If module has explicit stages or lecture questions
            $stages = [];
            if ($mod->preTestQuestions()->exists()) {
                $stages[] = 'pre_test';
            }
            if ($mod->postTestQuestions()->exists()) {
                $stages[] = 'post_test';
            }
            if (empty($stages)) {
                $stages[] = $mod->quiz_stage ?: null;
            }

            foreach ($stages as $stg) {
                try {
                    $res = $service->analyzeQuiz($mod->id, $stg, true);
                    if ($res['test']['n_examinees'] > 0) {
                        $this->line("  ✓ Module #{$mod->id}".($stg ? " ({$stg})" : '').": N={$res['test']['n_examinees']}, Mean p={$res['test']['mean_p']}, KR20={$res['test']['kr20']}");
                        $successCount++;
                    }
                } catch (\Throwable $e) {
                    $this->warn("  ⚠ Module #{$mod->id}: ".$e->getMessage());
                }
            }
        }

        $this->info("Item analysis run complete. Processed {$successCount} active quiz stage(s).");

        return 0;
    }

    private function displaySummary(array $result): void
    {
        $t = $result['test'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Examinees (N)', $t['n_examinees']],
                ['Items (k)', $t['n_items']],
                ['Mean Score', $t['mean_score']],
                ['Score Std Dev', $t['sd_score']],
                ['KR-20 Reliability', ($t['kr20'] ?? 'N/A').' ('.$t['kr20_interpretation'].')'],
                ['Mean Difficulty (p)', $t['mean_p'] ?? 'N/A'],
                ['Mean Discrimination (D)', $t['mean_d'] ?? 'N/A'],
                ['AI Label Accuracy', ($t['label_accuracy'] !== null ? $t['label_accuracy'].'%' : 'N/A')],
                ['Verdicts', json_encode($t['verdict_counts'])],
            ]
        );
    }
}
