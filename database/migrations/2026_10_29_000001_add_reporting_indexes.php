<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['type', 'status'], 'items_type_status_index');
            $table->index(['status', 'created_at'], 'items_status_created_at_index');
            $table->index('created_at', 'items_created_at_index');
            $table->index('category', 'items_category_index');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->index('status', 'claims_status_index');
            $table->index('created_at', 'claims_created_at_index');
            $table->index('completed_at', 'claims_completed_at_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->index('created_at', 'reports_created_at_index');
            $table->index('reason', 'reports_reason_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_at_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_created_at_index');
            $table->dropIndex('reports_reason_index');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex('claims_status_index');
            $table->dropIndex('claims_created_at_index');
            $table->dropIndex('claims_completed_at_index');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_type_status_index');
            $table->dropIndex('items_status_created_at_index');
            $table->dropIndex('items_created_at_index');
            $table->dropIndex('items_category_index');
        });
    }
};
