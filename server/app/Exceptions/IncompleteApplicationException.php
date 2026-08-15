<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when a student tries to submit an application that does not yet
 * satisfy the completeness rules. Carries the structured missing information
 * so the API can respond with a useful, actionable payload.
 */
class IncompleteApplicationException extends DomainException
{
    /**
     * @param  array<int, string>  $missingApplicationFields
     * @param  array<int, array{id: int, name: string, is_required: bool}>  $missingRequirements
     */
    public function __construct(
        private readonly array $missingApplicationFields = [],
        private readonly array $missingRequirements = [],
    ) {
        parent::__construct($this->buildMessage());
    }

    /**
     * @return array<int, string>
     */
    public function missingApplicationFields(): array
    {
        return $this->missingApplicationFields;
    }

    /**
     * @return array<int, array{id: int, name: string, is_required: bool}>
     */
    public function missingRequirements(): array
    {
        return $this->missingRequirements;
    }

    public function buildMessage(): string
    {
        $parts = [];

        if ($this->missingRequirements !== []) {
            $parts[] = 'Missing required documents: '.implode(', ', array_column($this->missingRequirements, 'name'));
        }

        if ($this->missingApplicationFields !== []) {
            $parts[] = 'Required application information is incomplete: '.implode(', ', $this->missingApplicationFields);
        }

        return 'Your application cannot be submitted yet. '.implode(' ', $parts);
    }
}
