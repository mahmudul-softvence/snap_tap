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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('trial_type')->nullable()->comment('free, paid, setup_fee');
            $table->decimal('trial_amount_paid', 10, 2)->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->boolean('trial_converted')->default(false);
            $table->json('trial_metadata')->nullable();
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            //
        });
    }
};
