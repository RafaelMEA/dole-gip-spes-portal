<?php

namespace App\Enums;

enum HostAgencyType: string
{
    case Government = 'government';
    case Private = 'private';
    case Ngo = 'ngo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::Private => 'Private',
            self::Ngo => 'NGO',
            self::Other => 'Other',
        };
    }
}
