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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->string('trial_type')->nullable()->comment('free, paid, setup_fee');
            $table->decimal('trial_amount_paid', 10, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->timestamp('trial_started_at')->nullable();
            $table->boolean('trial_converted')->default(false);
            $table->json('trial_metadata')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
