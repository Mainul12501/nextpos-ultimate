<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use App\Models\Translate;
use App\Services\LanguageInstaller;
use App\Support\LanguageCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LanguageController extends Controller
{
    public function load_language(Request $request)
    {
        $languages = Language::where('is_active', true)->get(['name', 'locale', 'flag']);

        return response()->json($languages);

    }

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        return Language::all();
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        $request->validate([
            'name' => 'required|string|max:100',
            'locale' => 'required|string|max:10|unique:languages',
            // Laravel 12: image rule excludes SVG by default; use file + mimes for SVG support
            'flag' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($request->hasFile('flag')) {
            // Saved as-is: flags are tiny, and SVG cannot go through Image::make
            // (which was also never imported here, so every upload crashed).
            $image = $request->file('flag');
            $filename = 'custom_'.bin2hex(random_bytes(6)).'.'.strtolower($image->getClientOriginalExtension() ?: 'png');
            $image->move(public_path('flags'), $filename);
        } else {
            $filename = 'no-image.png';
        }

        $language = Language::create([
            'name' => $request->name,
            'locale' => $request->locale,
            'flag' => $filename,
            'is_active' => true,
        ]);

        // A hand-added locale that matches a shipped pack gets its keys too.
        if (LanguageCatalog::find($language->locale) && LanguageCatalog::packPath($language->locale)) {
            (new LanguageInstaller)->install($language->locale);
        }

        return $language;
    }

    public function update(Request $request, Language $language)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        $request->validate([
            'name' => 'required|string|max:100',
            'locale' => 'required|string|max:10|unique:languages,locale,'.$language->id,
            // Laravel 12: image rule excludes SVG by default; use file + mimes for SVG support
            'flag' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        // Handle flag upload
        if ($request->hasFile('flag')) {
            // Delete old flag if not default
            if ($language->flag && $language->flag !== 'no-image.png') {
                $oldPath = public_path('/flags/'.$language->flag);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $image = $request->file('flag');
            $extension = strtolower($image->getClientOriginalExtension());
            $filename = rand(11111111, 99999999).'.'.$extension;
            $destination = public_path('/flags/'.$filename);

            if ($extension === 'svg') {
                // Just move the SVG without resizing
                $image->move(public_path('/flags'), $filename);
            } else {
                // Resize for raster formats
                $image_resize = \Image::make($image->getRealPath());
                $image_resize->save($destination);
            }
        } else {
            $filename = $language->flag;
        }

        // Update fields
        $language->update([
            'name' => $request->name,
            'locale' => $request->locale,
            'flag' => $filename,
        ]);

        return response()->json(['success' => true, 'language' => $language]);
    }

    public function destroy(Request $request, Language $language)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        // English is every locale's fallback, and the default language is what
        // the app falls back to for users without a choice — neither can go.
        if (in_array($language->locale, LanguageCatalog::PROTECTED_LOCALES, true) || $language->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'English and the default language cannot be deleted.',
            ], 422);
        }

        // Delete all translations for this language
        Translate::where('locale', $language->locale)->delete();

        // Delete the language itself
        $language->delete();

        if (function_exists('forget_db_translations')) {
            forget_db_translations($language->locale);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Catalog languages that ship a translation pack but are not installed yet
     * (Settings → Languages → "Available languages").
     */
    public function available(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        $installed = Language::pluck('locale')->map(fn ($l) => strtolower($l))->flip();

        $available = collect(LanguageCatalog::available())
            ->reject(fn ($entry) => isset($installed[strtolower($entry['locale'])]))
            ->map(fn ($entry) => [
                'locale' => $entry['locale'],
                'name' => $entry['name'],
                'flag' => $entry['flag'],
                'rtl' => $entry['rtl'],
            ])
            ->values();

        return response()->json($available);
    }

    /** Install a catalog language and import its translation pack. */
    public function install(Request $request, $locale)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        if (! LanguageCatalog::packPath($locale) || ! LanguageCatalog::find($locale)) {
            return response()->json([
                'success' => false,
                'message' => 'No translation pack is available for this language.',
            ], 404);
        }

        @set_time_limit(300);

        $language = (new LanguageInstaller)->install($locale);

        return response()->json([
            'success' => true,
            'language' => $language->fresh(),
            'keys' => Translate::where('locale', $locale)->count(),
        ]);
    }

    public function setDefault(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        // Set all languages to not default
        Language::query()->update(['is_default' => false]);

        // Set the selected language as default
        $language = Language::findOrFail($id);
        $language->is_default = true;
        $language->save();

        // Update default_language in settings table
        if ($language->locale) {
            $setting = Setting::first();
            if ($setting) {
                $setting->update([
                    'default_language' => $language->locale,
                ]);
                // SetLocale caches this to keep it off every web request.
                Cache::forget('settings.default_language');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Default language set successfully.',
            'default_locale' => $language->locale,
        ]);
    }

    public function setDefaultByLocale(Request $request, $locale)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        // Set selected language as default
        $language = Language::where('locale', $locale)->firstOrFail();

        // Skip if already default
        if (! $language->is_default) {
            // Set this one as default
            $language->update(['is_default' => true]);

            // Unset others
            Language::where('id', '!=', $language->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            // Update settings
            Setting::query()->update(['default_language' => $language->locale]);
            Cache::forget('settings.default_language');
        }

        return response()->json([
            'success' => true,
            'message' => 'Default language set successfully.',
            'default_locale' => $language->locale,
        ]);
    }

    public function setLocaleActive(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        // Find language by ID
        $language = Language::where('id', $id)->firstOrFail();

        // Toggle the is_active value
        $language->update([
            'is_active' => ! $language->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully.',
            'new_status' => $language->is_active,
        ]);
    }

    public function get_translate(Request $request, $locale)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        $perPage = (int) $request->get('per_page', 100);
        $page = (int) $request->get('page', 1);
        $search = $request->get('search');

        // Step 1: Get all EN keys
        $enKeysQuery = Translate::where('locale', 'en');

        if ($search) {
            $enKeysQuery->where(function ($q) use ($search) {
                $q->where('key', 'like', "%$search%")
                    ->orWhere('value', 'like', "%$search%");
            });
        }

        $enKeys = $enKeysQuery->orderBy('id', 'desc')->get();
        $enKeyList = $enKeys->pluck('key')->toArray();

        // Step 2: Get all locale-specific keys (not in EN)
        $localeExtraKeys = Translate::where('locale', $locale)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%$search%")
                        ->orWhere('value', 'like', "%$search%");
                });
            })
            ->whereNotIn('key', $enKeyList)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'key' => $item->key,
                    'value' => $item->value,
                    'locale' => $item->locale,
                ];
            });

        // Step 3: Merge EN + values from locale
        $localeTranslations = Translate::where('locale', $locale)->pluck('value', 'key');

        $mergedFromEN = $enKeys->map(function ($item) use ($locale, $localeTranslations) {
            return [
                'id' => $item->id,
                'key' => $item->key,
                'value' => $localeTranslations[$item->key] ?? '',
                'locale' => $locale,
            ];
        });

        // Step 4: Combine all
        $combined = $mergedFromEN->merge($localeExtraKeys)->sortByDesc('id')->values();

        // Step 5: Paginate manually
        $total = $combined->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $combined->slice($offset, $perPage)->values();

        $language = optional(Language::where('locale', $locale)->first())->name ?? $locale;

        return response()->json([
            'data' => $paginated,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'language' => $language,
        ]);
    }

    public function updateOrInsert(Request $request, $locale)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'nullable|string',
        ]);

        // Flag the row as client-edited so TranslationSeeder never overwrites it.
        $translation = Translate::updateOrCreate(
            ['locale' => $locale, 'key' => $request->key],
            ['value' => $request->value, 'is_customized' => 1]
        );

        // Blade reads this table through tdb(); drop its cached map so the edit
        // shows on the login/password pages right away.
        forget_db_translations($locale);

        return response()->json([
            'message' => 'Translation saved successfully.',
            'data' => $translation,
        ]);
    }

    public function delete_translate(Request $request, $locale, $key)
    {
        $this->authorizeForUser($request->user('api'), 'translations_settings', Setting::class);

        // Try to find the translation entry by locale and key
        $translation = Translate::where('locale', $locale)
            ->where('key', $key)
            ->first();

        if (! $translation) {
            return response()->json([
                'message' => 'Translation not found.',
            ], 404);
        }

        // Prevent deleting default/original keys
        if ($translation->is_default) {
            return response()->json([
                'message' => 'You cannot delete a default/original translation key.',
            ], 403);
        }

        // Delete the translation
        $translation->delete();
        forget_db_translations($locale);

        return response()->json([
            'message' => 'Translation deleted successfully.',
        ]);
    }
}
