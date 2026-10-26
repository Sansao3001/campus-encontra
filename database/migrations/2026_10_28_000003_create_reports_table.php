<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('target_type', 20);
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items')
                ->nullOnDelete();
            $table->foreignId('reported_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('resolution_note')->nullable();

            $table->foreignId('handled_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'item_id']);
            $table->index(['target_type', 'reported_user_id']);
            $table->index(['reporter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
