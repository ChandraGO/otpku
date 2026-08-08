<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_media', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('mime_type', 100);
            $table->longText('data_base64');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media');
    }
};
