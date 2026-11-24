<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /** @param array<int,array<string,mixed>> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage
    ) {
        $this->perPage = max(1, $this->perPage);
        $this->currentPage = max(1, $this->currentPage);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->currentPage - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->currentPage * $this->perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
            'from' => $this->from(),
            'to' => $this->to(),
        ];
    }
}
