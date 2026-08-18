<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'host_agency_id', 'name', 'address', 'city', 'region',
    'contact_person', 'contact_number', 'email', 'description', 'is_active',
])]
class DeploymentSite extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    public function hostAgency(): BelongsTo
    {
        return $this->belongsTo(HostAgency::class);
    }

    public function deploymentAssignments(): HasMany
    {
        return $this->hasMany(DeploymentAssignment::class);
    }
}
