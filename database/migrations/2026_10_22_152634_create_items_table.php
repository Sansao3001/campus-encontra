
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('title');
            $table->string('category');
            $table->text('description');
            $table->string('location');
            $table->date('occurrence_date');

            $table->string('photo_path')->nullable();

            $table->enum('type', ['lost', 'found']);
            $table->enum('status', ['open', 'pending', 'returned'])
                  ->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
