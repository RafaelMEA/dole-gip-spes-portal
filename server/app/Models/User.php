<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';

    public const ROLE_STAFF = 'staff';

    /**
     * The available roles for the application.
     *
     * @var array<int, string>
     */
    public const ROLES = [self::ROLE_STUDENT, self::ROLE_STAFF];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function studentDetail(): HasOne
    {
        return $this->hasOne(StudentDetail::class);
    }

    /**
     * Whether this user is a DOLE staff member.
     */
    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /**
     * Whether this user is a student applicant.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }
}
