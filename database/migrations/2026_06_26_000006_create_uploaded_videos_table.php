<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('uploaded_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('video_projects')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('frame_rate')->nullable();
            $table->unsignedBigInteger('bitrate')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('uploaded_videos');
    }
};
