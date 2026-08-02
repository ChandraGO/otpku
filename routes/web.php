<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TopupController as AdminTopupController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OtpOrderController;
use App\Http\Controllers\PakasirWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsVirtualWebhookController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/harga', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/sitemap.xml', [HomeController::class, 'sitemap'])->name('sitemap');
Route::get('/healthz', [HomeController::class, 'health'])->name('healthz');
Route::view('/syarat-ketentuan', 'terms')->name('terms');
Route::view('/kebijakan-privasi', 'privacy')->name('privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:email-otp');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/lupa-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'send'])->middleware('throttle:email-otp')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('password.reset.form');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:email-otp')->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/verifikasi-email', [VerifyEmailController::class, 'show'])->name('verification.notice');
    Route::post('/verifikasi-email', [VerifyEmailController::class, 'verify'])->middleware('throttle:email-otp')->name('verification.verify');
    Route::post('/verifikasi-email/kirim-ulang', [VerifyEmailController::class, 'resend'])->middleware('throttle:email-otp')->name('verification.send');
});

Route::middleware(['auth', 'active', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/layanan', [CatalogController::class, 'index'])->name('services.index');
    Route::get('/layanan/{service}', [CatalogController::class, 'show'])->name('services.show');
    Route::get('/pesanan', [OtpOrderController::class, 'index'])->name('orders.index');
    Route::post('/pesanan', [OtpOrderController::class, 'store'])->middleware('throttle:orders')->name('orders.store');
    Route::get('/pesanan/{order}', [OtpOrderController::class, 'show'])->name('orders.show');
    Route::get('/pesanan/{order}/status', [OtpOrderController::class, 'status'])->middleware('throttle:60,1')->name('orders.status');
    Route::post('/pesanan/{order}/aksi', [OtpOrderController::class, 'action'])->middleware('throttle:orders')->name('orders.action');
    Route::get('/top-up', [TopupController::class, 'index'])->name('topups.index');
    Route::post('/top-up', [TopupController::class, 'store'])->middleware('throttle:topups')->name('topups.store');
    Route::get('/top-up/{topup}', [TopupController::class, 'show'])->name('topups.show');
    Route::get('/top-up/{topup}/status', [TopupController::class, 'status'])->middleware('throttle:30,1')->name('topups.status');
    Route::get('/mutasi', [WalletController::class, 'index'])->name('wallet.index');
    Route::get('/pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::view('/api-docs', 'user.api-docs')->name('api.docs');
    Route::view('/bantuan', 'user.support')->name('support.index');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}/status', [AdminUserController::class, 'status'])->name('users.status');
        Route::post('/users/{user}/balance', [AdminUserController::class, 'adjustBalance'])->name('users.balance');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/action', [AdminOrderController::class, 'action'])->name('orders.action');
        Route::get('/topups', [AdminTopupController::class, 'index'])->name('topups.index');
        Route::get('/topups/{topup}', [AdminTopupController::class, 'show'])->name('topups.show');
        Route::post('/topups/{topup}/verify', [AdminTopupController::class, 'verify'])->name('topups.verify');
        Route::resource('announcements', AdminAnnouncementController::class)->except('show');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-sms', [AdminSettingsController::class, 'testSms'])->name('settings.test-sms');
        Route::post('/settings/test-pakasir', [AdminSettingsController::class, 'testPakasir'])->name('settings.test-pakasir');
        Route::post('/settings/test-mail', [AdminSettingsController::class, 'testMail'])->name('settings.test-mail');
        Route::post('/settings/sync-catalog', [AdminSettingsController::class, 'syncCatalog'])->name('settings.sync-catalog');
        Route::get('/backups', [AdminBackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [AdminBackupController::class, 'create'])->name('backups.create');
        Route::post('/backups/upload', [AdminBackupController::class, 'upload'])->name('backups.upload');
        Route::get('/backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{backup}/restore', [AdminBackupController::class, 'restore'])->name('backups.restore');
        Route::delete('/backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}.csv', [AdminReportController::class, 'export'])->name('reports.export');
    });
});

Route::post('/webhooks/pakasir', PakasirWebhookController::class)->middleware('throttle:60,1')->name('webhooks.pakasir');
Route::post('/webhooks/sms-virtual/{secret}', SmsVirtualWebhookController::class)->middleware('throttle:120,1')->name('webhooks.sms-virtual');
