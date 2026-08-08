<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('topups', function (Blueprint $table): void {
            $table->string('cancel_reason', 60)->nullable()->after('status')->index();
            $table->text('cancel_note')->nullable()->after('cancel_reason');
            $table->timestamp('cancelled_at')->nullable()->after('credited_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('topups', function (Blueprint $table): void {
            $table->dropIndex(['cancel_reason']);
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancel_reason', 'cancel_note', 'cancelled_at']);
        });
    }
};
