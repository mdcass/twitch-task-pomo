<?php

namespace App\Support\Branding;

class ProductBrand
{
    public static function productName(): string
    {
        $name = (string) config('app.name', 'Laravel');

        return $name === 'Laravel' ? 'Twitch Task Pomo' : $name;
    }

    public static function productTagline(): string
    {
        return 'Twitch Overlay Platform';
    }

    public static function mailMarkUrl(): string
    {
        return url('/images/email/application-mark.svg');
    }
}
