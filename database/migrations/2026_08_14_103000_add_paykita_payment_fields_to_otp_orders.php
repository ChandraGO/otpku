<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('otp_orders', function (Blueprint $table): void {
            $table->string('payment_channel', 20)->default('balance')->after('sell_price')->index();
            $table->string('payment_status', 20)->default('paid')->after('payment_channel')->index();
            $table->string('paykita_order_id')->nullable()->unique()->after('payment_status');
            $table->decimal('payment_base_amount', 18, 2)->nullable()->after('paykita_order_id');
            $table->decimal('payment_fee_amount', 18, 2)->default(0)->after('payment_base_amount');
            $table->unsignedInteger('payment_unique_code')->default(0)->after('payment_fee_amount');
            $table->decimal('payment_pay_amount', 18, 2)->nullable()->after('payment_unique_code');
            $table->longText('payment_qris')->nullable()->after('payment_pay_amount');
            $table->text('payment_checkout_url')->nullable()->after('payment_qris');
            $table->longText('payment_payload')->nullable()->after('payment_checkout_url');
            $table->timestamp('payment_expires_at')->nullable()->index()->after('payment_payload');
            $table->timestamp('payment_paid_at')->nullable()->after('payment_expires_at');
        });

        DB::table('otp_orders')->update([
            'payment_channel' => 'balance',
            'payment_status' => 'paid',
        ]);

        if (Schema::hasTable('topups') && Schema::hasColumn('topups', 'gateway')) {
            DB::table('topups')
                ->whereIn('status', ['creating', 'pending'])
                ->where('gateway', '!=', 'paykita')
                ->update(['status' => 'expired', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('otp_orders', function (Blueprint $table): void {
            $table->dropUnique(['paykita_order_id']);
            $table->dropIndex(['payment_channel']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn([
                'payment_channel','payment_status','paykita_order_id','payment_base_amount','payment_fee_amount',
                'payment_unique_code','payment_pay_amount','payment_qris','payment_checkout_url','payment_payload',
                'payment_expires_at','payment_paid_at',
            ]);
        });
    }
};
