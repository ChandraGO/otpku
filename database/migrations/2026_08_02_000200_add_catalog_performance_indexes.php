<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_service_prices', function (Blueprint $table): void {
            $table->index(
                ['sms_service_id', 'is_active', 'stock', 'sell_price'],
                'ssp_service_active_stock_price_idx',
            );
            $table->index(
                ['sms_country_id', 'is_active', 'stock', 'sell_price'],
                'ssp_country_active_stock_price_idx',
            );
        });

        Schema::table('otp_orders', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'status'],
                'otp_orders_created_status_idx',
            );
        });

        Schema::table('topups', function (Blueprint $table): void {
            $table->index(
                ['status', 'created_at'],
                'topups_status_created_idx',
            );
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(
                ['role', 'status'],
                'users_role_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sms_service_prices', function (Blueprint $table): void {
            $table->dropIndex('ssp_service_active_stock_price_idx');
            $table->dropIndex('ssp_country_active_stock_price_idx');
        });

        Schema::table('otp_orders', function (Blueprint $table): void {
            $table->dropIndex('otp_orders_created_status_idx');
        });

        Schema::table('topups', function (Blueprint $table): void {
            $table->dropIndex('topups_status_created_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_status_idx');
        });
    }
};
