<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * An immutable record of a meaningful business event.
 *
 * Rows are only ever created through App\Services\AuditLogger. Updates and
 * deletes are rejected at the model level, and no HTTP endpoint exposes a way
 * to modify or remove audit records.
 */
class AuditLog extends Model
{
    /**
     * Audit records are write-once: there is no "updated at" for history.
     */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }
}
