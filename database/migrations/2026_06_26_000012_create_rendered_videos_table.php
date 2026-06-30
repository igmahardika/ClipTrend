<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('rendered_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('render_job_id')->constrained('render_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('status')->default('ready')->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('rendered_videos');
    }
};
