<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 39,
                'articles_per_month' => 8,
                'sites_limit' => 1,
                'features' => [
                    '8 articles/mois',
                    '1 site',
                    'Support email',
                    'Analytics de base',
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'price' => 99,
                'articles_per_month' => 30,
                'sites_limit' => 3,
                'features' => [
                    '30 articles/mois',
                    '3 sites',
                    'Support prioritaire',
                    'Analytics avancés',
                    'Voix de marque personnalisée',
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'price' => 249,
                'articles_per_month' => 100,
                'sites_limit' => -1,
                'features' => [
                    '100 articles/mois',
                    'Sites illimités',
                    'Support dédié',
                    'API access',
                    'Intégrations personnalisées',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
