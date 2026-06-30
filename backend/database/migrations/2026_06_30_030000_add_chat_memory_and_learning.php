<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->text('original_question')->nullable()->after('session_id');
            $table->text('improved_question')->nullable()->after('original_question');
            $table->json('context_snapshot')->nullable()->after('improved_question');
            $table->boolean('was_improved')->default(false)->after('context_snapshot');
            $table->boolean('learned')->default(false)->after('was_improved');
        });

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('messages')->nullable();
            $table->unsignedInteger('turn_count')->default(0);
            $table->timestamps();

            $table->index('updated_at');
        });

        Schema::create('learned_qas', function (Blueprint $table) {
            $table->id();
            $table->string('topic_id', 64)->nullable();
            $table->text('question');
            $table->text('answer');
            $table->json('keywords')->nullable();
            $table->unsignedInteger('hit_count')->default(1);
            $table->string('source', 32)->default('auto');
            $table->timestamps();

            $table->index('hit_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_qas');
        Schema::dropIfExists('chat_sessions');

        Schema::table('chat_logs', function (Blueprint $table) {
            $table->dropColumn([
                'original_question',
                'improved_question',
                'context_snapshot',
                'was_improved',
                'learned',
            ]);
        });
    }
};
