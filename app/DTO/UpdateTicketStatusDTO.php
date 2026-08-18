<?php

namespace App\DTO;

final class UpdateTicketStatusDTO
{
    public function __construct(
        public readonly int $statusId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            statusId: (int) $data['status_id'],
        );
    }
}
