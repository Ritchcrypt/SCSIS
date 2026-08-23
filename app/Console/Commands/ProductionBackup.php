<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveBackupClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ProductionBackup extends Command
{
    protected $signature = 'backup:production
        {--force : Run even when BACKUP_ENABLED is false}
        {--no-retention : Upload backups without deleting older backups}';

    protected $description =
        'Create encrypted MySQL and Laravel upload backups in Google Drive';

    public function handle(GoogleDriveBackupClient $drive): int
    {
        if (
            ! (bool) config('backup.enabled', false)
            && ! (bool) $this->option('force')
        ) {
            $this->error(
                'Production backups are disabled. Set BACKUP_ENABLED=true.'
            );

            return self::FAILURE;
        }

        $encryptionKey = config('backup.encryption_key');

        if (
            ! is_string($encryptionKey)
            || strlen($encryptionKey) < 32
        ) {
            $this->error(
                'BACKUP_ENCRYPTION_KEY is missing or too short. '
                . 'Use a high-entropy secret of at least 32 characters.'
            );

            return self::FAILURE;
        }

        try {
            $drive->assertConfigured();
            $binaries = $this->requiredBinaries();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $lock = Cache::lock(
            'tabangnow:production-backup',
            7200
        );

        if (! $lock->get()) {
            $this->error(
                'Another TabangNow production backup is already running.'
            );

            return self::FAILURE;
        }

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'tabangnow-backup-'
            . Str::uuid();

        try {
            File::ensureDirectoryExists($tempDir, 0700, true);
            @chmod($tempDir, 0700);

            $timestamp = now('UTC')->format('Y-m-d_His\Z');

            $databaseCompressed = $tempDir
                . DIRECTORY_SEPARATOR
                . "tabangnow-db-{$timestamp}.sql.gz";
            $databaseEncrypted = $databaseCompressed . '.enc';

            $uploadsCompressed = $tempDir
                . DIRECTORY_SEPARATOR
                . "tabangnow-uploads-{$timestamp}.tar.gz";
            $uploadsEncrypted = $uploadsCompressed . '.enc';

            $this->info('Creating consistent MySQL backup...');
            $this->createDatabaseBackup(
                $databaseCompressed,
                $binaries['mysqldump'],
                $binaries['gzip'],
                $binaries['bash']
            );

            $this->info('Archiving persistent Laravel uploads...');
            $this->createUploadArchive(
                $uploadsCompressed,
                $binaries['tar']
            );

            $this->info('Encrypting backup artifacts...');
            $this->encryptFile(
                $databaseCompressed,
                $databaseEncrypted,
                $encryptionKey,
                $binaries['openssl']
            );
            File::delete($databaseCompressed);

            $this->encryptFile(
                $uploadsCompressed,
                $uploadsEncrypted,
                $encryptionKey,
                $binaries['openssl']
            );
            File::delete($uploadsCompressed);

            $databaseIntegrity = $this->integrityMetadata(
                $databaseEncrypted,
                $encryptionKey
            );
            $uploadsIntegrity = $this->integrityMetadata(
                $uploadsEncrypted,
                $encryptionKey
            );

            $this->info('Preparing Google Drive backup folders...');
            $folders = $drive->ensureBackupFolders();

            $this->info('Uploading encrypted database backup...');
            $databaseRemote = $drive->uploadEncryptedBackup(
                $databaseEncrypted,
                $folders['database'],
                'database',
                $databaseIntegrity['sha256'],
                $databaseIntegrity['hmac_sha256']
            );

            $this->info('Uploading encrypted upload backup...');
            $uploadsRemote = $drive->uploadEncryptedBackup(
                $uploadsEncrypted,
                $folders['uploads'],
                'uploads',
                $uploadsIntegrity['sha256'],
                $uploadsIntegrity['hmac_sha256']
            );

            if (! (bool) $this->option('no-retention')) {
                $keep = max(
                    1,
                    (int) config('backup.retention_count', 14)
                );

                $databaseDeleted = $drive->pruneFolder(
                    $folders['database'],
                    $keep
                );
                $uploadsDeleted = $drive->pruneFolder(
                    $folders['uploads'],
                    $keep
                );

                $this->line(
                    "Retention cleanup removed {$databaseDeleted} "
                    . "database and {$uploadsDeleted} upload backup(s)."
                );
            }

            Log::info('Production backup completed.', [
                'database_drive_file_id' =>
                    $databaseRemote['id'] ?? null,
                'uploads_drive_file_id' =>
                    $uploadsRemote['id'] ?? null,
                'database_sha256' =>
                    $databaseIntegrity['sha256'],
                'uploads_sha256' =>
                    $uploadsIntegrity['sha256'],
            ]);

            $this->newLine();
            $this->info('TABANGNOW PRODUCTION BACKUP: PASS');
            $this->line(
                'Database: ' . basename($databaseEncrypted)
            );
            $this->line(
                'Uploads:  ' . basename($uploadsEncrypted)
            );
            $this->line(
                'Local plaintext/compressed temporary files were removed.'
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Production backup failed.', [
                'error' => $e->getMessage(),
            ]);

            $this->error(
                'TABANGNOW PRODUCTION BACKUP: FAILED'
            );
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($tempDir);
            $lock->release();
        }
    }

    private function requiredBinaries(): array
    {
        $finder = new ExecutableFinder();
        $binaries = [];

        foreach (
            ['mysqldump', 'gzip', 'tar', 'openssl', 'bash'] as $binary
        ) {
            $path = $finder->find($binary);

            if (! is_string($path) || $path === '') {
                throw new RuntimeException(
                    "Required backup binary is unavailable: {$binary}"
                );
            }

            $binaries[$binary] = $path;
        }

        return $binaries;
    }

    private function createDatabaseBackup(
        string $outputPath,
        string $mysqldump,
        string $gzip,
        string $bash
    ): void {
        $database = $this->mysqlConnection();
        $defaultsFile = dirname($outputPath)
            . DIRECTORY_SEPARATOR
            . 'mysql-client.cnf';

        $contents = "[client]\n"
            . 'host=' . $this->mysqlOption($database['host']) . "\n"
            . 'port=' . (int) $database['port'] . "\n"
            . 'user=' . $this->mysqlOption($database['username']) . "\n"
            . 'password=' . $this->mysqlOption($database['password']) . "\n";

        File::put($defaultsFile, $contents);
        @chmod($defaultsFile, 0600);

        try {
            $command = escapeshellarg($mysqldump)
                . ' --defaults-extra-file='
                . escapeshellarg($defaultsFile)
                . ' --ssl'
                . ' --disable-ssl-verify-server-cert'
                . ' --single-transaction'
                . ' --quick'
                . ' --skip-lock-tables'
                . ' --hex-blob'
                . ' --default-character-set=utf8mb4 '
                . escapeshellarg($database['database'])
                . ' | '
                . escapeshellarg($gzip)
                . ' -9 > '
                . escapeshellarg($outputPath);

            $process = new Process([
                $bash,
                '-o',
                'pipefail',
                '-c',
                $command,
            ]);
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'MySQL backup creation failed.'
                );
            }
        } finally {
            File::delete($defaultsFile);
        }

        $this->assertNonEmptyFile(
            $outputPath,
            'MySQL compressed backup is empty.'
        );
    }

    private function createUploadArchive(
        string $outputPath,
        string $tar
    ): void {
        $storageApp = storage_path('app');
        $private = $storageApp . DIRECTORY_SEPARATOR . 'private';
        $public = $storageApp . DIRECTORY_SEPARATOR . 'public';

        File::ensureDirectoryExists($private);
        File::ensureDirectoryExists($public);

        $command = escapeshellarg($tar)
            . ' -C '
            . escapeshellarg($storageApp)
            . ' -czf '
            . escapeshellarg($outputPath)
            . ' private public';

        $this->runShellCommand(
            $command,
            1800,
            'Laravel upload archive creation failed.'
        );

        $this->assertNonEmptyFile(
            $outputPath,
            'Laravel upload archive is empty.'
        );
    }

    private function encryptFile(
        string $inputPath,
        string $outputPath,
        string $encryptionKey,
        string $openssl
    ): void {
        $this->assertNonEmptyFile(
            $inputPath,
            'Backup input artifact is empty before encryption.'
        );

        $command = escapeshellarg($openssl)
            . ' enc -aes-256-cbc'
            . ' -salt -pbkdf2 -iter 250000 -md sha256'
            . ' -in '
            . escapeshellarg($inputPath)
            . ' -out '
            . escapeshellarg($outputPath)
            . ' -pass env:TABANGNOW_BACKUP_PASSPHRASE';

        $process = Process::fromShellCommandline(
            $command,
            null,
            [
                'TABANGNOW_BACKUP_PASSPHRASE' => $encryptionKey,
            ]
        );
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Backup encryption failed.'
            );
        }

        $this->assertNonEmptyFile(
            $outputPath,
            'Encrypted backup artifact is empty.'
        );
    }

    private function integrityMetadata(
        string $path,
        string $encryptionKey
    ): array {
        $sha256 = hash_file('sha256', $path);

        if (! is_string($sha256) || $sha256 === '') {
            throw new RuntimeException(
                'Could not generate backup SHA-256.'
            );
        }

        $integrityKey = hash(
            'sha256',
            'tabangnow-backup-integrity-v1:' . $encryptionKey,
            true
        );
        $hmac = hash_hmac_file(
            'sha256',
            $path,
            $integrityKey
        );

        if (! is_string($hmac) || $hmac === '') {
            throw new RuntimeException(
                'Could not generate backup HMAC.'
            );
        }

        return [
            'sha256' => $sha256,
            'hmac_sha256' => $hmac,
        ];
    }

    private function mysqlConnection(): array
    {
        $connection = config('database.connections.mysql', []);

        if (! is_array($connection)) {
            throw new RuntimeException(
                'MySQL connection configuration is unavailable.'
            );
        }

        $url = $connection['url'] ?? null;

        if (is_string($url) && trim($url) !== '') {
            $parts = parse_url($url);

            if ($parts === false) {
                throw new RuntimeException(
                    'MySQL connection URL could not be parsed.'
                );
            }

            $connection['host'] =
                $parts['host'] ?? ($connection['host'] ?? '');
            $connection['port'] =
                $parts['port'] ?? ($connection['port'] ?? 3306);
            $connection['username'] = isset($parts['user'])
                ? urldecode($parts['user'])
                : ($connection['username'] ?? '');
            $connection['password'] = isset($parts['pass'])
                ? urldecode($parts['pass'])
                : ($connection['password'] ?? '');
            $connection['database'] = isset($parts['path'])
                ? ltrim(urldecode($parts['path']), '/')
                : ($connection['database'] ?? '');
        }

        $resolved = [
            'host' => (string) ($connection['host'] ?? ''),
            'port' => (int) ($connection['port'] ?? 3306),
            'username' =>
                (string) ($connection['username'] ?? ''),
            'password' =>
                (string) ($connection['password'] ?? ''),
            'database' =>
                (string) ($connection['database'] ?? ''),
        ];

        if (
            $resolved['host'] === ''
            || $resolved['username'] === ''
            || $resolved['database'] === ''
        ) {
            throw new RuntimeException(
                'MySQL backup connection is incomplete.'
            );
        }

        return $resolved;
    }

    private function mysqlOption(string $value): string
    {
        return '"'
            . addcslashes($value, "\\\"")
            . '"';
    }

    private function runShellCommand(
        string $command,
        int $timeout,
        string $failureMessage
    ): void {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($failureMessage);
        }
    }

    private function assertNonEmptyFile(
        string $path,
        string $message
    ): void {
        if (! is_file($path)) {
            throw new RuntimeException($message);
        }

        $size = filesize($path);

        if ($size === false || $size < 1) {
            throw new RuntimeException($message);
        }

        @chmod($path, 0600);
    }
}
