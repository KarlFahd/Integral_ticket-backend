<?php

namespace App\DTO;

final class UpdateTicketStatusDTO
{
    public function __construct(
        public readonly string $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
        );
    }
}
