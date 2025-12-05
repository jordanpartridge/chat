<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // ollama, groq, openai, anthropic, etc.
            $table->string('model_id'); // llama3:8b, gpt-4, claude-3-opus, etc.
            $table->integer('context_window')->default(4096);
            $table->boolean('supports_tools')->default(false);
            $table->boolean('supports_vision')->default(false);
            $table->string('speed_tier')->default('medium'); // fast, medium, slow
            $table->string('cost_tier')->default('low'); // low, medium, high
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
