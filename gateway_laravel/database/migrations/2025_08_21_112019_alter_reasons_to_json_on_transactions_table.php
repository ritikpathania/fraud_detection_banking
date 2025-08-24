<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('transactions', function (Blueprint $table) {
            // For MySQL 5.7+/MariaDB 10.2+: JSON supported
            $table->json('reasons')->nullable()->change();
            $table->string('risk_category')->nullable();

            // If Postgres, JSONB is fine too:
            // $table->jsonb('reasons')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('reasons')->nullable()->change();
        });
    }

};
