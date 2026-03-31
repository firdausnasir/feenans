<?php

namespace App\Exceptions\Domain;

class DomainConflict extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'domain_conflict';
    }
}
