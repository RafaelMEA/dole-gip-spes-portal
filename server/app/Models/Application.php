<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'applicant_id', 'program_cycle_id', 'status', 'remarks',
    'submitted_at', 'approved_at', 'approved_by',
    'decision_reason', 'decided_by', 'decided_at',
])]
class Application extends Model
{
    use HasFactory;

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function programCycle(): BelongsTo
    {
        return $this->belongsTo(ProgramCycle::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function deploymentAssignment(): HasOne
    {
        return $this->hasOne(DeploymentAssignment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Submit the application: flip to submitted and log the change.
     */
    public function submit(?int $changedBy = null): void
    {
        $this->status = ApplicationStatus::Submitted;
        $this->submitted_at = now();
        $this->save();

        $this->logStatusChange($changedBy);
    }

    /**
     * Log a status transition in the history table.
     */
    public function logStatusChange(?int $changedBy = null, ?string $remarks = null): void
    {
        $this->statusHistory()->create([
            'status' => $this->status,
            'changed_by' => $changedBy,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }
}
