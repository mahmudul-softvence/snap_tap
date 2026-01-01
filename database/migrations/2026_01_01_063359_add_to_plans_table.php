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
        Schema::table('plans', function (Blueprint $table) {
          $table->boolean('allow_trial')->default(true);
          $table->decimal('setup_fee', 10, 2)->nullable()->comment('One-time fee for trial plans');
          $table->string('trial_type')->default('free')->comment('free, paid, setup_fee');
          $table->boolean('auto_activate_after_trial')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            //
        });
    }
};
