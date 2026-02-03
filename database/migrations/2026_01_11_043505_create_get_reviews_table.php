<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('get_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page_id');
            $table->string('provider');
            $table->string('provider_review_id')->unique();
            $table->string('open_graph_story_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_image')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->longText('review_text')->nullable();
            $table->enum('status', ['pending', 'replied', 'ai_replied'])->default('pending');
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('reviewed_at')->nullable();
            $table->string('review_reply_id')->nullable();
            $table->text('review_reply_text')->nullable();
            $table->string('replied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('get_reviews');
    }
};
