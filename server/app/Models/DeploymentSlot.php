<?php

namespace App\Models;

use App\Enums\DeploymentSlotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program_cycle_id', 'deployment_site_id', 'title', 'description',
    'capacity', 'status',
])]
class DeploymentSlot extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => DeploymentSlotStatus::class,
        ];
    }

    public function programCycle(): BelongsTo
    {
        return $this->belongsTo(ProgramCycle::class);
    }

    public function deploymentSite(): BelongsTo
    {
        return $this->belongsTo(DeploymentSite::class);
    }

    public function isActive(): bool
    {
        return $this->storedStatus() === DeploymentSlotStatus::Active;
    }

    public function storedStatus(): DeploymentSlotStatus
    {
        return DeploymentSlotStatus::tryFrom((string) ($this->attributes['status'] ?? 'active'))
            ?? DeploymentSlotStatus::Active;
    }

    public function getAssignedCountAttribute(): int
    {
        return $this->deploymentAssignments()
            ->whereIn('status', ['scheduled', 'active'])
            ->count();
    }

    public function deploymentAssignments(): HasMany
    {
        return $this->hasMany(DeploymentAssignment::class);
    }

    public function getAvailableCountAttribute(): int
    {
        return max(0, $this->capacity - $this->assigned_count);
    }
}
