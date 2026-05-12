<?php

namespace App\Domain\Bargain;

final class DeterministicShopkeeperReply
{
    public static function welcome(string $customerName, string $productName, string $variantLabel, string $listPrice, ?string $seedMaterial = null): string
    {
        $name = trim($customerName);
        $hello = $name !== '' ? "Assalam‑o‑alaikum {$name}!" : 'Assalam‑o‑alaikum!';
        $seed = $seedMaterial ?? 'welcome:'.md5($hello.$productName);

        $variants = [
            "{$hello} {$productName} ({$variantLabel}) ki price PKR {$listPrice} hai.\n\nApna budget PKR me bata dein — seedha number bhi chalega.",
            "{$hello} Ye {$productName} ({$variantLabel}) listed PKR {$listPrice} hai.\n\nKitna budget hai? PKR likh dein.",
            "{$hello} {$productName} ({$variantLabel}) — PKR {$listPrice}.\n\nAap kitne tak comfortable hain? Number bhej dein.",
        ];

        return self::pick($variants, $seed.':welcome');
    }

    public static function askForOfferWithAmount(string $listPrice, ?string $seedMaterial = null, array $avoidSnippets = [], int $priorBudgetPrompts = 0): string
    {
        return self::askForOfferWithAmountVaried($listPrice, $seedMaterial ?? 'ask:'.$listPrice, $avoidSnippets, $priorBudgetPrompts);
    }

    /**
     * When budget was already requested, rotate to range/target wording.
     */
    public static function askForOfferWithAmountVaried(
        string $listPrice,
        string $seedMaterial,
        array $avoidSnippets,
        int $priorBudgetPrompts,
    ): string {
        if ($priorBudgetPrompts >= 1) {
            $variants = [
                'Aap kis range tak soch rahe hain? Seedha PKR likh dein.',
                'Best aap kitna kar sakte hain? Target PKR bata dein.',
                'Theek — apna max comfortable PKR number bhej dein, main dekh leta hun.',
                'Kya best ho sakta hai aapki side se? PKR amount type kar dein.',
            ];

            return self::pickAvoiding($variants, $seedMaterial.':ask_varied', $avoidSnippets);
        }

        $variants = [
            'Theek hai — apna budget PKR me bata dein.',
            'PKR amount likh dein, main adjust kar ke batata hun.',
            'Seedha PKR number bhej dein, scene clear ho jaye ga.',
        ];

        return self::pickAvoiding($variants, $seedMaterial.':ask', $avoidSnippets);
    }

    public static function askDiscountOrBestPrice(
        bool $askBest,
        string $listPrice,
        ?string $currentOffer,
        string $seedMaterial,
        array $avoidSnippets,
    ): string {
        $line = $currentOffer !== null && $currentOffer !== ''
            ? "Meri side se abhi PKR {$currentOffer} line hai."
            : 'Pehle aapka budget PKR me bata dein, phir best adjust kar ke batata hun.';

        if ($askBest) {
            $variants = [
                "Best yehi range me nikalta hun — {$line}\n\nApna target PKR likh dein, close karne ki koshish karunga.",
                "Best price negotiation se hi banta hai 😄 {$line}\n\nAap kitna tak le kar chal sakte hain? PKR number bhej dein.",
            ];
        } else {
            $variants = [
                "Discount scene list + stock pe depend karta hai. {$line}\n\nApna budget PKR me likh dein, main check kar ke batata hun.",
                "Thora adjust ho sakta hai lekin seedha number chahiye. {$line}\n\nPKR amount bata dein.",
            ];
        }

        return self::pickAvoiding($variants, $seedMaterial.':discount', $avoidSnippets);
    }

    public static function clarifyAcceptNeedOfferLine(
        ?string $currentOffer,
        string $listPrice,
        string $seedMaterial,
        array $avoidSnippets,
    ): string {
        $variants = $currentOffer !== null && $currentOffer !== ''
            ? [
                "Pehle meri last line PKR {$currentOffer} confirm kar lein — phir “done / theek” likh dein, main lock kar dun ga.",
                "Accept tab ho ga jab last shop line clear ho. Abhi PKR {$currentOffer} hai — agar yehi theek hai to “confirm / done” likh dein.",
            ]
            : [
                'Pehle ek PKR amount ya meri last offer confirm kar dein — phir “done / theek” likh dein, main lock laga dun ga.',
                'Abhi lock ke liye pehle numeric offer ya meri last line clear honi chahiye. PKR likh dein.',
            ];

        return self::pickAvoiding($variants, $seedMaterial.':clarify_accept', $avoidSnippets);
    }

    public static function intentGreetingShort(string $seedMaterial, array $avoidSnippets): string
    {
        $variants = [
            'Walaikum salam — batain, kis budget pe soch rahe hain?',
            'AoA — PKR amount likh dein jahan tak comfortable hain.',
            'Theek — seedha budget number bhej dein.',
        ];

        return self::pickAvoiding($variants, $seedMaterial.':hi', $avoidSnippets);
    }

    public static function intentConfusedClarify(string $listPrice, ?string $currentOffer, string $seedMaterial, array $avoidSnippets): string
    {
        $line = $currentOffer !== null && $currentOffer !== ''
            ? "Abhi meri line PKR {$currentOffer} hai."
            : "List PKR {$listPrice} hai.";

        $variants = [
            "Simple rakhte hain: aap apna budget PKR me likh dein. {$line}",
            "Koi masla nahi — seedha PKR number bata dein kitna dena chahte hain. {$line}",
        ];

        return self::pickAvoiding($variants, $seedMaterial.':confused', $avoidSnippets);
    }

    public static function intentRejectSoft(string $seedMaterial, array $avoidSnippets): string
    {
        $variants = [
            'Theek hai — agar mind change ho to PKR range bata dena.',
            'Koi issue nahi. Budget clear ho to message kar dena.',
        ];

        return self::pickAvoiding($variants, $seedMaterial.':reject_soft', $avoidSnippets);
    }

    public static function nudgeIncreaseFromLastOffer(string $lastOffer, ?string $seedMaterial = null, array $avoidSnippets = []): string
    {
        $seed = $seedMaterial ?? 'nudge:'.$lastOffer;
        $variants = [
            "Samajh gaya — aap ne last PKR {$lastOffer} bola tha.\n\nThora sa upar aa jayein, phir close ho jaye ga.",
            "PKR {$lastOffer} note kar liya.\n\nIs pair ke liye thori si flexibility kar dein, deal ban sakti hai.",
            "Last PKR {$lastOffer} tha na?\n\nChoti si increase kar dein, main bhi adjust kar lun ga.",
        ];

        return self::pickAvoiding($variants, $seed.':nudge', $avoidSnippets);
    }

    public static function counterTooLow(string $customerOffer, string $counterOffer, string $listPrice): string
    {
        return "PKR {$customerOffer} thora low hai.\n\n".
            "Best main PKR {$counterOffer} tak kar sakta hun.";
    }

    /**
     * Hold-firm: same shop PKR line, no numeric concession this turn (plateau / exhaustion).
     *
     * @param  list<string>  $avoidSnippets
     */
    public static function counterHoldFirm(
        string $shopLinePkr,
        string $customerOffer,
        string $listPrice,
        string $seedMaterial,
        array $avoidSnippets = [],
    ): string {
        $variants = [
            "PKR {$customerOffer} samajh gaya — lekin PKR {$shopLinePkr} se neeche abhi shop side move nahi ho sakta.\n\nList PKR {$listPrice} hai; isi line pe reh kar close karne ki koshish karte hain.",
            "Dekho PKR {$shopLinePkr} realistic anchor hai meri side se (list PKR {$listPrice}).\n\nPKR {$customerOffer} pe aur neeche nahi ja sakta abhi — thora flexible ho jayein.",
            "PKR {$customerOffer} repeat ho raha hai 😄 scene tight hai.\n\nPKR {$shopLinePkr} pe hold kar raha hun — yahi workable line hai.",
        ];

        return self::pickAvoiding($variants, $seedMaterial.':hold', $avoidSnippets);
    }

    /**
     * Defend current line after micro-push / guilt phrasing (no price change).
     *
     * @param  list<string>  $avoidSnippets
     */
    public static function defendHoldLine(
        string $shopLinePkr,
        string $listPrice,
        string $seedMaterial,
        array $avoidSnippets = [],
    ): string {
        $variants = [
            "PKR {$shopLinePkr} pe already stretch ho chuka hun — list PKR {$listPrice} ke hisaab se aur neeche realistic nahi.\n\nThora realistic angle se aa jayein.",
            "Samajh raha hun push chahiye — lekin PKR {$shopLinePkr} ke neeche margin scene tough ho jata hai (list PKR {$listPrice}).\n\nIsi line pe best try kar raha hun.",
            "PKR {$shopLinePkr} workable line hai; aur kam karna abhi possible nahi (list PKR {$listPrice}).\n\nClose agar ho to isi anchor pe soch lein.",
        ];

        return self::pickAvoiding($variants, $seedMaterial.':defend', $avoidSnippets);
    }

    public static function generateCounterReply(
        NegotiationState $state,
        ConversationSignals $signals,
        string $customerOffer,
        string $counterOffer,
        string $listPrice,
        string $seedMaterial,
        array $avoidSnippets = [],
    ): string {
        $rng = new SeededRng($seedMaterial);
        $stage = $state->negotiationStage;

        $pools = [];

        if ($signals->sameOfferStreakAtEnd >= 2) {
            $pools[] = [
                "Yar same PKR {$customerOffer} dubara — scene thora mushkil hai 😄\n\nPKR {$counterOffer} tak best de sakta hun.",
                "Dekho PKR {$customerOffer} pe phir aa gaye.\n\nPKR {$counterOffer} realistic line hai meri side se.",
                "Samajh raha hun PKR {$customerOffer} repeat ho raha hai.\n\nPKR {$counterOffer} pe aa jayein, warna stuck ho jaye ga.",
            ];
        }

        $pools[] = match ($stage) {
            NegotiationStage::Lowball => [
                "Yar PKR {$customerOffer} itna possible nahi hoga 😄\n\nPKR {$counterOffer} tak kar sakta hun.",
                "PKR {$customerOffer} scene tough hai is range me.\n\nPKR {$counterOffer} realistic hai.",
                "Itna neeche PKR {$customerOffer} se deal nahi ban paati.\n\nPKR {$counterOffer} consider kar lein.",
            ],
            NegotiationStage::Opening => [
                "PKR {$customerOffer} start thora neeche hai.\n\nPKR {$counterOffer} tak aa sakte hain.",
                "Samajh gaya PKR {$customerOffer}.\n\nPKR {$counterOffer} workable line hai meri side se.",
                "PKR {$customerOffer} se thora upar aayein.\n\nPKR {$counterOffer} pe discussion ban sakti hai.",
            ],
            NegotiationStage::Frustrated => [
                "Samajh gaya mood — lekin PKR {$customerOffer} se deal match nahi hoti.\n\nPKR {$counterOffer} workable hai.",
                "Bar bar same point pe phans jate hain.\n\nPKR {$counterOffer} pe move karein, warna stuck rahe ga.",
                "Thora sa realistic ho jayein — PKR {$counterOffer} tak main kar sakta hun.",
            ],
            NegotiationStage::NearClose => [
                "Aap almost wahan pohanch gaye hain.\n\nPKR {$counterOffer} pe lock kar lein?",
                "Close ho rahe hain — PKR {$counterOffer} pe aa jayein.",
                "Thora sa fasla reh gaya.\n\nPKR {$counterOffer} final side se.",
            ],
            NegotiationStage::FinalPush => [
                "Chalein PKR {$counterOffer} pe done karte hain 🤝\n\nAap close hain.",
                "Itna kar deta hun — PKR {$counterOffer}.\n\nIsi pe final kar dein agar theek ho.",
                "PKR {$counterOffer} — last realistic line.\n\nAap confirm kar dein.",
            ],
            NegotiationStage::Negotiating, NegotiationStage::Exploring => [
                "PKR {$customerOffer} thora low hai.\n\nBest main PKR {$counterOffer} tak kar sakta hun.",
                "PKR {$customerOffer} se neeche shop side tough hai.\n\nPKR {$counterOffer} pe aa sakte hain.",
                "Samajh gaya PKR {$customerOffer}.\n\nPKR {$counterOffer} tak best possible hai.",
            ],
            NegotiationStage::Accepted => [
                "PKR {$customerOffer} thora low hai.\n\nBest main PKR {$counterOffer} tak kar sakta hun.",
            ],
        };

        $flat = array_merge(...$pools);
        $flat = array_values(array_unique($flat));

        return self::pickAvoiding($flat, $seedMaterial.':counter', $avoidSnippets, $rng);
    }

    public static function acceptable(string $offer, ?string $seedMaterial = null, array $avoidSnippets = []): string
    {
        $seed = $seedMaterial ?? 'ok:'.$offer;
        $variants = [
            "Done 🤝 PKR {$offer} pe ho gaya.\n\nAccept dabao, checkout lock kar deta hun.",
            "Scene set hai 😄 PKR {$offer} final.\n\nAccept tap kar dein, main lock laga deta hun.",
            "PKR {$offer} theek hai.\n\nAccept kar dein, phir bag me add kar ke checkout — same phone se.",
        ];

        return self::pickAvoiding($variants, $seed, $avoidSnippets);
    }

    public static function acceptableNudged(string $stated, string $nudged, string $listPrice, ?string $seedMaterial = null, array $avoidSnippets = []): string
    {
        $seed = $seedMaterial ?? 'nudged:'.$stated.':'.$nudged;
        $variants = [
            "PKR {$stated} samajh gaya. Best main PKR {$nudged} tak kar sakta hun.\n\nListed PKR {$listPrice} hai — agar okay ho to accept kar dein.",
            "PKR {$stated} note kiya. Shop side PKR {$nudged} better rehta hai.\n\nList PKR {$listPrice} hai — accept kar ke lock karwa lein.",
            "PKR {$stated} pe aa gaye. Main PKR {$nudged} tak stretch kar sakta hun.\n\nPKR {$listPrice} list hai — confirm kar dein.",
        ];

        return self::pickAvoiding($variants, $seed, $avoidSnippets);
    }

    public static function decline(?string $seedMaterial = null, array $avoidSnippets = []): string
    {
        $seed = $seedMaterial ?? 'decline';
        $variants = [
            "Koi masla nahi.\n\nJab dil chahe dubara bargain start kar lena.",
            "Theek hai phir next time.\n\nJab ready hona, message kar dena.",
            "Chalo koi issue nahi.\n\nDubara try kar sakte hain jab mood ho.",
        ];

        return self::pickAvoiding($variants, $seed, $avoidSnippets);
    }

    /**
     * Draft for accept lock — must include PKR with two decimals for AI guard.
     */
    public static function acceptLockDraft(string $chosenPkr, string $seedMaterial, array $avoidSnippets = []): string
    {
        $variants = [
            "Done 🤝 PKR {$chosenPkr} pe lock ho gaya checkout ke liye. Time limited lock hai — jaldi bag me add kar ke same phone se checkout kar lein.",
            "Scene set 😄 PKR {$chosenPkr} lock kar diya. Lock expire ho sakta hai, isi number se order place kar dein jab ready hon.",
            "PKR {$chosenPkr} confirm. Lock lag gaya — time kam hai, add to bag + checkout same phone se kar dein.",
            "PKR {$chosenPkr} pe ho gaya. Lock time bound hai — cart me daal ke checkout finish kar dein, wohi phone jo yahan use kiya.",
            "Isi number se PKR {$chosenPkr} lock hai. Jaldi checkout kar lein, lock khatam ho sakta hai.",
        ];

        return self::pickAvoiding($variants, $seedMaterial.':accept', $avoidSnippets);
    }

    /**
     * @param  list<string>  $variants
     */
    private static function pick(array $variants, string $seedMaterial): string
    {
        if ($variants === []) {
            return '';
        }
        $rng = new SeededRng($seedMaterial);
        $idx = (int) floor($rng->float01() * count($variants));

        return $variants[$idx];
    }

    /**
     * @param  list<string>  $variants
     * @param  list<string>  $avoidSnippets  normalized lowercase snippets
     */
    private static function pickAvoiding(array $variants, string $seedMaterial, array $avoidSnippets, ?SeededRng $rng = null): string
    {
        if ($variants === []) {
            return '';
        }
        $rng ??= new SeededRng($seedMaterial);
        $n = count($variants);
        $start = (int) floor($rng->float01() * $n);

        for ($k = 0; $k < $n; $k++) {
            $idx = ($start + $k) % $n;
            $text = $variants[$idx];
            if (! self::matchesAvoid($text, $avoidSnippets)) {
                return $text;
            }
        }

        return $variants[$start % $n];
    }

    /**
     * @param  list<string>  $avoidSnippets
     */
    private static function matchesAvoid(string $text, array $avoidSnippets): bool
    {
        $head = explode("\n", trim($text), 2)[0];
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $head) ?? '');
        if (mb_strlen($norm) > 96) {
            $norm = mb_substr($norm, 0, 96);
        }
        foreach ($avoidSnippets as $snip) {
            $s = mb_strtolower(trim($snip));
            if ($s === '') {
                continue;
            }
            if ($norm === $s || str_contains($norm, $s) || str_contains($s, $norm)) {
                return true;
            }
        }

        return false;
    }
}
