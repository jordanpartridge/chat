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
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->text('system_prompt')->nullable();
            $table->string('avatar')->nullable();
            $table->json('tools')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['default_model_id']);
            $table->dropColumn([
                'user_id',
                'default_model_id',
                'system_prompt',
                'avatar',
                'tools',
                'capabilities',
                'is_active',
            ]);
        });
    }
};
