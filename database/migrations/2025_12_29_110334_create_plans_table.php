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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_price_id')->unique();
            $table->string('stripe_product_id');
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('usd');
            $table->string('interval')->default('month'); 
            $table->integer('interval_count')->default(1);
            $table->integer('trial_days')->default(30);
            $table->text('description')->nullable();
            $table->boolean('allow_trial')->default(true);
            $table->decimal('setup_fee', 10, 2)->nullable()->comment('One-time fee for trial plans');
            $table->string('trial_type')->default('free')->comment('free, paid, setup_fee');
            $table->boolean('auto_activate_after_trial')->default(true);
            $table->json('features')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
