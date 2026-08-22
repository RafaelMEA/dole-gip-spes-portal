<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * A student resubmitted a corrected application for review.
 */
class ApplicationResubmitted
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
