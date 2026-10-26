<?php

namespace Database\Seeders;

use App\Models\CreditAssessmentRuleSet;
use App\Services\Finance\CreditAssessmentEngine;
use Illuminate\Database\Seeder;

class CreditAssessmentRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (CreditAssessmentRuleSet::exists()) {
            return;
        }

        // Proposta inicial NÃO validada: só entra em vigor após validação por um gestor.
        // Até lá, o motor usa os mesmos valores de referência (versão 0).
        CreditAssessmentRuleSet::create([
            'version' => 1,
            'name' => 'Parâmetros de referência (a calibrar e validar pela instituição)',
            'rules' => CreditAssessmentEngine::DEFAULT_RULES,
            'is_active' => false,
            'notes' => 'Valores iniciais do sistema. Não constituem um modelo de risco validado.',
        ]);
    }
}
