<?php

namespace App\Domain\Bargain;

final class DeterministicShopkeeperReply
{
    public static function welcome(string $customerName, string $productName, string $variantLabel, string $listPrice): string
    {
        $name = trim($customerName);
        $hello = $name !== '' ? "Assalam‑o‑alaikum {$name}!" : 'Assalam‑o‑alaikum!';

        return "{$hello} {$productName} ({$variantLabel}) ki price PKR {$listPrice} hai.\n\n".
            "Aapka budget kitna hai? (e.g. “Mera budget 11000 hai”)";
    }

    public static function askForOfferWithAmount(string $listPrice): string
    {
        return "Theek hai — apna budget PKR me bata dein.";
    }

    public static function nudgeIncreaseFromLastOffer(string $lastOffer): string
    {
        return "Samajh gaya — aap ne last PKR {$lastOffer} bola tha.\n\n".
            "Is pair ke liye thora sa increase kar dein? Comfort/styling dono top hai.";
    }

    public static function counterTooLow(string $customerOffer, string $counterOffer, string $listPrice): string
    {
        return "PKR {$customerOffer} thora low hai.\n\n".
            "Best main PKR {$counterOffer} tak kar sakta hun.";
    }

    public static function acceptable(string $offer): string
    {
        return "Done. PKR {$offer} final.\n\n".
            "Accept par tap kar dein, main checkout ke liye lock kar deta hun.";
    }

    public static function acceptableNudged(string $stated, string $nudged, string $listPrice): string
    {
        return "PKR {$stated} samajh gaya. Best main PKR {$nudged} tak kar sakta hun.\n\n".
            "Listed PKR {$listPrice} hai — agar okay ho to accept kar dein.";
    }

    public static function decline(): string
    {
        return "Koi masla nahi.\n\n".
            "Jab bhi chahein, phir se bargain start kar sakte hain.";
    }
}
