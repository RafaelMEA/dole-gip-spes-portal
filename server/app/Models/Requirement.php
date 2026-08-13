<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'allowed_file_types', 'max_file_size', 'slug', 'is_active',
])]
class Requirement extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_file_types' => 'array',
            'max_file_size' => 'integer',
        ];
    }

    public function programCycles(): BelongsToMany
    {
        return $this->belongsToMany(ProgramCycle::class, 'program_cycle_requirements')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }
}
