<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'address', 'city', 'region', 'is_active',
])]
class DeploymentSite extends Model
{
    use HasFactory;

    public function deploymentAssignments(): HasMany
    {
        return $this->hasMany(DeploymentAssignment::class);
    }
}
