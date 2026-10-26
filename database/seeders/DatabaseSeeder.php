<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de base (todas as instalações): permissões, perfis, definições e regras de avaliação.
     * Dados de demonstração: só quando APP_ENV=local (ver DemoDataSeeder).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            CreditAssessmentRuleSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
