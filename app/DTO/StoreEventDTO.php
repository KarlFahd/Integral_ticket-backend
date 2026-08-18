<?php

namespace App\DTO;

final class StoreEventDTO
{
    /** @param array<int> $participantUserIds */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly int $typeId,
        public readonly string $eventDate,
        public readonly string $startTime,
        public readonly ?string $endTime,
        public readonly int $createdByUserId,
        public readonly array $participantUserIds = [],
    ) {}

    /** @param array<int> $participantUserIds */
    public static function fromArray(array $data, int $createdByUserId, array $participantUserIds = []): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            typeId: (int) $data['type_id'],
            eventDate: $data['event_date'],
            startTime: $data['start_time'],
            endTime: $data['end_time'] ?? null,
            createdByUserId: $createdByUserId,
            participantUserIds: $participantUserIds,
        );
    }
}
