<?php

namespace Database\Seeders;

use App\Models\ContentPost;
use Illuminate\Database\Seeder;

class JournalBlogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $post) {
            ContentPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function posts(): array
    {
        $published = now()->subDays(2);

        return [
            [
                'slug' => 'pre-loved-shoes-pakistan-buying-guide',
                'title' => 'Pre-Loved Shoes in Pakistan: What to Check Before You Buy',
                'meta_title' => 'Pre-Loved Shoes Pakistan — Inspection & Buying Guide',
                'meta_description' => 'How to buy second-hand and pre-loved shoes in Pakistan: hygiene, sizing, sole wear, authenticity, and fair PKR pricing.',
                'excerpt' => 'Pre-loved boots can be brilliant value in Lahore, Karachi, and Islamabad — if you know what to inspect before paying.',
                'pillar_keyword' => 'pre-loved shoes Pakistan',
                'is_published' => true,
                'published_at' => $published->copy()->subDays(5),
                'body' => $this->img('pre-loved-shoes-pakistan', 'Pre-loved football boots laid out for inspection')
                    .$this->p('Second-hand and <strong>pre-loved shoes</strong> are huge in Pakistan — academy players, Sunday leagues, and students all hunt deals on Instagram, OLX, and trusted online shops. The trick is not finding a low price; it is finding a pair that still protects your feet and performs on your surface.')
                    .$this->h2('Why pre-loved makes sense here')
                    .$this->p('New international boots often land between <strong>PKR 18,000–45,000+</strong> depending on tier. A clean pre-loved pair from a known model line can sit at <strong>40–65% of retail</strong> if studs and upper are honest. That gap matters when you outgrow sizes every season or rotate between turf and futsal.')
                    .$this->h2('Inspection checklist (5 minutes)')
                    .$this->ul([
                        '<strong>Inside lining & insole:</strong> lift the insole if possible. Mild wear is fine; crusty glue, strong odour, or torn heel counters are walk-away signs.',
                        '<strong>Midsole & plate:</strong> press the heel and forefoot. If you feel collapse or uneven softness, cushioning is spent.',
                        '<strong>Outsole & studs:</strong> FG studs should not be filed to nubs. Turf patterns should still have depth — bald rubber slips on dusty courts.',
                        '<strong>Upper:</strong> check toe box separation, ripped knit threads, and stretched eyelets. Leather can crease; it should not peel.',
                        '<strong>Size label:</strong> match UK/EU/US print with seller listing. Pakistan listings often mix EU on box and UK on tongue.',
                        '<strong>Authenticity:</strong> compare SKU font, stitch density, and weight to official photos. If the price is “too Nike for PKR 3,000”, trust your doubt.',
                    ])
                    .$this->h2('Hygiene & freshening')
                    .$this->p('UV dry time, antibacterial spray, and a replaced insole turn most acceptable pairs into match-day ready. Avoid soaking knit uppers — heat and water warp shape. For club resale, disclose cleaning steps; buyers in Pakistan appreciate transparency.')
                    .$this->h2('Where to buy with less risk')
                    .$this->p('Prefer sellers who show <strong>sole photos, size tag close-ups, and 10-second flex videos</strong>. Local shops that grade A/B/C condition beat anonymous DMs. Online stores with return windows for sizing swaps reduce gamble — browse <a href="/shop">football boots in our shop</a> alongside your pre-loved hunt so you know current retail benchmarks.')
                    .$this->blockquote('Rule of thumb: if you would not loan the pair to a teammate, do not list it as “excellent”.')
                    .$this->h2('Fair PKR ranges (pre-loved, 2026 ballpark)')
                    .$this->p('Entry tier replicas or older models: <strong>PKR 2,500–6,000</strong>. Mid-tier used name brands: <strong>PKR 7,000–14,000</strong>. Top-tier pro models with &gt;70% life left: <strong>PKR 15,000–28,000</strong>. Adjust down for heavy turf use or missing bag.')
                    .$this->p('Pre-loved is worth it when structure is sound and size is certain. When either is fuzzy, a discounted new pair from a reputable seller often costs less per hour played.'),
            ],
            [
                'slug' => 'shoe-size-guide-pakistan-uk-eu-us',
                'title' => 'Shoe Size Guide for Pakistan: UK, EU & US Conversions',
                'meta_title' => 'Pakistan Shoe Size Guide — UK, EU, US Football Boots',
                'meta_description' => 'Convert UK, EU, and US football shoe sizes for Pakistan shoppers. Fit tips for wide feet, half sizes, and ordering online.',
                'excerpt' => 'Pakistan size tags rarely agree. Use this UK / EU / US chart and fit rules before you checkout online.',
                'pillar_keyword' => 'shoe size guide Pakistan',
                'is_published' => true,
                'published_at' => $published->copy()->subDays(4),
                'body' => $this->img('shoe-size-guide-pakistan', 'Measuring foot length against a size chart')
                    .$this->p('Ordering boots online in Pakistan fails most often on <strong>size</strong>, not payment. Boxes print EU, tongues show UK, and US sizes sneak into marketplace titles. Measure once, convert twice, then compare to the brand chart — not a random Facebook comment.')
                    .$this->h2('Measure your foot (cm)')
                    .$this->ol([
                        'Stand on paper against a wall, heel touching the wall.',
                        'Mark longest toe. Measure heel-to-toe in <strong>centimetres</strong>.',
                        'Measure both feet; buy for the <strong>longer</strong> foot.',
                        'Add <strong>0.5–1 cm</strong> for football socks if between sizes.',
                    ])
                    .$this->h2('Men’s football boot conversion (approximate)')
                    .$this->table(
                        ['CM (foot)', 'UK', 'EU', 'US men'],
                        [
                            ['24.5', '6', '39', '7'],
                            ['25.0', '6.5', '40', '7.5'],
                            ['25.5', '7', '40.5', '8'],
                            ['26.0', '7.5', '41', '8.5'],
                            ['26.5', '8', '42', '9'],
                            ['27.0', '8.5', '42.5', '9.5'],
                            ['27.5', '9', '43', '10'],
                            ['28.0', '9.5', '44', '10.5'],
                            ['28.5', '10', '44.5', '11'],
                            ['29.0', '10.5', '45', '11.5'],
                            ['29.5', '11', '46', '12'],
                        ],
                    )
                    .$this->p('<em>Brands differ: Nike often runs snug; some adidas silhouettes run long. Always open the brand size chart for the exact SKU.</em>')
                    .$this->h2('Pakistan-specific fit notes')
                    .$this->ul([
                        '<strong>Wide forefoot:</strong> look for “wide” editions or leather uppers that stretch; tight knit causes numb toes on hot days.',
                        '<strong>Half sizes:</strong> scarce in local stock — order up with thicker socks or down with thin socks only if toe space still allows sprint push-off.',
                        '<strong>Kids:</strong> leave thumb-width at toes; do not “size up two” — sloppy boots cause ankle rolls on turf.',
                        '<strong>Women’s unisex:</strong> US women ≈ US men +1.5 rule is rough; use CM instead.',
                    ])
                    .$this->h2('When to size up or down')
                    .$this->p('Size <strong>up half</strong> if you play long sessions on hot turf and toes bruise. Size <strong>down</strong> only when heel lift is visible in your true CM length — never down just because a seller has no stock.')
                    .$this->p('Still unsure? Compare product size notes on our <a href="/shop">shop page</a> or message with your CM measurement — faster than exchanging across cities.'),
            ],
            [
                'slug' => 'bulk-shoes-pakistan-teams-resellers',
                'title' => 'Bulk Shoes in Pakistan: Guide for Teams, Academies & Resellers',
                'meta_title' => 'Bulk Shoes Pakistan — Wholesale & Team Orders',
                'meta_description' => 'How to buy bulk football shoes in Pakistan: MOQs, mixed sizes, invoicing, delivery to academies, and PKR pricing bands for resellers.',
                'excerpt' => 'Academies and resellers save when they plan sizes, surfaces, and invoices upfront — not when they chase random lots.',
                'pillar_keyword' => 'bulk shoes Pakistan',
                'is_published' => true,
                'published_at' => $published->copy()->subDays(3),
                'body' => $this->img('bulk-shoes-pakistan', 'Cartons of football shoes for team bulk order')
                    .$this->p('<strong>Bulk shoes in Pakistan</strong> usually means ten pairs for a school squad, fifty for a city academy, or a reseller lot mixed by size and surface. Price per pair drops when the supplier can pick from open cartons without hunting rare colourways.')
                    .$this->h2('Who bulk buying suits')
                    .$this->ul([
                        '<strong>Football academies</strong> — standardise TF boots for beginners, FG for U17+ on grass.',
                        '<strong>School sports programs</strong> — gendered sizing runs with spare UK 7–9 for growth.',
                        '<strong>Corporate leagues</strong> — one model, many sizes, fast delivery before Ramadan tournaments.',
                        '<strong>Resellers</strong> — focus on 3–4 hero SKUs locals already search by name.',
                    ])
                    .$this->h2('What to specify on your RFQ')
                    .$this->p('Send: surface split (turf vs grass %), size run table, brand tier (entry/mid/pro), deadline city, and whether you need <strong>GST invoice</strong> or cash memo. Vague “50 jora chahiye” quotes come back unusable.')
                    .$this->h2('Typical MOQ & PKR bands (indicative)')
                    .$this->ul([
                        '<strong>Mini bulk (6–11 pairs):</strong> 5–8% off retail — good for one team bench.',
                        '<strong>Standard bulk (12–47 pairs):</strong> 10–18% off — common academy season order.',
                        '<strong>Pallet / importer lots (48+):</strong> 20–35% off — needs storage and size discipline.',
                    ])
                    .$this->p('Entry TF trainers bulk landed often <strong>PKR 4,500–7,500/pair</strong>; mid FG <strong>PKR 9,000–16,000</strong>; pro tiers higher. Freight to secondary cities (Peshawar, Quetta, Multan) add PKR 150–400/pair unless you consolidate on one airway bill.')
                    .$this->h2('Logistics inside Pakistan')
                    .$this->p('Use courier with <strong>box count photos</strong> at dispatch. Split shipments by size to avoid one lost carton freezing an entire tournament. For monsoon season, plastic wrap inside carton — damp boots mould in Lahore warehouses.')
                    .$this->h2('Quality control on delivery')
                    .$this->p('Open <strong>one random pair per size line</strong> before signing. Check stud mould, size stickers, and left/right matching. Document defects within 24–48 hours — local wholesale norms expect fast claims.')
                    .$this->p('Planning a squad order? Start from current stock on <a href="/shop">our shop</a> to anchor models, then message for bulk pricing on matching lines.'),
            ],
            [
                'slug' => 'types-of-football-shoes-fg-ag-tf-ic',
                'title' => 'Types of Football Shoes: FG, SG, AG, TF & IC Explained',
                'meta_title' => 'Types of Football Shoes — FG, AG, TF, IC Guide',
                'meta_description' => 'Learn football boot types: firm ground (FG), soft ground (SG), artificial grass (AG), turf (TF), and indoor (IC). Pick the right sole for Pakistan pitches.',
                'excerpt' => 'Wrong studs waste money and risk knees. Match your boot type to the surface you actually play on in Pakistan.',
                'pillar_keyword' => 'types of football shoes',
                'is_published' => true,
                'published_at' => $published->copy()->subDays(2),
                'body' => $this->img('types-of-football-shoes', 'Different football boot sole plates: FG, TF, and IC')
                    .$this->p('Pakistan football happens on <strong>natural grass, dusty hard ground, 3G turf, concrete futsal, and school mats</strong>. Each surface needs a different outsole. Buying “FG because pros wear them” on daily turf is the most common expensive mistake.')
                    .$this->h2('Firm Ground (FG)')
                    .$this->p('Moulded studs for dry to slightly soft natural grass. Ideal for well-kept grounds in Islamabad or early-morning Lahore parks. On hard baked soil, FG feels slippery — players shorten stride and complain of heel pain.')
                    .$this->h2('Soft Ground (SG)')
                    .$this->p('Replaceable metal studs for rain-soaked grass. Rarely needed in most Pakistani cities except monsoon weeks on real turf. Keep a few pairs for elite amateur leagues; overkill for street football.')
                    .$this->h2('Artificial Grass (AG)')
                    .$this->p('Shorter, more numerous studs tuned for 3G/4G turf. Best upgrade if you live on commercial turf cages in Karachi or Faisalabad. Safer knee torque than FG on rubber infill.')
                    .$this->h2('Turf / TF (astro turf & rough courts)')
                    .$this->p('Rubber bumps or small rubber studs. The <strong>default choice</strong> for school courts, community centres, and dusty “turf” franchises. Durability beats blades; less stud pressure on ankles.')
                    .$this->h2('Indoor / IC (futsal)')
                    .$this->p('Flat gum rubber for halls and smooth concrete. Never on grass — you slide; studs never engage. Essential for winter leagues in covered courts.')
                    .$this->h2('Quick picker for Pakistan')
                    .$this->ul([
                        'Daily turf cage → <strong>TF or AG</strong>',
                        'Real grass twice a week → <strong>FG</strong> (AG if turf training midweek)',
                        'Futsal only → <strong>IC</strong>',
                        'Monsoon grass pro match → <strong>SG</strong> spare pair',
                    ])
                    .$this->p('One boot cannot do all. If budget allows two pairs, buy <strong>TF + FG/AG</strong> before chasing flagship colourways. Filter by type on <a href="/shop">our shop</a> when browsing.'),
            ],
            [
                'slug' => 'best-football-boots-by-player-position',
                'title' => 'Best Football Boots by Player Position (Pakistan Guide)',
                'meta_title' => 'Best Football Boots by Position — GK, DEF, MID, FWD',
                'meta_description' => 'Position-based football boot advice for Pakistan: goalkeeper grip, defender stability, midfielder control, winger speed, and striker strike zones.',
                'excerpt' => 'Goalkeepers, defenders, midfielders, and forwards stress boots differently — here is how to match features to your role.',
                'pillar_keyword' => 'best football boots by position',
                'is_published' => true,
                'published_at' => $published->copy()->subDay(),
                'body' => $this->img('football-boots-by-position', 'Football players in different positions wearing boots')
                    .$this->p('Marketing pushes one “hero” boot for everyone. On Pakistani turf, your <strong>position</strong> changes what matters: traction for keepers, stability for centre-backs, touch for tens, lightness for wingers.')
                    .$this->h2('Goalkeepers (GK)')
                    .$this->ul([
                        '<strong>Priorities:</strong> grip on dry turf, toe protection, secure midfoot lock.',
                        '<strong>Sole:</strong> TF or AG for most training; slightly higher stud density helps push-off dives.',
                        '<strong>Upper:</strong> textured zones for punching; avoid paper-thin knit if you face hard shots daily.',
                    ])
                    .$this->h2('Centre-backs & full-backs (DEF)')
                    .$this->ul([
                        '<strong>Priorities:</strong> ankle stability, durable outsole, reliable pass under pressure.',
                        '<strong>Sole:</strong> TF/AG for cage-heavy schedules; FG if you mostly play grass.',
                        '<strong>Upper:</strong> synthetic leather or reinforced knit — tackles scrape toes.',
                    ])
                    .$this->h2('Defensive & central midfielders (MID)')
                    .$this->ul([
                        '<strong>Priorities:</strong> first-touch control, 90-minute comfort, balanced weight.',
                        '<strong>Sole:</strong> AG/TF for quick pivots on commercial turf.',
                        '<strong>Upper:</strong> control pads on forefoot; slightly roomier toe if you ping long diagonals.',
                    ])
                    .$this->h2('Wingers & attacking mids (W / AM)')
                    .$this->ul([
                        '<strong>Priorities:</strong> low weight, explosive toe spring, confident cuts.',
                        '<strong>Sole:</strong> responsive TF or AG; avoid chunky entry-level plates.',
                        '<strong>Upper:</strong> snug fit — but not painful on 38°C evening kickoffs.',
                    ])
                    .$this->h2('Strikers (ST / CF)')
                    .$this->ul([
                        '<strong>Priorities:</strong> strike zone padding, clean instep for finishing, secure heel for one-touch turns.',
                        '<strong>Sole:</strong> same as wingers; surface matters more than colour.',
                        '<strong>Upper:</strong> textured strike panels; some prefer classic leather for chip shots.',
                    ])
                    .$this->h2('Budget reality in Pakistan')
                    .$this->p('Pros rotate boots; amateurs should invest in <strong>correct sole type</strong> first, then tier. A PKR 9,000 TF in the right size beats a PKR 25,000 FG on the wrong pitch.')
                    .$this->p('Browse position-friendly stock — lightweight lines for forwards, padded tiers for keepers — on <a href="/shop">our shop</a>.'),
            ],
            [
                'slug' => 'football-shoe-prices-pakistan-new-vs-pre-loved',
                'title' => 'Football Shoe Prices in Pakistan: New vs Pre-Loved — What’s Worth It?',
                'meta_title' => 'Football Shoe Prices Pakistan 2026 — New vs Second Hand',
                'meta_description' => '2026 football boot price ranges in PKR for Pakistan: entry, mid, pro tiers. Compare new vs second-hand value and when each is worth buying.',
                'excerpt' => 'From PKR 4,000 trainers to pro-tier boots — see real 2026 price bands and when pre-loved beats box-fresh.',
                'pillar_keyword' => 'football shoe prices Pakistan',
                'is_published' => true,
                'published_at' => $published,
                'body' => $this->img('football-shoe-prices-pakistan', 'New and pre-loved football boots with price tags in PKR')
                    .$this->p('Prices swing with dollar rate, import batch, and season drops. This guide gives <strong>realistic PKR bands</strong> for Pakistan in 2026 so you can compare Instagram deals, mall racks, and online checkout without guesswork.')
                    .$this->h2('New football boot price tiers (PKR, indicative)')
                    .$this->table(
                        ['Tier', 'What you get', 'Typical PKR range'],
                        [
                            ['Entry / local TF', 'Rubber turf soles, academy use', '4,000 – 8,500'],
                            ['Mid / global brand', 'FG or TF, last season colour', '9,000 – 18,000'],
                            ['Performance', 'Current season silos, AG/FG', '18,000 – 32,000'],
                            ['Pro / elite', 'Top athlete lines, limited packs', '32,000 – 55,000+'],
                        ],
                    )
                    .$this->p('Sales during Eid, 11.11, and end-of-season clearances can shave <strong>15–30%</strong> if size is in stock. Flagship launch weeks rarely discount — wait six weeks if you are not sponsored.')
                    .$this->h2('Pre-loved / second-hand bands')
                    .$this->ul([
                        '<strong>Light use (indoor only):</strong> often 50–65% of new ask.',
                        '<strong>One season turf:</strong> 35–50% if studs and upper honest.',
                        '<strong>Heavy grind / unknown history:</strong> cap at entry-tier new price — no bargain if plate is dead.',
                    ])
                    .$this->h2('Buying new: when it is worth it')
                    .$this->ul([
                        'You need a <strong>half size</strong> hard to find used.',
                        'You play <strong>3+ times weekly</strong> — lifespan math favours fresh cushioning.',
                        'You want <strong>warranty / exchange</strong> from a registered seller.',
                        'Hygiene matters for growing kids — new insole path is simpler.',
                    ])
                    .$this->h2('Buying pre-loved: when it is worth it')
                    .$this->ul([
                        'Pro-tier boot <strong>one size up</strong> you cannot afford new.',
                        'Backup pair for rain or second surface (TF vs FG).',
                        'You tried the exact model in-store and only need cheaper unit.',
                        'Seller provides <strong>sole video + size tag</strong> and accepts return if misdescribed.',
                    ])
                    .$this->h2('Side-by-side value scorecard')
                    .$this->table(
                        ['Factor', 'New', 'Pre-loved'],
                        [
                            ['Upfront PKR', 'Higher', 'Lower'],
                            ['Known history', 'Yes', 'Sometimes'],
                            ['Hygiene', 'Best', 'Needs work'],
                            ['Latest tech', 'Yes', 'Often last year'],
                            ['Resale if you outgrow', 'Easier with receipt', 'Harder'],
                        ],
                    )
                    .$this->h2('Verdict for Pakistan players')
                    .$this->p('<strong>New</strong> wins for primary match boots, kids, and weekly grinders. <strong>Pre-loved</strong> wins for trial models, backup pairs, and pro tiers you will replace in a year anyway. Never buy used without sole photos; never buy new without knowing your CM length — see our <a href="/journal/shoe-size-guide-pakistan-uk-eu-us">size guide</a>.')
                    .$this->p('Compare today’s listed prices on <a href="/shop">our shop</a> against any second-hand quote — if gap is under 25% and condition is unknown, new is usually the smarter play.'),
            ],
        ];
    }

    private function img(string $basename, string $alt): string
    {
        $src = '/images/journal/'.$basename.'.svg';

        return '<figure class="journal-figure">'
            .'<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($alt, ENT_QUOTES, 'UTF-8').'" loading="lazy" style="width:100%;height:auto;" />'
            .'<figcaption style="margin-top:0.5rem;font-size:0.875rem;color:#6b7280;">Sample image — replace with <code>/images/journal/'.htmlspecialchars($basename, ENT_QUOTES, 'UTF-8').'.jpg</code></figcaption>'
            .'</figure>';
    }

    private function p(string $html): string
    {
        return '<p>'.$html.'</p>';
    }

    private function h2(string $text): string
    {
        return '<h2>'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</h2>';
    }

    private function ul(array $items): string
    {
        $lis = implode('', array_map(fn (string $item) => '<li>'.$item.'</li>', $items));

        return '<ul>'.$lis.'</ul>';
    }

    private function ol(array $items): string
    {
        $lis = implode('', array_map(fn (string $item) => '<li>'.$item.'</li>', $items));

        return '<ol>'.$lis.'</ol>';
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function table(array $headers, array $rows): string
    {
        $th = implode('', array_map(
            fn (string $h) => '<th style="text-align:left;padding:0.5rem 0.75rem;border-bottom:2px solid #e5e7eb;">'.htmlspecialchars($h, ENT_QUOTES, 'UTF-8').'</th>',
            $headers,
        ));
        $body = '';
        foreach ($rows as $row) {
            $td = implode('', array_map(
                fn (string $cell) => '<td style="padding:0.5rem 0.75rem;border-bottom:1px solid #e5e7eb;">'.htmlspecialchars($cell, ENT_QUOTES, 'UTF-8').'</td>',
                $row,
            ));
            $body .= '<tr>'.$td.'</tr>';
        }

        return '<div style="overflow-x:auto;margin-top:1rem;"><table style="width:100%;border-collapse:collapse;font-size:0.9375rem;">'
            .'<thead><tr>'.$th.'</tr></thead><tbody>'.$body.'</tbody></table></div>';
    }

    private function blockquote(string $text): string
    {
        return '<blockquote><p>'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</p></blockquote>';
    }
}
