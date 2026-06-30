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
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->nullable();
            $table->text('question');
            $table->text('answer');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->string('source', 32)->default('kb');
            $table->string('provider', 32)->nullable();
            $table->string('status', 32)->default('answered');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
