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
        Schema::create('basic_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('msg_after_checkin')->nullable()->default(1);
            $table->boolean('next_message_time')->nullable()->default(5);
            $table->boolean('re_try_time')->nullable()->default(5);

            $table->boolean('new_customer_review')->nullable()->default(false);
            $table->boolean('ai_reply')->nullable()->default(false);
            $table->boolean('ai_review_reminder')->nullable()->default(false);
            $table->boolean('customer_review')->nullable()->default(false);
            $table->boolean('renewel_reminder')->nullable()->default(false);
            $table->boolean('timezone')->nullable()->default(false);
            $table->boolean('auto_request_auto')->nullable()->default(false);
            $table->dateTime('review_sent_time')->nullable();
            $table->string('lang')->nullable()->default('en');
            $table->string('date_format')->nullable()->default('dd/mm/yyyy');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basic_settings');
    }
};
