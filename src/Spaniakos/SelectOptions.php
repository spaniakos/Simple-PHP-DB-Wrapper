<?php

declare(strict_types=1);

namespace Spaniakos;

use InvalidArgumentException;

final readonly class SelectOptions
{
    /**
     * @param array<string, SortDirection> $order
     */
    public function __construct(
        public array $order = [],
        public ?int $limit = null,
        public ?int $offset = null
    ) {
        foreach ($this->order as $column => $direction) {
            if (!is_string($column) || $column === '' || !$direction instanceof SortDirection) {
                throw new InvalidArgumentException('Order must map column names to SortDirection cases.');
            }
        }

        if ($this->limit !== null && $this->limit < 0) {
            throw new InvalidArgumentException('limit must be a non-negative integer.');
        }

        if ($this->offset !== null && $this->offset < 0) {
            throw new InvalidArgumentException('offset must be a non-negative integer.');
        }

        if ($this->offset !== null && $this->limit === null) {
            throw new InvalidArgumentException('offset requires a limit.');
        }
    }
}
