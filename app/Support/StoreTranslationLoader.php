<?php

namespace App\Support;

use App\Services\PortalLanguageInstaller;
use App\Services\StoreLanguageInstaller;
use Illuminate\Contracts\Translation\Loader;

/**
 * Wraps Laravel's translation loader so the database-backed groups — the
 * storefront `messages` group (store_translations) and the client portal
 * `portal` group (portal_translations) — are served from the database on top
 * of the resources/lang files. Every other group, and any key missing from
 * the database, still comes from the files, and the translator's fallback
 * locale (en) covers keys a pack does not have yet.
 */
class StoreTranslationLoader implements Loader
{
    /** Translation group => installer serving it. */
    protected const SCOPES = [
        'messages' => StoreLanguageInstaller::class,
        'portal' => PortalLanguageInstaller::class,
    ];

    public function __construct(protected Loader $inner)
    {
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        if (isset(static::SCOPES[$group]) && ($namespace === null || $namespace === '*')) {
            $installer = static::SCOPES[$group];
            $db = $installer::lines((string) $locale);
            if ($db) {
                $lines = array_replace(is_array($lines) ? $lines : [], $db);
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }

    /** Forward anything else (e.g. FileLoader::addPath) to the real loader. */
    public function __call($method, $args)
    {
        return $this->inner->{$method}(...$args);
    }
}
