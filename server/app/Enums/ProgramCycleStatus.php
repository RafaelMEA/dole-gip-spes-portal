<?php

namespace App\Enums;

enum ProgramCycleStatus: string
{
    case Draft = 'draft';
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    /**
     * Whether students can currently create/submit applications.
     */
    public function isAcceptingApplications(): bool
    {
        return $this === self::Open;
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Upcoming => 'Upcoming',
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }
}
