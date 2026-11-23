<?php

namespace App\Database\Connections;

use DateTimeInterface;
use Illuminate\Database\PostgresConnection;

class NeonPostgresConnection extends PostgresConnection
{
    /**
     * Keep boolean bindings as booleans for PostgreSQL.
     *
     * Base Connection converts bools to int (0/1), which breaks PostgreSQL
     * boolean columns when PDO::ATTR_EMULATE_PREPARES is enabled for
     * Neon/PgBouncer transaction mode.
     */
    public function prepareBindings(array $bindings): array
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                // PostgreSQL boolean columns reject integer literals (1/0).
                // With PDO emulated prepares, PHP bools can be inlined as 1/empty.
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }
}
