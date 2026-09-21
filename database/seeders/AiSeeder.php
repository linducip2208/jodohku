<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiSeeder extends Seeder
{
    public function run(): void
    {
        $openai = AiProvider::firstOrCreate(['code' => 'openai'], ['name' => 'OpenAI', 'base_url' => 'https://api.openai.com/v1', 'is_active' => true]);
        $compat = AiProvider::firstOrCreate(['code' => 'compatible'], ['name' => 'OpenAI-Compatible', 'base_url' => 'https://api.openai.com/v1', 'is_active' => true]);

        AiModel::firstOrCreate(['code' => 'gpt-4o-mini'], [
            'ai_provider_id' => $openai->id, 'name' => 'GPT-4o mini',
            'cost_per_1k_input' => 0.00015, 'cost_per_1k_output' => 0.0006,
            'max_tokens' => 128000, 'is_active' => true, 'is_default' => true,
        ]);
        AiModel::firstOrCreate(['code' => 'compat-default'], [
            'ai_provider_id' => $compat->id, 'name' => 'Compatible Default',
            'cost_per_1k_input' => 0, 'cost_per_1k_output' => 0,
            'max_tokens' => 8192, 'is_active' => true, 'is_default' => false,
        ]);
    }
}
