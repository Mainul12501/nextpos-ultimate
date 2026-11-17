<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditableBuilder;
use App\Services\Audit\AuditLogger;

/**
 * Records every create / update / delete of the model in the audit log.
 *
 * Instance saves are caught by the model events below. Mass updates such as
 * `Sale::whereId($id)->update(['deleted_at' => now()])` fire no model events,
 * so the model also gets an AuditableBuilder that snapshots the affected rows
 * before running the query.
 *
 * Optional per-model tuning:
 *   protected $auditExclude = ['column', ...];   // never recorded
 */
trait Auditable
{
    public static function bootAuditable()
    {
        static::created(fn ($model) => AuditLogger::modelCreated($model));
        static::updated(fn ($model) => AuditLogger::modelUpdated($model));
        static::deleted(fn ($model) => AuditLogger::modelDeleted($model));
        // SoftDeletes restore() goes through save(), so the updated event
        // already records it (deleted_at set → null is classified "restored").
    }

    public function newEloquentBuilder($query)
    {
        return new AuditableBuilder($query);
    }

    public function auditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? (array) $this->auditExclude : [];
    }

    /** Human label shown next to the record id (reference, name, …). */
    public function auditLabel(): ?string
    {
        foreach (['Ref', 'name', 'title', 'username', 'code', 'email'] as $attribute) {
            $value = $this->getAttributeFromArray($attribute);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr((string) $value, 0, 191);
            }
        }

        return null;
    }
}
