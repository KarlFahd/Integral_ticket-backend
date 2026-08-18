<?php

namespace App\Support;

/**
 * Assigns every user a calendar color deterministically from their id — no
 * database column, no admin step. The same user id always maps to the same
 * color, so the frontend never needs to compute or store this itself; it
 * only ever displays whatever hex string the API sends (see
 * UserResource::calendar_color and EventResource's nested creator).
 */
final class CalendarColor
{
    private const PALETTE = [
        '#7c3aed', '#0ea5e9', '#10b981', '#f59e0b',
        '#ef4444', '#ec4899', '#14b8a6', '#f97316',
    ];

    public static function forUserId(int $userId): string
    {
        return self::PALETTE[$userId % count(self::PALETTE)];
    }
}
