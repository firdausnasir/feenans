<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

abstract class DomainException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        protected ?string $safeMessage = null,
        protected array $context = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function codeName(): string
    {
        return $this->type();
    }

    public function type(): string
    {
        return 'domain_error';
    }

    public function safeMessage(): string
    {
        return $this->safeMessage ?? $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
