<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('video_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('source_type')->default('upload');
            $table->text('youtube_url')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('target_platforms')->nullable();
            $table->string('niche_detection_status')->default('pending')->index();
            $table->string('render_status')->default('not_started')->index();
            $table->decimal('total_duration_seconds', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('video_projects');
    }
};
