<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Guérison',           'slug' => 'guerison',          'icon' => '🙏', 'color' => '#10B981', 'display_order' => 1],
            ['name' => 'Délivrance',          'slug' => 'delivrance',        'icon' => '⛓️', 'color' => '#8B5CF6', 'display_order' => 2],
            ['name' => 'Conversion',          'slug' => 'conversion',        'icon' => '🔄', 'color' => '#F59E0B', 'display_order' => 3],
            ['name' => 'Salut',               'slug' => 'salut',             'icon' => '✝️', 'color' => '#EF4444', 'display_order' => 4],
            ['name' => 'Mariage',             'slug' => 'mariage',           'icon' => '💍', 'color' => '#EC4899', 'display_order' => 5],
            ['name' => 'Famille',             'slug' => 'famille',           'icon' => '👨‍👩‍👧', 'color' => '#3B82F6', 'display_order' => 6],
            ['name' => 'Finances',            'slug' => 'finances',          'icon' => '💰', 'color' => '#059669', 'display_order' => 7],
            ['name' => 'Miracles',            'slug' => 'miracles',          'icon' => '✨', 'color' => '#6366F1', 'display_order' => 8],
            ['name' => 'Protection Divine',   'slug' => 'protection_divine', 'icon' => '🛡️', 'color' => '#0EA5E9', 'display_order' => 9],
            ['name' => 'Ministère',           'slug' => 'ministere',         'icon' => '⛪', 'color' => '#7C3AED', 'display_order' => 10],
            ['name' => 'Emploi & Carrière',   'slug' => 'emploi',            'icon' => '💼', 'color' => '#14B8A6', 'display_order' => 11],
            ['name' => 'Études',              'slug' => 'etudes',            'icon' => '📚', 'color' => '#F97316', 'display_order' => 12],
            ['name' => 'Autre',               'slug' => 'autre',             'icon' => '🌟', 'color' => '#6B7280', 'display_order' => 13],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'name' => $category['name'],
                'slug' => $category['slug'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'display_order' => $category['display_order'],
                'testimony_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
