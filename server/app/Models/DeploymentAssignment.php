<?php

namespace App\Models;

use App\Enums\DeploymentAssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id', 'deployment_slot_id', 'host_agency_id', 'start_date', 'end_date',
    'deployment_site_id', 'position', 'status', 'assigned_by',
    'assigned_at', 'remarks',
])]
class DeploymentAssignment extends Model
{
    use HasFactory;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function deploymentSlot(): BelongsTo
    {
        return $this->belongsTo(DeploymentSlot::class);
    }

    public function hostAgency(): BelongsTo
    {
        return $this->belongsTo(HostAgency::class);
    }

    public function deploymentSite(): BelongsTo
    {
        return $this->belongsTo(DeploymentSite::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeploymentAssignmentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'assigned_at' => 'datetime',
        ];
    }
}
