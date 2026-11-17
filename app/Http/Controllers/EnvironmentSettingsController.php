<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WooCommerceSetting;
use App\Services\EnvEditor;
use App\Services\WooCommerce\SyncOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * System Settings → Environment (.env).
 *
 * Only the keys in catalog() can be read or written; anything else in .env
 * (DB credentials, APP_KEY, gateway secrets…) is never exposed here. To make a
 * new key editable, add it to catalog().
 *
 * The WooCommerce keys are FALLBACKS: a value saved under
 * WooCommerce → Sync tuning (woocommerce_settings.sync_options) always wins,
 * so each field reports whether it is currently overridden.
 */
class EnvironmentSettingsController extends Controller
{
    public function __construct(private EnvEditor $env)
    {
    }

    public function show(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        $catalog = $this->catalog();
        $keys = array_merge(...array_values(array_map(fn ($g) => array_keys($g['fields']), $catalog)));
        $values = $this->env->get($keys);
        $saved = $this->savedWooOptions();

        $groups = [];
        foreach ($catalog as $groupKey => $group) {
            $fields = [];
            foreach ($group['fields'] as $key => $field) {
                $raw = $values[$key];
                $fields[] = $field + [
                    'key' => $key,
                    'value' => $this->castOut($field['type'], $raw),
                    'in_file' => $raw !== null,
                    'overridden_by' => isset($field['woo_option']) && array_key_exists($field['woo_option'], $saved)
                        ? ['label' => 'WooCommerce → Sync tuning', 'value' => $saved[$field['woo_option']]]
                        : null,
                ];
            }
            $groups[] = [
                'key' => $groupKey,
                'title' => $group['title'],
                'description' => $group['description'] ?? null,
                'fields' => $fields,
            ];
        }

        return response()->json([
            'groups' => $groups,
            'writable' => $this->env->isWritable(),
            'config_cached' => app()->configurationIsCached(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        $request->validate(['values' => 'required|array']);

        $fields = [];
        foreach ($this->catalog() as $group) {
            $fields += $group['fields'];
        }

        $rules = [];
        foreach ($fields as $key => $field) {
            $rules['values.'.$key] = $this->rulesFor($field);
        }
        $data = $request->validate($rules)['values'] ?? [];

        // Unknown keys were already dropped by validate(); only write what changed.
        $current = $this->env->get(array_keys($data));
        $changes = [];
        foreach ($data as $key => $value) {
            $new = $this->castIn($fields[$key]['type'], $value);
            $old = $current[$key];
            $newAsString = is_bool($new) ? ($new ? 'true' : 'false') : ($new === null ? null : (string) $new);
            if ($newAsString !== $old) {
                $changes[$key] = $new;
            }
        }

        if (! $changes) {
            return response()->json(['success' => true, 'changed' => []]);
        }

        try {
            $this->env->set($changes);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['values' => $e->getMessage()]);
        }

        // A cached config ignores .env entirely, and env() reads outside config
        // files (the Woo fallbacks, QUEUE_WORKER_TIMEOUT) return null with it.
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        // Long-running workers keep the old values until they restart.
        if (collect(array_keys($changes))->contains(fn ($k) => str_starts_with($k, 'QUEUE_') || str_starts_with($k, 'WOO_'))) {
            try {
                Artisan::call('queue:restart');
            } catch (\Throwable $e) {
                // Needs a working cache store; a failure here must not undo the save.
            }
        }

        SyncOptions::flush();

        return response()->json(['success' => true, 'changed' => array_keys($changes)]);
    }

    /**
     * group key => [title, description, fields => [ENV_KEY => field]]
     * field: type (integer|boolean|string|select), label, hint, default,
     *        min/max (integer), options (select), required, woo_option.
     */
    private function catalog(): array
    {
        $catalog = [
            'queue' => [
                'title' => 'Queue',
                'description' => 'Background jobs (emails, webhooks, WooCommerce / Shopify syncs). Workers restart automatically after saving.',
                'fields' => [
                    'QUEUE_CONNECTION' => [
                        'type' => 'select',
                        'label' => 'Queue driver',
                        'hint' => '"database" needs the scheduler (cron) or a queue worker. "sync" runs jobs immediately inside the request — no worker needed, but long syncs can time out.',
                        'default' => 'database',
                        'required' => true,
                        'options' => [
                            ['value' => 'database', 'label' => 'Database (recommended)'],
                            ['value' => 'sync', 'label' => 'Sync (no worker)'],
                            ['value' => 'redis', 'label' => 'Redis'],
                        ],
                    ],
                    'QUEUE_RETRY_AFTER' => [
                        'type' => 'integer', 'min' => 60, 'max' => 86400, 'default' => 1200,
                        'label' => 'Retry after (seconds)',
                        'hint' => 'A job still running after this long is handed to another worker. Keep it higher than the worker timeout.',
                    ],
                    'QUEUE_WORKER_TIMEOUT' => [
                        'type' => 'integer', 'min' => 60, 'max' => 86400, 'default' => 1200,
                        'label' => 'Worker timeout (seconds)',
                        'hint' => 'Maximum run time of one job before the worker kills it.',
                    ],
                ],
            ],
        ];

        // WooCommerce: reuse the SyncOptions catalog so labels, ranges and
        // defaults stay in one place.
        $wooGroups = [];
        foreach (SyncOptions::GROUPS as $g => $title) {
            $wooGroups['woo_'.$g] = ['title' => 'WooCommerce — '.$title, 'fields' => []];
        }
        foreach (SyncOptions::INTEGERS as $opt => [$env, $default, $min, $max, $g, $label, $hint]) {
            $wooGroups['woo_'.$g]['fields'][$env] = compact('label', 'hint', 'default', 'min', 'max') + ['type' => 'integer', 'woo_option' => $opt];
        }
        foreach (SyncOptions::BOOLEANS as $opt => [$env, $default, $g, $label, $hint]) {
            $wooGroups['woo_'.$g]['fields'][$env] = compact('label', 'hint', 'default') + ['type' => 'boolean', 'woo_option' => $opt];
        }
        foreach (SyncOptions::STRINGS as $opt => [$env, $default, $g, $label, $hint]) {
            $wooGroups['woo_'.$g]['fields'][$env] = compact('label', 'hint', 'default') + ['type' => 'string', 'woo_option' => $opt];
        }

        return $catalog + array_filter($wooGroups, fn ($g) => $g['fields']);
    }

    private function rulesFor(array $field): array
    {
        $rules = [empty($field['required']) ? 'nullable' : 'required'];

        switch ($field['type']) {
            case 'integer':
                $rules[] = 'integer';
                $rules[] = 'min:'.$field['min'];
                $rules[] = 'max:'.$field['max'];
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'select':
                $rules[] = 'in:'.implode(',', array_column($field['options'], 'value'));
                break;
            default:
                $rules[] = 'string';
                $rules[] = 'max:255';
                $rules[] = 'not_regex:/[\r\n]/';
        }

        return $rules;
    }

    /** Submitted value → what gets written (null removes the key). */
    private function castIn(string $type, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => trim((string) $value),
        };
    }

    /** Raw .env string → typed value for the form. */
    private function castOut(string $type, ?string $raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($type) {
            'integer' => is_numeric($raw) ? (int) $raw : null,
            'boolean' => in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], true),
            default => $raw,
        };
    }

    private function savedWooOptions(): array
    {
        try {
            $saved = WooCommerceSetting::query()->value('sync_options');
            if (is_string($saved)) {
                $saved = json_decode($saved, true);
            }

            return is_array($saved) ? $saved : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
