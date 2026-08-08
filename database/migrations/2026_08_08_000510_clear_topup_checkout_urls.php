<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // URL checkout lama tidak lagi diperlukan dan tidak boleh dipakai client.
        DB::table('topups')->whereNotNull('checkout_url')->update(['checkout_url' => null]);
    }

    public function down(): void
    {
        // Nilai lama sengaja tidak dipulihkan karena berisi URL checkout penyedia.
    }
};
