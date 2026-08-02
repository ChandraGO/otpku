<?php
namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $action, Model|string|null $subject = null, array $before = [], array $after = []): void
    {
        AdminAuditLog::query()->create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject::class : (is_string($subject) ? $subject : null),
            'subject_id' => $subject instanceof Model ? (string) $subject->getKey() : null,
            'ip_address' => request()?->ip(),
            'user_agent' => str(request()?->userAgent())->limit(500)->toString(),
            'before' => $before ?: null,
            'after' => $after ?: null,
        ]);
    }
}
