<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('review');
            $table->timestamps();
            $table->index(['rating', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
