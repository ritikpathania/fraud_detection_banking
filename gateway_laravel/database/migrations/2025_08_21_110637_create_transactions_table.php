<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->index();
            $table->string('account')->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8);
            $table->timestamp('timestamp')->nullable();
            $table->decimal('fraud_score', 4, 2)->nullable();
            $table->boolean('is_fraud')->default(false);
            $table->json('reasons')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
