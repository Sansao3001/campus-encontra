<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->timestamp('moderated_at')->nullable()->after('status');
            $table->text('moderation_reason')->nullable()->after('moderated_at');
            $table->foreignId('moderated_by_id')
                ->nullable()
                ->after('moderation_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('moderated_at');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['moderated_at']);
            $table->dropConstrainedForeignId('moderated_by_id');
            $table->dropColumn(['moderated_at', 'moderation_reason']);
        });
    }
};
