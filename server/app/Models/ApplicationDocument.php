<?php

namespace App\Models;

use App\Enums\DocumentVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id', 'file_path', 'file_name', 'verified_by', 'verified_at',
    'uploaded_at', 'requirement_id', 'mime_type', 'file_size',
    'verification_status', 'rejection_reason',
])]
class ApplicationDocument extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => DocumentVerificationStatus::class,
            'file_size' => 'integer',
            'verified_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Mark this document as verified by the given staff member.
     *
     * A verified document can never carry a rejection reason; any previous
     * rejection reason is cleared so the record cannot end up in a
     * contradictory state.
     */
    public function verify(int $userId): void
    {
        $this->update([
            'verification_status' => DocumentVerificationStatus::Verified,
            'verified_by' => $userId,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject this document with a reason.
     */
    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'verification_status' => DocumentVerificationStatus::Rejected,
            'verified_by' => $userId,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
