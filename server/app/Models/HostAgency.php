<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'address', 'contact_person', 'contact_number', 'email', 'is_active',
])]
class HostAgency extends Model
{
    use HasFactory;

    public function deploymentAssignments(): HasMany
    {
        return $this->hasMany(DeploymentAssignment::class);
    }
}
