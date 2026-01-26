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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('status')->default('sent');
            $table->longText('message');
            $table->string('provider');
            $table->boolean('sent_sms');
            $table->boolean('sent_email');
            $table->integer('retries')->default(0);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('unique_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
