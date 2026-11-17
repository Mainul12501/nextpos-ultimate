<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Services\AdminLanguageInstaller;
use App\Support\LanguageCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Languages / Translations for the back-office app, on the shared
 * Languages admin API (same endpoints and page design as the storefront and
 * client portal). Back-office specifics:
 *  - the default language lives in settings.default_language AND
 *    languages.is_default (SetLocale caches the former);
 *  - admins can still add a CUSTOM language (not in the catalog) with an
 *    uploaded flag, and change a language's name / flag.
 */
class AdminLanguagesController extends ScopedLanguagesController
{
    protected function installerClass(): string
    {
        return AdminLanguageInstaller::class;
    }

    protected function authorizeScope(Request $request): void
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);
    }

    protected function label(): string
    {
        return 'app';
    }

    protected function defaultLocale(): string
    {
        return (string) (Setting::query()->whereNull('deleted_at')->value('default_language')
            ?: DB::table('languages')->where('is_default', 1)->value('locale')
            ?: 'en');
    }

    protected function applyDefault(string $locale): void
    {
        DB::table('languages')->update(['is_default' => 0]);
        DB::table('languages')->where('locale', $locale)->update(['is_default' => 1]);
        Setting::query()->whereNull('deleted_at')->update(['default_language' => $locale]);
        // SetLocale caches this to keep it off every web request.
        Cache::forget('settings.default_language');
    }

    /** Languages not in the catalog were added by hand (no shipped pack). */
    protected function extraLanguageFields(object $language): array
    {
        return ['is_custom' => LanguageCatalog::find($language->locale) === null];
    }

    /**
     * POST admin/system/languages (multipart: name, locale, flag?) — add a
     * custom language. A locale that matches a shipped pack gets its keys too.
     */
    public function store(Request $request)
    {
        $this->authorizeScope($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'locale' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})?$/', 'unique:languages,locale'],
            'flag' => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $flag = $request->hasFile('flag') ? $this->storeFlag($request->file('flag')) : null;
        if (! $flag && ($entry = LanguageCatalog::find($data['locale']))) {
            $flag = $entry['flag'];
        }

        DB::table('languages')->insert([
            'name' => $data['name'],
            'locale' => $data['locale'],
            'flag' => $flag,
            'is_active' => 1,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (LanguageCatalog::find($data['locale']) && LanguageCatalog::packPath($data['locale'])) {
            $this->installLocale($data['locale']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * PUT admin/system/languages/{id} — rename; with multipart (POST +
     * _method=PUT) a new flag image can be uploaded too. The locale code is
     * deliberately not editable: translations are keyed by it.
     */
    public function update(Request $request, int $id)
    {
        $this->authorizeScope($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'flag' => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $lang = DB::table('languages')->where('id', $id)->first();
        abort_unless($lang, 404);

        $patch = ['name' => $data['name'], 'updated_at' => now()];
        if ($request->hasFile('flag')) {
            $patch['flag'] = $this->storeFlag($request->file('flag'));
            $this->deleteUploadedFlag($lang->flag);
        }

        DB::table('languages')->where('id', $id)->update($patch);

        return response()->json(['success' => true, 'flag' => $patch['flag'] ?? $lang->flag]);
    }

    /**
     * Flags live in public/flags. Saved as-is (they are tiny), which also keeps
     * SVG working — the old Add form ran every upload through Image::make and
     * crashed (the facade was not even imported in that controller).
     */
    private function storeFlag($file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'custom_'.bin2hex(random_bytes(6)).'.'.$ext;
        $file->move(public_path('flags'), $name);

        return $name;
    }

    /** Remove a previously uploaded flag; never the shipped catalog flags. */
    private function deleteUploadedFlag(?string $flag): void
    {
        if (! $flag || ! str_starts_with($flag, 'custom_') || str_contains($flag, '/') || str_contains($flag, '\\')) {
            return;
        }
        $path = public_path('flags/'.$flag);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
