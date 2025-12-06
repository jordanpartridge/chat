<?php

declare(strict_types=1);

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
        Schema::table('chats', function (Blueprint $table): void {
            $table->index('updated_at');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->index('created_at');
        });

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table): void {
            $table->dropIndex(['updated_at']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropIndex(['enabled']);
        });
    }
};
