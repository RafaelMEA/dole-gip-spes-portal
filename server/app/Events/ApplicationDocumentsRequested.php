<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;

/**
 * Staff requested additional or corrected documents from the student.
 */
class ApplicationDocumentsRequested
{
    public function __construct(
        public readonly Application $application,
        public readonly User $actor,
    ) {}
}
