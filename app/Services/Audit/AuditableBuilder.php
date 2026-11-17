<?php

namespace App\Services\Audit;

use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent builder for Auditable models: snapshots the rows a mass update or
 * delete is about to touch, runs the query, then records one entry per row.
 *
 * A builder whose model `exists` belongs to an instance save()/delete() —
 * those are already recorded by the model events, so they pass straight
 * through. SoftDeletes builders route delete() into update(), which records
 * it there.
 */
class AuditableBuilder extends Builder
{
    public function update(array $values)
    {
        if ($this->model->exists || ! AuditLogger::enabled()) {
            return parent::update($values);
        }

        $before = $this->snapshot();
        $affected = parent::update($values);

        if ($affected) {
            AuditLogger::massUpdated($this->model, $before, $values);
        }

        return $affected;
    }

    public function delete()
    {
        if (isset($this->onDelete) || $this->model->exists || ! AuditLogger::enabled()) {
            return parent::delete();
        }

        $before = $this->snapshot();
        $deleted = parent::delete();

        if ($deleted) {
            AuditLogger::massDeleted($this->model, $before);
        }

        return $deleted;
    }

    private function snapshot()
    {
        try {
            // Keep the query's own limit (update()->limit(3) touches 3 rows);
            // one row past the cap is enough to know it's a bulk change.
            $cap = AuditLogger::MASS_ROW_LIMIT + 1;
            $own = $this->getQuery()->limit;

            return (clone $this)
                ->limit($own ? min((int) $own, $cap) : $cap)
                ->get([$this->model->getTable().'.*']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
