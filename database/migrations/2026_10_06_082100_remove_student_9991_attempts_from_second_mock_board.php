<?php

use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardReadinessReport;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptSnapshot;
use App\Models\User;
use App\Services\MockBoardStatisticsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to remove student 23-9991's pre-seeded attempts so they can take the second mock board manually.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mock_boards') || ! Schema::hasTable('users')) {
            return;
        }

        DB::transaction(function () {
            $student = User::where('idnumber', '23-9991')->first();
            $board = MockBoard::where('title', '2026 CPALE Pre-Board Diagnostic & Simulated Examination')->first();

            if (! $student || ! $board) {
                return;
            }

            $phaseModuleIds = $board->phases()->pluck('module_id')->filter()->values()->toArray();

            $quizAttempts = QuizAttempt::where('user_id', $student->id)
                ->where(function ($q) use ($board, $phaseModuleIds) {
                    $q->where('mock_board_id', $board->id)
                        ->orWhereIn('module_id', $phaseModuleIds);
                })
                ->get();

            $attemptIds = $quizAttempts->pluck('id')->toArray();

            if (! empty($attemptIds)) {
                QuizAnswer::whereIn('attempt_id', $attemptIds)->delete();
                QuizAttempt::whereIn('id', $attemptIds)->delete();
            }

            if (Schema::hasTable('quiz_attempt_snapshots')) {
                QuizAttemptSnapshot::where('user_id', $student->id)
                    ->where('mock_board_id', $board->id)
                    ->delete();
            }

            MockBoardAttempt::where('user_id', $student->id)
                ->where('mock_board_id', $board->id)
                ->delete();

            MockBoardReadinessReport::where('user_id', $student->id)
                ->where('mock_board_id', $board->id)
                ->delete();

            // Re-compute class statistics with remaining 9 students
            try {
                app(MockBoardStatisticsService::class)->computeClassStatistics($board);
            } catch (Throwable $e) {
                // Ignore if computed dynamically
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: manual answers will not be reverted on rollback
    }
};
