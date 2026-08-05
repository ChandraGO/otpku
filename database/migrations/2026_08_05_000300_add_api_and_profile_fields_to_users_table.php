<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_id', 100)->nullable()->after('whatsapp');
            $table->foreignId('default_country_id')->nullable()->after('telegram_id')->constrained('sms_countries')->nullOnDelete();
            $table->text('api_key')->nullable()->after('theme');
            $table->char('api_key_hash', 64)->nullable()->unique()->after('api_key');
            $table->timestamp('api_key_created_at')->nullable()->after('api_key_hash');
            $table->timestamp('deletion_requested_at')->nullable()->index()->after('last_login_at');
            $table->text('deletion_request_reason')->nullable()->after('deletion_requested_at');
            $table->string('deletion_request_status', 20)->nullable()->index()->after('deletion_request_reason');
            $table->timestamp('deletion_reviewed_at')->nullable()->after('deletion_request_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['default_country_id']);
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn([
                'telegram_id',
                'default_country_id',
                'api_key',
                'api_key_hash',
                'api_key_created_at',
                'deletion_requested_at',
                'deletion_request_reason',
                'deletion_request_status',
                'deletion_reviewed_at',
            ]);
        });
    }
};
