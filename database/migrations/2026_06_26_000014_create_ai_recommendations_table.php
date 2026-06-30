<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('clip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('ai_recommendations');
    }
};
