<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('uploaded_video_id')->constrained('uploaded_videos')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('start_time', 10, 2);
            $table->decimal('end_time', 10, 2);
            $table->decimal('duration_seconds', 10, 2);
            $table->text('hook_text')->nullable();
            $table->text('transcript_excerpt')->nullable();
            $table->decimal('retention_score', 5, 2)->default(0);
            $table->decimal('viral_score', 5, 2)->default(0);
            $table->json('platform_fit')->nullable();
            $table->string('status')->default('candidate')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('clips');
    }
};
