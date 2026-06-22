<?php

namespace App\Support;

class StaffUi
{
    /** @var list<string> */
    private const THEMES = ['classic', 'premium'];

    public static function theme(): string
    {
        $theme = (string) config('ui.staff_theme', 'classic');

        return in_array($theme, self::THEMES, true) ? $theme : 'classic';
    }

    public static function isPremium(): bool
    {
        return self::theme() === 'premium';
    }
}
