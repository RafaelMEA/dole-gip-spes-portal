<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * Staff rejected an application.
 */
class ApplicationRejected
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
