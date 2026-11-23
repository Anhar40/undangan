<?php

namespace App\Services\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RetriesDeadlocks
{
    /**
     * InnoDB can abort a transaction with 1213 (deadlock) or 1205 (lock wait
     * timeout) under concurrent checkout/webhook traffic. Both are safe to
     * retry because the transaction is rolled back before the exception.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function transactionWithRetry(Closure $callback, int $attempts = 3): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction($callback);
            } catch (QueryException $exception) {
                if (! $this->isRetryableLockError($exception) || $attempt >= $attempts) {
                    throw $exception;
                }

                Log::warning('database.lock_retry', [
                    'attempt' => $attempt,
                    'sql_state' => $exception->getCode(),
                    'error_code' => $exception->errorInfo[1] ?? null,
                ]);

                usleep(random_int(20000, 80000) * $attempt);
            }
        }
    }

    private function isRetryableLockError(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '40001'
            || in_array((string) ($exception->errorInfo[1] ?? ''), ['1213', '1205'], true);
    }
}
