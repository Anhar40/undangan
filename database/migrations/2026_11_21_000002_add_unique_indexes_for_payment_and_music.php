<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique indexes must be safe on installations that already contain data.
     * Keep the oldest row for duplicate music paths, and clear only the extra
     * idempotency keys so transaction history is never deleted.
     */
    public function up(): void
    {
        $this->deduplicate('musics', 'file_path', false);
        $this->deduplicate('transactions', 'idempotency_key', true);

        Schema::table('musics', function (Blueprint $table): void {
            $table->unique('file_path');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
        });

        Schema::table('musics', function (Blueprint $table): void {
            $table->dropUnique(['file_path']);
        });
    }

    private function deduplicate(string $table, string $column, bool $nullable): void
    {
        $query = DB::table($table)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');

        if ($nullable) {
            $query->whereNotNull($column);
        }

        foreach ($query->pluck($column) as $value) {
            $ids = DB::table($table)
                ->where($column, $value)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1)
                ->all();

            if ($ids === []) {
                continue;
            }

            if ($nullable) {
                // A NULL idempotency key simply disables dedupe for that old
                // row; the transaction itself and all its history are kept.
                DB::table($table)->whereIn('id', $ids)->update([$column => null]);
            } else {
                // Duplicate music entries point at the same file and are
                // redundant. Removing them only clears invitation references.
                DB::table($table)->whereIn('id', $ids)->delete();
            }
        }
    }
};
