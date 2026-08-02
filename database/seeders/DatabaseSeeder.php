<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(['email' => strtolower((string) env('ADMIN_EMAIL', 'admin@example.com'))], [
            'username' => env('ADMIN_USERNAME', 'admin'),
            'name' => 'Administrator',
            'whatsapp' => env('ADMIN_WHATSAPP', '6280000000000'),
            'password' => env('ADMIN_PASSWORD', 'ChangeMe-Immediately-123!'),
            'role' => 'admin', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        Announcement::query()->firstOrCreate(['title' => 'Selamat datang di '.config('app.name')], [
            'created_by' => $admin->id, 'body' => 'Silakan periksa harga dan stok sebelum melakukan pemesanan. Gunakan nomor hanya untuk aktivitas yang sah dan sesuai ketentuan layanan tujuan.',
            'type' => 'info', 'is_active' => true, 'is_pinned' => true,
        ]);
    }
}
