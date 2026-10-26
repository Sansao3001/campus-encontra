<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('email');
            $table->timestamp('suspended_at')->nullable()->after('password');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');

            $table->index('role');
            $table->index('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['role', 'suspended_at', 'suspension_reason']);
        });
    }
};
