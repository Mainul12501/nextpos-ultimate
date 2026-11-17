<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Api\ScopedLanguagesController;
use App\Models\StoreSetting;
use App\Services\StoreLanguageInstaller;
use Illuminate\Http\Request;

/**
 * Store → Languages and Store → Translations.
 *
 * Storefront languages are independent from the back-office ones: rows live in
 * store_languages / store_translations and are served to __('messages.*') by
 * StoreTranslationLoader. The default storefront language stays in
 * store_settings.language. All endpoint logic is in ScopedLanguagesController.
 */
class StoreLanguagesController extends ScopedLanguagesController
{
    protected function installerClass(): string
    {
        return StoreLanguageInstaller::class;
    }

    protected function authorizeScope(Request $request): void
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);
    }

    protected function label(): string
    {
        return 'storefront';
    }

    protected function defaultLocale(): string
    {
        return (string) (StoreSetting::query()->value('language') ?: 'en');
    }

    protected function applyDefault(string $locale): void
    {
        $setting = StoreSetting::query()->first();
        if ($setting) {
            $setting->language = $locale;
            $setting->save();
        }
        if (function_exists('store_url_config_clear')) {
            store_url_config_clear();
        }
    }

    protected function extraIndex(): array
    {
        return ['visit_url' => function_exists('store_base_url') ? store_base_url() : url('/')];
    }
}
