<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('purpose', 30)->index();
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['email', 'purpose', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40)->index();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type', 20)->default('info');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_countries', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_id')->unique();
            $table->string('name');
            $table->string('iso_code', 8)->nullable()->index();
            $table->string('dial_code', 12)->nullable();
            $table->text('flag_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('provider_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_services', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_id')->unique();
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->text('icon_url')->nullable();
            $table->decimal('min_provider_price', 18, 2)->nullable();
            $table->decimal('max_provider_price', 18, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('provider_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_service_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_service_id')->constrained()->cascadeOnDelete();
            $table->string('provider_price_id')->unique();
            $table->string('provider_operator_id')->nullable()->index();
            $table->string('operator_name')->nullable();
            $table->decimal('provider_price', 18, 2);
            $table->decimal('sell_price', 18, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('success_rate', 8, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('provider_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['sms_country_id', 'sms_service_id']);
        });

        Schema::create('otp_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_service_price_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('provider_activation_id')->nullable()->unique();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_price_id')->nullable()->index();
            $table->string('provider_operator_id')->nullable()->index();
            $table->string('service_name');
            $table->string('country_name');
            $table->string('operator_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('otp_code')->nullable();
            $table->decimal('provider_cost', 18, 2);
            $table->decimal('sell_price', 18, 2);
            $table->string('status', 40)->default('processing')->index();
            $table->smallInteger('provider_order_status')->nullable();
            $table->smallInteger('provider_activation_status')->nullable();
            $table->text('provider_message')->nullable();
            $table->longText('provider_payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('otp_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('topups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->default(0);
            $table->decimal('total_payment', 18, 2);
            $table->string('payment_method', 40);
            $table->longText('payment_number')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->longText('provider_payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_key')->unique();
            $table->string('direction', 10)->index();
            $table->string('category', 30)->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('api_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->index();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('successful')->default(false)->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_meta')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });

        Schema::create('database_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename')->unique();
            $table->string('disk')->default('backups');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('source', 20)->default('generated');
            $table->string('status', 20)->default('ready');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('topups');
        Schema::dropIfExists('otp_orders');
        Schema::dropIfExists('sms_service_prices');
        Schema::dropIfExists('sms_services');
        Schema::dropIfExists('sms_countries');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('email_otps');
    }
};
