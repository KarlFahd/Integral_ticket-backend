<?php

namespace App\DTO;

final class StoreTicketDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $createdBy,
        public readonly ?string $attachment = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            category: $data['category'],
            priority: $data['priority'],
            createdBy: $data['created_by'],
            attachment: $data['attachment'] ?? null,
        );
    }
}
