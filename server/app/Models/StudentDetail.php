<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'school_name', 'course', 'year_level', 'gwa',
    'is_indigent', 'address', 'birthplace', 'birthdate', 'sex',
    'is_4ps_member',
])]
class StudentDetail extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'gwa' => 'float',
            'is_indigent' => 'boolean',
            'is_4ps_member' => 'boolean',
            'birthdate' => 'date',
        ];
    }
}
