<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReorderPositionsService
{
    /**
     * @param  array<int, array{id: int, position: int}>  $items
     */
    public function __invoke(string $table, int $ledgerId, array $items): void
    {
        if ($items === []) {
            return;
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Unsafe table name provided.');
        }

        $cases = [];
        $bindings = [];
        $ids = [];

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $position = (int) $item['position'];

            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $position;
            $ids[] = $id;
        }

        $idPlaceholders = implode(', ', array_fill(0, count($ids), '?'));

        DB::update(
            sprintf(
                'UPDATE %s SET position = CASE id %s END WHERE ledger_id = ? AND id IN (%s)',
                $table,
                implode(' ', $cases),
                $idPlaceholders,
            ),
            [...$bindings, $ledgerId, ...$ids],
        );
    }
}
