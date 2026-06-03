<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class SeoCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $store = config('app.name', 'CleatSheat.pk');

        $categories = [
            [
                'slug' => 'football-shoes',
                'name' => 'Football Shoes',
                'parent_id' => null,
                'sort_order' => 10,
                'meta_title' => "Football Shoes in Pakistan | Buy Online at {$store}",
                'meta_description' => 'Buy football shoes & boots online in Pakistan — FG, SG, AG & turf with UK/EU sizing, inspected condition, COD and fast delivery nationwide.',
                'intro_html' => '<p>Master any surface with <strong>football shoes</strong> built for Pakistani pitches — firm ground, soft ground, artificial grass, and turf. Every pair lists UK/EU sizes, honest condition, and surface-matched studs. <a href="/shop">Shop all boots</a> or pick a surface below.</p>',
            ],
            [
                'slug' => 'football-cleats',
                'name' => 'Football Cleats',
                'parent_id' => null,
                'sort_order' => 11,
                'meta_title' => "Football Cleats in Pakistan | Buy Online at {$store}",
                'meta_description' => 'Shop football cleats in Pakistan — FG, SG, AG & turf studs with UK/EU/PK sizing, WhatsApp fit help, COD and nationwide delivery.',
                'intro_html' => '<p>Find the right <strong>football cleats</strong> for your league — moulded FG studs, metal SG, AG for 3G turf, and TF for daily cages. Browse our <a href="/c/football-shoes">football shoes</a> collection or filter by surface on <a href="/shop">shop all</a>.</p>',
            ],
            [
                'slug' => 'grippers',
                'name' => 'Grippers',
                'parent_id' => null,
                'sort_order' => 20,
                'meta_title' => "Football Grippers in Pakistan | Buy Online at {$store}",
                'meta_description' => 'Buy football grippers & anti-slip grip socks in Pakistan — better lock-in inside your boots. COD and fast delivery.',
                'intro_html' => '<p><strong>Grippers</strong> and grip socks stop your foot sliding inside the boot on hot turf and dusty courts. Pair with our <a href="/c/football-socks">football socks</a> or browse <a href="/c/accessories">accessories</a> for match-day essentials.</p>',
            ],
            [
                'slug' => 'football-socks',
                'name' => 'Football Socks',
                'parent_id' => null,
                'sort_order' => 21,
                'meta_title' => "Football Socks in Pakistan | Grip Socks & More | {$store}",
                'meta_description' => 'Shop football socks in Pakistan — grip socks, long match socks, and anti-slip pairs for FG, AG & turf. COD nationwide.',
                'intro_html' => '<p>Comfort starts with the right <strong>football socks</strong> — breathable match socks, anti-slip grip socks, and grippers for lock-in. See also <a href="/c/grippers">grippers</a> and <a href="/c/accessories">accessories</a>.</p>',
            ],
            [
                'slug' => 'accessories',
                'name' => 'Football Accessories',
                'parent_id' => null,
                'sort_order' => 22,
                'meta_title' => "Football Accessories in Pakistan | {$store}",
                'meta_description' => 'Football accessories in Pakistan — socks, grippers, laces, insoles, bags & care kits. COD and fast delivery.',
                'intro_html' => '<p>Complete your kit with <strong>football accessories</strong> — laces, insoles, boot bags, and care products alongside <a href="/c/football-socks">socks</a> and <a href="/c/grippers">grippers</a>.</p>',
            ],
        ];

        foreach ($categories as $row) {
            Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true]),
            );
        }

        // Ensure surface children stay under football-shoes when that parent exists.
        $parent = Category::query()->where('slug', 'football-shoes')->first();
        if ($parent) {
            foreach (FootballShoesDemoSeeder::SURFACE_CATEGORIES as $i => $surface) {
                Category::query()->where('slug', $surface['slug'])->update([
                    'parent_id' => $parent->id,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }
    }
}
