<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('video_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('uploaded_video_id')->unique()->constrained('uploaded_videos')->cascadeOnDelete();
            $table->foreignId('detected_niche_id')->nullable()->constrained('detected_niches')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->string('main_topic')->nullable();
            $table->json('audience_profile')->nullable();
            $table->string('content_style')->nullable();
            $table->json('recommended_output')->nullable();
            $table->json('recommended_duration_seconds')->nullable();
            $table->decimal('ai_confidence', 5, 2)->default(0);
            $table->text('reasoning')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('video_analyses');
    }
};
