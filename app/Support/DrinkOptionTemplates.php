<?php

namespace App\Support;

/**
 * Coffee de Madrid reçetesine uygun admin / seed varyasyon şablonları.
 */
class DrinkOptionTemplates
{
    /** @return array<string, array<string, mixed>> */
    public static function temperatureGroup(): array
    {
        return [
            'name' => ['tr' => 'Sıcaklık', 'en' => 'Temperature', 'ru' => 'Температура'],
            'type' => 'single',
            'required' => true,
            'options' => [
                ['name' => ['tr' => 'Sıcak', 'en' => 'Hot', 'ru' => 'Горячий'], 'price' => 0, 'default' => true],
                ['name' => ['tr' => 'Buzlu', 'en' => 'Iced', 'ru' => 'Со льдом'], 'price' => 0, 'default' => false],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function milkGroup(): array
    {
        return [
            'name' => ['tr' => 'Süt Değişimi', 'en' => 'Milk', 'ru' => 'Молоко'],
            'type' => 'single',
            'required' => false,
            'options' => [
                ['name' => ['tr' => 'Normal Süt', 'en' => 'Regular Milk', 'ru' => 'Обычное'], 'price' => 0, 'default' => true],
                ['name' => ['tr' => 'Yağsız Süt', 'en' => 'Non-Fat Milk', 'ru' => 'Обезжиренное'], 'price' => 0, 'default' => false],
                ['name' => ['tr' => 'Laktozsuz Süt', 'en' => 'Lactose-Free', 'ru' => 'Без лактозы'], 'price' => 0, 'default' => false],
                ['name' => ['tr' => 'Soya Sütü', 'en' => 'Soy Milk', 'ru' => 'Соевое'], 'price' => 15, 'default' => false],
                ['name' => ['tr' => 'Yulaf Sütü', 'en' => 'Oat Milk', 'ru' => 'Овсяное'], 'price' => 15, 'default' => false],
                ['name' => ['tr' => 'Badem Sütü', 'en' => 'Almond Milk', 'ru' => 'Миндальное'], 'price' => 15, 'default' => false],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function shotGroup(): array
    {
        return [
            'name' => ['tr' => 'Shot', 'en' => 'Shot', 'ru' => 'Шот'],
            'type' => 'single',
            'required' => false,
            'options' => [
                ['name' => ['tr' => 'Single', 'en' => 'Single', 'ru' => 'Single'], 'price' => 0, 'default' => true],
                ['name' => ['tr' => 'Double', 'en' => 'Double', 'ru' => 'Double'], 'price' => 15, 'default' => false],
                ['name' => ['tr' => 'Triple', 'en' => 'Triple', 'ru' => 'Triple'], 'price' => 25, 'default' => false],
                ['name' => ['tr' => 'Quadruple', 'en' => 'Quadruple', 'ru' => 'Quadruple'], 'price' => 35, 'default' => false],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function fullDrinkPackage(): array
    {
        return [
            self::temperatureGroup(),
            self::milkGroup(),
            self::shotGroup(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function groupsForTemplate(string $template): array
    {
        return match ($template) {
            'temperature' => [self::temperatureGroup()],
            'milk' => [self::milkGroup()],
            'shot' => [self::shotGroup()],
            'full' => self::fullDrinkPackage(),
            default => [],
        };
    }
}
