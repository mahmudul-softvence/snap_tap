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
        Schema::table('basic_settings', function (Blueprint $table) {
            $table->boolean('auto_ai_reply')->after('auto_request_auto')->default(false);
            $table->boolean('auto_ai_review_request')->after('auto_ai_reply')->default(false);
            $table->boolean('multi_language_ai')->after('auto_ai_review_request')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('basic_settings', function (Blueprint $table) {
            //
        });
    }
};
