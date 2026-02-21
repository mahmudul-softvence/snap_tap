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
        Schema::table('get_reviews', function (Blueprint $table) {
            $table->foreignId('user_business_account_id')
                ->after('user_id')
                ->nullable()
                ->constrained('user_business_accounts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('get_reviews', function (Blueprint $table) {
            $table->dropForeign(['user_business_account_id']);
            $table->dropColumn('user_business_account_id');
        });
    }
};
