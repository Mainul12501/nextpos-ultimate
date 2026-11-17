<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Storefront languages (Store → Languages): store_languages /
 * store_translations, serving the `messages` group. All logic lives in
 * ScopedLanguageInstaller.
 */
class StoreLanguageInstaller extends ScopedLanguageInstaller
{
    public static function scope(): string
    {
        return 'store';
    }

    public static function group(): string
    {
        return 'messages';
    }

    public static function packDir(): string
    {
        return 'store_translations';
    }

    /** CountryService indexes country names in every storefront language. */
    protected static function afterForget(?string $locale): void
    {
        Cache::forget('country_name_index');
        $locales = $locale ? [$locale] : array_keys(\App\Support\LanguageCatalog::all());
        foreach ($locales as $loc) {
            Cache::forget('country_options_'.$loc);
        }
    }
}
