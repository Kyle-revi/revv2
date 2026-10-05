<?php

use App\Services\AiSettingsResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $now = now();
        $resolver = app(AiSettingsResolver::class);
        $defaults = (new ReflectionClass($resolver))->getConstant('GLOBAL_DEFAULTS');

        $updates = [
            'prompt.quiz_insights.system' => $defaults['prompt.quiz_insights.system'],
            'prompt.quiz_insights.user_template' => $defaults['prompt.quiz_insights.user_template'],
            'model.quiz_insights' => $defaults['model.quiz_insights'],
            'model.max_tokens' => $defaults['model.max_tokens'],
        ];

        foreach ($updates as $key => $value) {
            DB::table('ai_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]
            );
        }

        Cache::forget('ai_settings.global');
    }

    public function down(): void
    {
        // Preserves current settings on down
    }
};
