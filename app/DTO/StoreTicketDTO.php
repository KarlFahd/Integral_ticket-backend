<?php

namespace App\DTO;

final class StoreTicketDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly int $categoryId,
        public readonly int $priorityId,
        public readonly string $createdBy,
        public readonly ?string $attachment = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            categoryId: (int) $data['category_id'],
            priorityId: (int) $data['priority_id'],
            createdBy: $data['created_by'],
            attachment: $data['attachment'] ?? null,
        );
    }
}
