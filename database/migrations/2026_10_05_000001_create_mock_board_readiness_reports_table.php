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
        Schema::create('mock_board_readiness_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_board_id')->constrained()->cascadeOnDelete();
            $table->decimal('readiness_percentage', 5, 2)->default(0);
            $table->string('tier', 20)->default('low'); // 'high', 'moderate', 'low'
            $table->string('tier_label', 100)->nullable();
            $table->decimal('gap_percentage', 5, 2)->default(0);
            $table->integer('gap_items')->default(0);
            $table->decimal('pre_test_score', 5, 2)->nullable();
            $table->decimal('post_test_score', 5, 2)->nullable();
            $table->decimal('improvement_percentage', 5, 2)->nullable();
            $table->string('consistency_status', 50)->nullable();
            $table->json('domain_breakdown')->nullable();
            $table->json('item_insights')->nullable();
            $table->json('peer_benchmark')->nullable();
            $table->json('historical_comparison')->nullable();
            $table->json('ai_action_plan')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mock_board_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_board_readiness_reports');
    }
};
