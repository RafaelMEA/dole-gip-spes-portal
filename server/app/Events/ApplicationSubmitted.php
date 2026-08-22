<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * A student submitted a brand-new application for review.
 *
 * Dispatched from inside the workflow transaction so notification listeners
 * participate in it: if the state change rolls back, so do its notifications.
 */
class ApplicationSubmitted
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
