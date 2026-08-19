<?php

namespace App\Models;

use App\Enums\ProgramCycleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program_id', 'name', 'description', 'status', 'total_slots',
    'application_start', 'application_deadline',
    'deployment_start', 'deployment_end', 'created_by',
])]
class ProgramCycle extends Model
{
    use HasFactory;

    protected $casts = [
        'application_start' => 'date',
        'application_deadline' => 'date',
        'deployment_start' => 'date',
        'deployment_end' => 'date',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function deploymentSlots(): HasMany
    {
        return $this->hasMany(DeploymentSlot::class);
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'program_cycle_requirements')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Whether applications are currently being accepted for this cycle.
     *
     * A cycle must be published first; draft and archived cycles never
     * accept applications, regardless of their date window.
     */
    public function isAcceptingApplications(): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $start = $this->application_start;
        $deadline = $this->application_deadline;
        $today = today();

        return $start !== null
            && $deadline !== null
            && $today->between($start, $deadline);
    }

    /**
     * Whether the cycle has been published (not draft or archived).
     */
    public function isPublished(): bool
    {
        $status = $this->storedStatus();

        return $status !== ProgramCycleStatus::Draft
            && $status !== ProgramCycleStatus::Archived;
    }

    /**
     * The stored publication state (draft / published-phase / archived).
     */
    public function storedStatus(): ProgramCycleStatus
    {
        return ProgramCycleStatus::tryFrom((string) ($this->attributes['status'] ?? 'draft'))
            ?? ProgramCycleStatus::Draft;
    }

    /**
     * Scope to cycles that are published and therefore visible to students.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['upcoming', 'open', 'closed']);
    }

    /**
     * Scope to cycles still in preparation or retired (hidden from students).
     */
    public function scopeHidden(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'archived']);
    }

    /**
     * The date-derived phase the cycle would be in right now.
     */
    public function phaseStatus(): ProgramCycleStatus
    {
        if ($this->application_start === null || $this->application_deadline === null) {
            return ProgramCycleStatus::Draft;
        }

        $today = today();

        if ($today->lt($this->application_start)) {
            return ProgramCycleStatus::Upcoming;
        }

        if ($today->between($this->application_start, $this->application_deadline)) {
            return ProgramCycleStatus::Open;
        }

        return ProgramCycleStatus::Closed;
    }

    /**
     * The number of free slots left for this cycle.
     */
    public function getSlotsRemainingAttribute(): int
    {
        $used = $this->relationLoaded('applications')
            ? $this->applications->count()
            : $this->applications_count ?? 0;

        return max(0, (int) $this->total_slots - $used);
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ProgramCycleStatus => $this->resolveStatus($value),
        );
    }

    protected function resolveStatus(?string $stored): ProgramCycleStatus
    {
        $storedStatus = ProgramCycleStatus::tryFrom((string) $stored) ?? ProgramCycleStatus::Draft;

        if ($storedStatus === ProgramCycleStatus::Draft || $storedStatus === ProgramCycleStatus::Archived) {
            return $storedStatus;
        }

        return $this->phaseStatus();
    }
}
