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
                'stripe_price_id_live' => "price_1SifRgAc2IYiD1ydcH432Eek",
                'stripe_price_id_test' => "price_1SifeQAQ01Zh1HCGVm6YTIXW",
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
                'stripe_price_id_live' => "price_1SifTuAc2IYiD1yd87OitiaI",
                'stripe_price_id_test' => "price_1Siff8AQ01Zh1HCGy5fAnoKY",
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
                'stripe_price_id_live' => "price_1SifUHAc2IYiD1ydEDqdNVXT",
                'stripe_price_id_test' => "price_1SiffWAQ01Zh1HCG3HLleuju",
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
