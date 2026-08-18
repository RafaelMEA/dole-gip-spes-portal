<?php

namespace App\Models;

use App\Enums\HostAgencyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'agency_type', 'address', 'contact_person', 'contact_number', 'email', 'is_active',
])]
class HostAgency extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'agency_type' => HostAgencyType::class,
            'is_active' => 'boolean',
        ];
    }

    public function deploymentSites(): HasMany
    {
        return $this->hasMany(DeploymentSite::class);
    }

    public function deploymentAssignments(): HasMany
    {
        return $this->hasMany(DeploymentAssignment::class);
    }
}
