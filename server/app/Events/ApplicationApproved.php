<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * Staff approved an application.
 */
class ApplicationApproved
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
