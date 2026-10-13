<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('question_id')->index(); // No FK cascade to preserve historical question analysis
            $table->string('quiz_stage')->nullable();
            $table->text('question_text');
            $table->string('correct_option', 10);
            $table->string('ai_difficulty_label')->nullable();
            $table->unsignedInteger('n_examinees')->default(0);
            $table->unsignedInteger('n_correct')->default(0);
            $table->decimal('p_index', 5, 4)->nullable();
            $table->decimal('d_index', 5, 4)->nullable();
            $table->decimal('point_biserial', 5, 4)->nullable();
            $table->json('option_stats')->nullable();
            $table->decimal('distractor_efficiency', 5, 2)->nullable();
            $table->string('difficulty_interpretation')->nullable();
            $table->string('discrimination_interpretation')->nullable();
            $table->string('empirical_band')->nullable();
            $table->boolean('label_matches')->nullable();
            $table->string('verdict')->default('insufficient');
            $table->json('flags')->nullable();
            $table->string('confidence')->default('insufficient');
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['module_id', 'quiz_stage', 'analyzed_at']);
        });

        Schema::create('test_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('quiz_stage')->nullable();
            $table->unsignedInteger('n_examinees')->default(0);
            $table->unsignedInteger('n_items')->default(0);
            $table->decimal('mean_score', 8, 2)->default(0);
            $table->decimal('sd_score', 8, 2)->default(0);
            $table->decimal('score_variance', 10, 4)->nullable();
            $table->decimal('kr20', 5, 4)->nullable();
            $table->string('kr20_interpretation')->nullable();
            $table->decimal('mean_p', 5, 4)->nullable();
            $table->decimal('mean_d', 5, 4)->nullable();
            $table->decimal('label_accuracy', 5, 2)->nullable();
            $table->json('mean_p_by_label')->nullable();
            $table->json('verdict_counts')->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['module_id', 'quiz_stage', 'analyzed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_analysis_results');
        Schema::dropIfExists('item_analysis_results');
    }
};
