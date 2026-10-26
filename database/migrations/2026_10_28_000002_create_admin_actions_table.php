<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 80);
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
