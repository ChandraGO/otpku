<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    public function create(?int $createdBy = null): DatabaseBackup
    {
        $filename = 'kodeotp-'.now()->format('Ymd-His').'.sql.gz';
        $path = Storage::disk('backups')->path($filename);
        $plainPath = preg_replace('/\.gz$/', '', $path);
        $process = new Process([
            'pg_dump', '--no-owner', '--no-privileges', '--clean', '--if-exists',
            '--host='.$this->db('host'), '--port='.$this->db('port'), '--username='.$this->db('username'), '--dbname='.$this->db('database'), '--file='.$plainPath,
        ], env: ['PGPASSWORD' => $this->db('password')]);
        $process->setTimeout(600);
        $process->mustRun();

        $gzip = new Process(['gzip', '-9', $plainPath]);
        $gzip->setTimeout(600);
        $gzip->mustRun();
        if (! is_file($path)) throw new RuntimeException('File backup tidak berhasil dibuat.');

        return DatabaseBackup::query()->create([
            'created_by' => $createdBy, 'filename' => $filename, 'disk' => 'backups', 'size' => filesize($path),
            'checksum' => hash_file('sha256', $path), 'source' => 'generated', 'status' => 'ready',
        ]);
    }

    public function storeUpload(UploadedFile $file, ?int $createdBy = null): DatabaseBackup
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['sql', 'gz'], true)) throw new RuntimeException('Backup harus berformat .sql atau .sql.gz.');
        $filename = 'uploaded-'.now()->format('Ymd-His').'-'.str()->random(6).'.'.$ext;
        $path = $file->storeAs('', $filename, 'backups');
        if (! $path) throw new RuntimeException('Upload backup gagal.');
        $absolute = Storage::disk('backups')->path($filename);
        return DatabaseBackup::query()->create([
            'created_by' => $createdBy, 'filename' => $filename, 'disk' => 'backups', 'size' => filesize($absolute),
            'checksum' => hash_file('sha256', $absolute), 'source' => 'uploaded', 'status' => 'ready',
        ]);
    }

    public function restore(DatabaseBackup $backup): void
    {
        $path = Storage::disk($backup->disk)->path($backup->filename);
        if (! is_file($path)) throw new RuntimeException('File backup tidak ditemukan.');
        if ($backup->checksum && ! hash_equals($backup->checksum, hash_file('sha256', $path))) throw new RuntimeException('Checksum backup tidak cocok; restore dibatalkan.');
        $backup->update(['status' => 'restoring']);
        $command = str_ends_with($backup->filename, '.gz')
            ? ['sh', '-lc', 'gzip -dc '.escapeshellarg($path).' | psql --set ON_ERROR_STOP=1 --host='.escapeshellarg($this->db('host')).' --port='.escapeshellarg($this->db('port')).' --username='.escapeshellarg($this->db('username')).' --dbname='.escapeshellarg($this->db('database'))]
            : ['psql', '--set', 'ON_ERROR_STOP=1', '--host='.$this->db('host'), '--port='.$this->db('port'), '--username='.$this->db('username'), '--dbname='.$this->db('database'), '--file='.$path];
        $process = new Process($command, env: ['PGPASSWORD' => $this->db('password')]);
        $process->setTimeout(1800);
        try {
            $process->mustRun();
            $backup->update(['status' => 'ready']);
        } catch (\Throwable $e) {
            $backup->update(['status' => 'failed']);
            throw $e;
        }
    }

    public function delete(DatabaseBackup $backup): void
    {
        Storage::disk($backup->disk)->delete($backup->filename);
        $backup->delete();
    }

    private function db(string $key): string
    {
        $connection = config('database.connections.'.config('database.default'));
        return (string) match ($key) {
            'host' => $connection['host'] ?? 'postgres', 'port' => $connection['port'] ?? '5432', 'database' => $connection['database'] ?? '',
            'username' => $connection['username'] ?? '', 'password' => $connection['password'] ?? '', default => '',
        };
    }
}
