<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
                Schema::create('detected_niches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('video_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->json('signals')->nullable();
            $table->text('reasoning')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
                Schema::dropIfExists('detected_niches');
    }
};
