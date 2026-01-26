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
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('page_id', 191);
            $table->string('review_id', 191);
            $table->string('reply_id')->unique();
            $table->enum('reply_type', ['ai_reply', 'manual_reply'])->default('manual_reply');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['provider', 'page_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_replies');
    }
};
