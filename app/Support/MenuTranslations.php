<?php

namespace App\Support;

use Illuminate\Http\Request;

class MenuTranslations
{
    /** @var list<string> */
    public const ADMIN_LOCALES = ['tr', 'en', 'ru'];

    /**
     * @return array{name: array<string, string|null>, description: array<string, string|null>}
     */
    public static function validated(Request $request, bool $nameRequired = true, int $maxName = 150): array
    {
        $rules = [
            'name' => ($nameRequired ? 'required' : 'nullable').'|array',
            'name.tr' => ($nameRequired ? 'required' : 'nullable')."|string|max:{$maxName}",
            'name.en' => "nullable|string|max:{$maxName}",
            'name.ru' => "nullable|string|max:{$maxName}",
            'description' => 'nullable|array',
            'description.tr' => 'nullable|string',
            'description.en' => 'nullable|string',
            'description.ru' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        return [
            'name' => self::cleanMap($validated['name'] ?? []),
            'description' => self::cleanMap($validated['description'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function cleanMap(array $input): array
    {
        $clean = [];

        foreach (self::ADMIN_LOCALES as $locale) {
            $value = $input[$locale] ?? null;
            if (filled($value)) {
                $clean[$locale] = trim((string) $value);
            }
        }

        return $clean;
    }
}
