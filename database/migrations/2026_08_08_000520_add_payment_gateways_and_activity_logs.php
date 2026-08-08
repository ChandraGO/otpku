<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('topups', function (Blueprint $table): void {
            $table->string('gateway', 30)->default('pakasir')->after('order_id')->index();
            $table->string('provider_reference')->nullable()->after('payment_number')->index();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->string('subject_type', 40)->nullable()->index();
            $table->string('subject_id')->nullable();
            $table->string('gateway', 30)->nullable()->index();
            $table->string('status', 40)->nullable()->index();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['event', 'created_at']);
        });

        // Isi riwayat awal dari data yang sudah ada supaya halaman admin tidak
        // kosong setelah fitur activity log pertama kali di-deploy. Ini adalah
        // snapshot, sedangkan perubahan status setelah migration dicatat realtime.
        DB::table('topups')
            ->leftJoin('users', 'users.id', '=', 'topups.user_id')
            ->select('topups.*', 'users.email as user_email')
            ->orderBy('topups.created_at')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('activity_logs')->insert([
                        'user_id' => $row->user_id,
                        'actor_id' => null,
                        'event' => 'topup.snapshot',
                        'subject_type' => 'topup',
                        'subject_id' => (string) $row->id,
                        'gateway' => $row->gateway ?: 'pakasir',
                        'status' => $row->status,
                        'amount' => $row->amount,
                        'description' => sprintf(
                            'Riwayat awal: %s memiliki isi saldo %s sebesar Rp %s melalui %s.',
                            $row->user_email ?: 'Pengguna',
                            $row->order_id,
                            number_format((float) $row->amount, 0, ',', '.'),
                            ucfirst($row->gateway ?: 'pakasir'),
                        ),
                        'meta' => json_encode(['order_id' => $row->order_id, 'snapshot' => true]),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });

        DB::table('otp_orders')
            ->leftJoin('users', 'users.id', '=', 'otp_orders.user_id')
            ->select('otp_orders.*', 'users.email as user_email')
            ->orderBy('otp_orders.created_at')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('activity_logs')->insert([
                        'user_id' => $row->user_id,
                        'actor_id' => null,
                        'event' => 'order.snapshot',
                        'subject_type' => 'order',
                        'subject_id' => (string) $row->id,
                        'gateway' => null,
                        'status' => $row->status,
                        'amount' => $row->sell_price,
                        'description' => sprintf(
                            'Riwayat awal: %s memiliki pesanan %s (%s) senilai Rp %s.',
                            $row->user_email ?: 'Pengguna',
                            $row->service_name,
                            $row->country_name,
                            number_format((float) $row->sell_price, 0, ',', '.'),
                        ),
                        'meta' => json_encode(['snapshot' => true]),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        Schema::table('topups', function (Blueprint $table): void {
            $table->dropIndex(['gateway']);
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['gateway', 'provider_reference']);
        });
    }
};
