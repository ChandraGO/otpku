#!/usr/bin/env bash
set -Eeuo pipefail
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
PROJECT="${PROJECT:-kodeotp}"
COMPOSE=(docker compose --env-file "$STACK_DIR/.env" -p "$PROJECT" -f "$STACK_DIR/docker-compose.yml")
ACTIVE="$(cat "$STACK_DIR/.active_color" 2>/dev/null || echo blue)"
SERVICE="app_${ACTIVE}"
CID="$("${COMPOSE[@]}" ps -q "$SERVICE" | tail -n1)"
echo "ACTIVE_COLOR=$ACTIVE"
echo "ACTIVE_SERVICE=$SERVICE"
echo "ACTIVE_CID=$CID"
echo "DEPLOYED_SHA=$(cat "$STACK_DIR/.deployed_sha" 2>/dev/null || true)"
[ -n "$CID" ] || { echo 'Container aktif tidak ditemukan'; exit 1; }
echo '=== PHP SYNTAX ==='
docker exec "$CID" php -l app/Providers/AppServiceProvider.php
docker exec "$CID" php -l app/Http/Controllers/Admin/DashboardController.php
docker exec "$CID" php -l app/Http/Controllers/Admin/SettingsController.php
echo '=== AUTHENTICATED ADMIN RENDER TEST ==='
docker exec -i "$CID" php <<'PHP'
<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $admin = App\Models\User::query()->where('role', 'admin')->first();
    if (! $admin) {
        throw new RuntimeException('Admin user tidak ditemukan.');
    }
    Illuminate\Support\Facades\Auth::guard()->setUser($admin);
    $request = Illuminate\Http\Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);
    $view = $app->call([app(App\Http\Controllers\Admin\DashboardController::class), '__invoke']);
    $html = $view->render();
    echo 'ADMIN_RENDER_OK bytes='.strlen($html).PHP_EOL;
} catch (Throwable $e) {
    echo 'ADMIN_RENDER_EXCEPTION='.get_class($e).PHP_EOL;
    echo 'MESSAGE='.$e->getMessage().PHP_EOL;
    echo 'FILE='.$e->getFile().':'.$e->getLine().PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
    exit(1);
}
PHP
echo '=== LARAVEL LOG TERAKHIR ==='
docker exec "$CID" sh -lc 'tail -n 250 storage/logs/laravel.log 2>/dev/null || true'
echo '=== CONTAINER LOG TERAKHIR ==='
docker logs --tail 150 "$CID" 2>&1 || true
