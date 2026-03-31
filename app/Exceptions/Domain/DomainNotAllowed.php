<?php

namespace App\Exceptions\Domain;

class DomainNotAllowed extends DomainException
{
    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return 'domain_not_allowed';
    }
}
