<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, clear existing seeded models - they'll come from API now
        DB::table('ai_models')->delete();

        Schema::table('ai_models', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['provider', 'model_id']);

            // Remove columns that are now redundant
            $table->dropColumn(['provider', 'is_available']);

            // Add credential relationship with cascade delete
            $table->foreignId('user_api_credential_id')
                ->after('id')
                ->constrained('user_api_credentials')
                ->cascadeOnDelete();

            // New unique: each model_id unique per credential
            $table->unique(['user_api_credential_id', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropForeign(['user_api_credential_id']);
            $table->dropUnique(['user_api_credential_id', 'model_id']);
            $table->dropColumn('user_api_credential_id');

            $table->string('provider')->after('name');
            $table->boolean('is_available')->default(false);
            $table->unique(['provider', 'model_id']);
        });
    }
};
