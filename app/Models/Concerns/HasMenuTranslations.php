<?php

namespace App\Models\Concerns;

use App\Support\MenuLocale;
use Throwable;

trait HasMenuTranslations
{
    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        try {
            if (method_exists($this, 'getTranslation')) {
                $value = $this->getTranslation('name', $locale, false);

                if (filled($value)) {
                    return (string) $value;
                }

                $fallback = $this->getTranslation('name', MenuLocale::DEFAULT, false);

                return filled($fallback) ? (string) $fallback : '';
            }

            return (string) ($this->name ?? '');
        } catch (Throwable) {
            return $this->rawNameFallback($locale);
        }
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        if (! isset($this->description)) {
            return null;
        }

        $locale = $locale ?? app()->getLocale();

        try {
            if (method_exists($this, 'getTranslation')) {
                $value = $this->getTranslation('description', $locale, false);

                if (filled($value)) {
                    return (string) $value;
                }

                $fallback = $this->getTranslation('description', MenuLocale::DEFAULT, false);

                return filled($fallback) ? (string) $fallback : null;
            }

            return filled($this->description) ? (string) $this->description : null;
        } catch (Throwable) {
            $raw = $this->getAttributes()['description'] ?? null;

            return is_string($raw) && filled($raw) ? $raw : null;
        }
    }

    private function rawNameFallback(?string $locale = null): string
    {
        $locale = $locale ?? MenuLocale::DEFAULT;
        $raw = $this->getAttributes()['name'] ?? '';

        if (is_array($raw)) {
            return (string) ($raw[$locale] ?? $raw[MenuLocale::DEFAULT] ?? reset($raw) ?: '');
        }

        if (is_string($raw) && filled($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded[$locale] ?? $decoded[MenuLocale::DEFAULT] ?? reset($decoded) ?: '');
            }

            return $raw;
        }

        return '';
    }
}
