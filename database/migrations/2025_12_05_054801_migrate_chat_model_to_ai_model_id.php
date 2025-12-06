<?php

declare(strict_types=1);

use App\Models\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing chats to use ai_model_id
        $chats = DB::table('chats')->whereNotNull('model')->get();

        foreach ($chats as $chat) {
            $aiModel = AiModel::where('model_id', $chat->model)->first();

            if ($aiModel !== null) {
                DB::table('chats')
                    ->where('id', $chat->id)
                    ->update(['ai_model_id' => $aiModel->id]);
            }
        }

        // Drop the old model column
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }

    public function down(): void
    {
        // Re-add the model column
        Schema::table('chats', function (Blueprint $table) {
            $table->string('model')->nullable()->after('title');
        });

        // Migrate back from ai_model_id to model string
        $chats = DB::table('chats')->whereNotNull('ai_model_id')->get();

        foreach ($chats as $chat) {
            $aiModel = AiModel::find($chat->ai_model_id);

            if ($aiModel !== null) {
                DB::table('chats')
                    ->where('id', $chat->id)
                    ->update(['model' => $aiModel->model_id]);
            }
        }
    }
};
