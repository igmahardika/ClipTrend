<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('trend_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('video_projects')->nullOnDelete();
            $table->string('niche')->nullable()->index();
            $table->string('topic')->nullable()->index();
            $table->string('platform')->default('tiktok')->index();
            $table->string('region', 10)->default('ID')->index();
            $table->decimal('score', 5, 2)->default(0);
            $table->json('hashtags')->nullable();
            $table->json('angles')->nullable();
            $table->json('hooks')->nullable();
            $table->json('captions')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('trend_reports');
    }
};
