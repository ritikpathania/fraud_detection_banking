<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['sqlite','pgsql'])) {
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_account_index     ON transactions (account)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_is_fraud_index    ON transactions (is_fraud)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_timestamp_index   ON transactions (timestamp)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_is_fraud_timestamp_index ON transactions (is_fraud, timestamp)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_amount_index     ON transactions (amount)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_fraud_score_index ON transactions (fraud_score)');
                DB::statement('CREATE INDEX IF NOT EXISTS transactions_created_at_index ON transactions (created_at)');
            } else {
                try { DB::statement('CREATE INDEX transactions_account_index ON transactions (account)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_is_fraud_index ON transactions (is_fraud)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_timestamp_index ON transactions (timestamp)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_is_fraud_timestamp_index ON transactions (is_fraud, timestamp)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_amount_index ON transactions (amount)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_fraud_score_index ON transactions (fraud_score)'); } catch (\Throwable $e) {}
                try { DB::statement('CREATE INDEX transactions_created_at_index ON transactions (created_at)'); } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS transactions_account_index');
            DB::statement('DROP INDEX IF EXISTS transactions_is_fraud_index');
            DB::statement('DROP INDEX IF EXISTS transactions_timestamp_index');
            DB::statement('DROP INDEX IF EXISTS transactions_is_fraud_timestamp_index');
            DB::statement('DROP INDEX IF EXISTS transactions_amount_index');
            DB::statement('DROP INDEX IF EXISTS transactions_fraud_score_index');
            DB::statement('DROP INDEX IF EXISTS transactions_created_at_index');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS transactions_account_index');
            DB::statement('DROP INDEX IF EXISTS transactions_is_fraud_index');
            DB::statement('DROP INDEX IF EXISTS transactions_timestamp_index');
            DB::statement('DROP INDEX IF EXISTS transactions_is_fraud_timestamp_index');
            DB::statement('DROP INDEX IF EXISTS transactions_amount_index');
            DB::statement('DROP INDEX IF EXISTS transactions_fraud_score_index');
            DB::statement('DROP INDEX IF EXISTS transactions_created_at_index');
        } else {
            try { DB::statement('DROP INDEX transactions_account_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_is_fraud_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_timestamp_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_is_fraud_timestamp_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_amount_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_fraud_score_index ON transactions'); } catch (\Throwable $e) {}
            try { DB::statement('DROP INDEX transactions_created_at_index ON transactions'); } catch (\Throwable $e) {}
        }
    }
};
