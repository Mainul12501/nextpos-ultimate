<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Writes the audit log. Every entry point is fail-safe: recording problems
 * are swallowed so they can never break the save that triggered them.
 *
 * Rows are inserted inside the caller's transaction, so a rolled-back sale
 * leaves no audit entry behind.
 *
 * Console runs (seeders, migrations, queue workers, sync commands) are not
 * recorded — they would flood the log with system noise.
 */
class AuditLogger
{
    /** Mass updates touching more rows than this get one summary entry. */
    public const MASS_ROW_LIMIT = 200;

    private const MAX_VALUE_LENGTH = 1000;

    private const MASK = '••••••';

    /** Never recorded, on any model. */
    private const IGNORED = ['created_at', 'updated_at', 'remember_token'];

    /** Recorded as "changed" without the value. */
    private const SECRET_PATTERN = '/password|secret|token|api_key|private_key|passphrase|access_key/i';

    private static ?bool $enabled = null;

    private static int $paused = 0;

    private static ?string $batchId = null;

    public static function enabled(): bool
    {
        if (self::$paused > 0) {
            return false;
        }

        if (self::$enabled !== null) {
            return self::$enabled;
        }

        try {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return self::$enabled = false;
            }

            if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('settings', 'audit_log_enabled')) {
                return self::$enabled = false;
            }

            $flag = DB::table('settings')->whereNull('deleted_at')->value('audit_log_enabled');

            return self::$enabled = $flag === null ? true : (bool) $flag;
        } catch (\Throwable $e) {
            return self::$enabled = false;
        }
    }

    /** Forget the cached on/off flag (after the setting changes). */
    public static function flush(): void
    {
        self::$enabled = null;
    }

    /** Run $callback without recording anything (bulk imports, rebuilds). */
    public static function withoutAuditing(callable $callback)
    {
        self::$paused++;

        try {
            return $callback();
        } finally {
            self::$paused--;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Model events                                                        */
    /* ------------------------------------------------------------------ */

    public static function modelCreated(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $new = self::clean($model, $model->getAttributes(), true);
        self::record('created', $model, [], $new);
    }

    public static function modelUpdated(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $changes = $model->getChanges();
        $old = [];
        $new = [];
        foreach ($changes as $key => $value) {
            $old[$key] = $model->getRawOriginal($key);
            $new[$key] = $value;
        }

        self::recordDiff($model, $old, $new);
    }

    public static function modelDeleted(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        self::record('deleted', $model, self::clean($model, $model->getAttributes(), true), []);
    }

    /* ------------------------------------------------------------------ */
    /* Mass queries (AuditableBuilder)                                     */
    /* ------------------------------------------------------------------ */

    public static function massUpdated(Model $prototype, $rows, array $values): void
    {
        try {
            if ($rows->count() > self::MASS_ROW_LIMIT) {
                self::record('bulk_updated', $prototype, [], self::clean($prototype, $values, false), null, [
                    'auditable_id' => null,
                    'auditable_label' => '> '.self::MASS_ROW_LIMIT,
                ]);

                return;
            }

            foreach ($rows as $row) {
                $old = [];
                $new = [];
                foreach ($values as $key => $value) {
                    $key = Str::afterLast((string) $key, '.');
                    $old[$key] = $row->getRawOriginal($key);
                    $new[$key] = $value;
                }
                self::recordDiff($row, $old, $new);
            }
        } catch (\Throwable $e) {
            // never break the caller
        }
    }

    public static function massDeleted(Model $prototype, $rows): void
    {
        try {
            if ($rows->count() > self::MASS_ROW_LIMIT) {
                self::record('bulk_deleted', $prototype, [], [], null, [
                    'auditable_id' => null,
                    'auditable_label' => '> '.self::MASS_ROW_LIMIT,
                ]);

                return;
            }

            foreach ($rows as $row) {
                self::record('deleted', $row, self::clean($row, $row->getAttributes(), true), []);
            }
        } catch (\Throwable $e) {
            // never break the caller
        }
    }

    /* ------------------------------------------------------------------ */
    /* Writing                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Record an arbitrary event (login, permissions_changed, …).
     *
     * @param  Model|null  $subject  record the event is about
     * @param  array  $overrides  raw column overrides for the inserted row
     */
    public static function record(string $event, ?Model $subject = null, array $old = [], array $new = [], ?string $label = null, array $overrides = []): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            [$userId, $userName] = self::actor();
            $request = app()->bound('request') ? request() : null;

            $row = [
                'user_id' => $userId,
                'user_name' => $userName,
                'event' => $event,
                'auditable_type' => $subject ? get_class($subject) : null,
                'auditable_id' => $subject ? $subject->getKey() : null,
                'auditable_label' => $label ?? ($subject && method_exists($subject, 'auditLabel') ? $subject->auditLabel() : null),
                'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'url' => $request ? mb_substr($request->path(), 0, 500) : null,
                'method' => $request?->method(),
                'batch_id' => self::batchId(),
                'created_at' => now(),
            ];

            DB::table('audit_logs')->insert(array_merge($row, $overrides));
            self::schedulePrune();
        } catch (\Throwable $e) {
            // never break the caller
        }
    }

    /* ------------------------------------------------------------------ */
    /* Retention (no cron needed)                                          */
    /* ------------------------------------------------------------------ */

    /**
     * First audit write of the day queues the retention cleanup to run after
     * the response is sent, so the user never waits for it. Cache::add is
     * atomic: one request per day wins, even under concurrent writes.
     */
    private static function schedulePrune(): void
    {
        static $queued = false;
        if ($queued) {
            return;
        }
        $queued = true;

        if (! Cache::add('audit_log_pruned_'.now()->toDateString(), 1, now()->endOfDay())) {
            return;
        }

        app()->terminating(function () {
            try {
                self::prune();
            } catch (\Throwable $e) {
                // retried tomorrow
            }
        });
    }

    /**
     * Delete entries older than the retention window ($days, or
     * settings.audit_log_retention_days; empty = keep forever). Chunked and
     * capped per run so a first pass over a huge table finishes over a few
     * days instead of holding one request. Returns rows deleted.
     */
    public static function prune(?int $days = null, int $maxChunks = 20): int
    {
        if (! Schema::hasTable('audit_logs')) {
            return 0;
        }

        if ($days === null && Schema::hasColumn('settings', 'audit_log_retention_days')) {
            $days = (int) DB::table('settings')->whereNull('deleted_at')->value('audit_log_retention_days');
        }
        if ((int) $days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays((int) $days);
        $deleted = 0;
        for ($i = 0; $i < $maxChunks; $i++) {
            $batch = DB::table('audit_logs')->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
            if ($batch < 5000) {
                break;
            }
        }

        return $deleted;
    }

    /** Updates: drop no-op changes, classify soft delete / restore. */
    private static function recordDiff(Model $model, array $old, array $new): void
    {
        $event = 'updated';
        if (array_key_exists('deleted_at', $new)) {
            if (empty($old['deleted_at']) && ! empty($new['deleted_at'])) {
                $event = 'deleted';
            } elseif (! empty($old['deleted_at']) && empty($new['deleted_at'])) {
                $event = 'restored';
            }
        }

        $exclude = array_merge(self::IGNORED, method_exists($model, 'auditExclude') ? $model->auditExclude() : []);
        $oldOut = [];
        $newOut = [];
        foreach ($new as $key => $value) {
            if (in_array($key, $exclude, true) || self::same($old[$key] ?? null, $value)) {
                continue;
            }
            if (preg_match(self::SECRET_PATTERN, $key)) {
                $oldOut[$key] = self::MASK;
                $newOut[$key] = self::MASK;

                continue;
            }
            $oldOut[$key] = self::value($old[$key] ?? null);
            $newOut[$key] = self::value($value);
        }

        if (! $newOut && $event === 'updated') {
            return;
        }

        self::record($event, $model, $oldOut, $newOut);
    }

    /** Snapshot for create/delete: no nulls, no noise, secrets masked. */
    private static function clean(Model $model, array $attributes, bool $dropNulls): array
    {
        $exclude = array_merge(self::IGNORED, method_exists($model, 'auditExclude') ? $model->auditExclude() : []);
        if ($dropNulls) {
            // Record snapshots: the key is already the entry's record #, and
            // deleted_at only restates the event.
            $exclude = array_merge($exclude, [$model->getKeyName(), 'deleted_at']);
        }
        $out = [];
        foreach ($attributes as $key => $value) {
            $key = Str::afterLast((string) $key, '.');
            if (in_array($key, $exclude, true) || ($dropNulls && ($value === null || $value === ''))) {
                continue;
            }
            $out[$key] = preg_match(self::SECRET_PATTERN, $key) ? self::MASK : self::value($value);
        }

        return $out;
    }

    private static function same($a, $b): bool
    {
        $a = self::value($a);
        $b = self::value($b);
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.0000001;
        }
        if (is_bool($a) || is_bool($b)) {
            return (int) $a === (int) $b;
        }

        return (string) $a === (string) $b;
    }

    private static function value($value)
    {
        if ($value instanceof Expression) {
            return '(expression)';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        if (is_string($value) && mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            return mb_substr($value, 0, self::MAX_VALUE_LENGTH).'…';
        }

        return $value;
    }

    /** [user_id, display name]; storefront/portal actors keep user_id null. */
    private static function actor(): array
    {
        try {
            $user = Auth::user();
            if (! $user && app()->bound('request') && request()->is('api/*')) {
                $user = Auth::guard('api')->user();
            }
        } catch (\Throwable $e) {
            $user = null;
        }

        if (! $user) {
            return [null, null];
        }

        if ($user instanceof User) {
            $name = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));

            return [(int) $user->id, $user->username ?: ($name ?: $user->email)];
        }

        $name = $user->name ?? $user->username ?? $user->email ?? ('#'.$user->getAuthIdentifier());

        return [null, class_basename($user).': '.$name];
    }

    /** Groups every entry written by one request. */
    private static function batchId(): string
    {
        if (app()->bound('request')) {
            $request = request();
            $id = $request->attributes->get('audit_batch_id');
            if (! $id) {
                $id = (string) Str::uuid();
                $request->attributes->set('audit_batch_id', $id);
            }

            return $id;
        }

        return self::$batchId ??= (string) Str::uuid();
    }
}
