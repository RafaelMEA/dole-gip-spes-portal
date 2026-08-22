<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * Staff returned an application to the student for correction.
 */
class ApplicationReturnedForCorrection
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
