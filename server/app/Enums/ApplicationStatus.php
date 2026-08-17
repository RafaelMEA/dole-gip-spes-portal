<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocumentsIncomplete = 'documents_incomplete';
    case DocumentsVerified = 'documents_verified';
    case ReturnedForCorrection = 'returned_for_correction';
    case Approved = 'approved';
    case ForDeployment = 'for_deployment';
    case Deployed = 'deployed';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Statuses that a student may move the application to on their own.
     */
    public function isStudentControlled(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Submitted,
            self::ReturnedForCorrection,
            self::Withdrawn,
        ], true);
    }

    /**
     * Whether the application is still active (not terminal).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn, self::Completed], true);
    }

    /**
     * Whether the application is in a student-editable state.
     */
    public function isEditableByStudent(): bool
    {
        return in_array($this, [
            self::Draft,
            self::ReturnedForCorrection,
        ], true);
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::DocumentsIncomplete => 'Documents Incomplete',
            self::DocumentsVerified => 'Documents Verified',
            self::ReturnedForCorrection => 'Correction Required',
            self::Approved => 'Approved',
            self::ForDeployment => 'For Deployment',
            self::Deployed => 'Deployed',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
