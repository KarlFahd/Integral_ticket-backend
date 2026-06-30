<?php

namespace App\DTO;

final class StoreMessageDTO
{
    public function __construct(
        public readonly string $sender,
        public readonly bool $isAgent,
        public readonly string $message,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sender:  (string) $data['sender'],
            isAgent: (bool) $data['is_agent'],
            message: (string) $data['message'],
        );
    }
}
