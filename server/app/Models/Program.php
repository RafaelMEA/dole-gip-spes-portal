<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'is_active',
])]
class Program extends Model
{
    use HasFactory;

    public function programCycles(): HasMany
    {
        return $this->hasMany(ProgramCycle::class);
    }
}
