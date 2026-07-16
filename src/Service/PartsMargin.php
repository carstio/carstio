<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Internal-only summary of how much the workshop is making on parts
 * resold inside one document (offer or work order).
 *
 * All amounts are decimal strings with 2 fraction digits. {@see $marginPercent}
 * is null when the sell total is zero - we never divide by zero, and the UI
 * shows a placeholder instead of "0%" or "Infinity".
 *
 * Cost / profit numbers must never be rendered on client-facing PDFs.
 */
final class PartsMargin
{
    public function __construct(
        public readonly string $costTotal,
        public readonly string $sellTotal,
        public readonly string $profit,
        public readonly ?string $marginPercent,
        public readonly bool $hasCostData,
    ) {
    }
}
