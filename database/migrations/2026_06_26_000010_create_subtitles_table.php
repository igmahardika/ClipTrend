<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('subtitles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->foreignId('clip_id')->constrained()->cascadeOnDelete();
            $table->string('language')->default('id');
            $table->json('segments')->nullable();
            $table->json('style')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->unique(['clip_id', 'language']);
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('subtitles');
    }
};
