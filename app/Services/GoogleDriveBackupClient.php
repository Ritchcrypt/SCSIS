<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveBackupClient
{
    private const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    private const DRIVE_UPLOAD_API =
        'https://www.googleapis.com/upload/drive/v3';

    private const TOKEN_ENDPOINT =
        'https://oauth2.googleapis.com/token';

    private ?string $accessToken = null;

    public function assertConfigured(): void
    {
        $required = [
            'GOOGLE_DRIVE_CLIENT_ID' =>
                config('backup.google_drive.client_id'),
            'GOOGLE_DRIVE_CLIENT_SECRET' =>
                config('backup.google_drive.client_secret'),
            'GOOGLE_DRIVE_REFRESH_TOKEN' =>
                config('backup.google_drive.refresh_token'),
        ];

        $missing = array_keys(
            array_filter(
                $required,
                fn ($value): bool =>
                    ! is_string($value) || trim($value) === ''
            )
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing Google Drive backup configuration: '
                . implode(', ', $missing)
            );
        }
    }

    public function ensureBackupFolders(): array
    {
        $this->assertConfigured();

        $root = $this->ensureFolder(
            (string) config(
                'backup.google_drive.root_folder_name',
                'TabangNow Backups'
            ),
            'root'
        );

        $database = $this->ensureFolder(
            (string) config(
                'backup.google_drive.database_folder_name',
                'database'
            ),
            $root
        );

        $uploads = $this->ensureFolder(
            (string) config(
                'backup.google_drive.uploads_folder_name',
                'uploads'
            ),
            $root
        );

        return [
            'root' => $root,
            'database' => $database,
            'uploads' => $uploads,
        ];
    }

    public function uploadEncryptedBackup(
        string $localPath,
        string $folderId,
        string $kind,
        string $sha256,
        string $hmacSha256
    ): array {
        if (! is_file($localPath)) {
            throw new RuntimeException(
                'Backup artifact does not exist: '
                . basename($localPath)
            );
        }

        $localSize = filesize($localPath);

        if ($localSize === false || $localSize < 1) {
            throw new RuntimeException(
                'Backup artifact is empty: '
                . basename($localPath)
            );
        }

        $metadata = $this->request()
            ->post(
                self::DRIVE_API . '/files?fields=id,name',
                [
                    'name' => basename($localPath),
                    'parents' => [$folderId],
                    'mimeType' => 'application/octet-stream',
                    'appProperties' => [
                        'tabangnow_backup' => '1',
                        'backup_kind' => $kind,
                        'sha256' => $sha256,
                        'hmac_sha256' => $hmacSha256,
                        'encryption' =>
                            'aes-256-cbc-pbkdf2-sha256-v1',
                    ],
                ]
            );

        if (! $metadata->successful()) {
            throw new RuntimeException(
                'Google Drive could not create backup metadata.'
            );
        }

        $fileId = (string) $metadata->json('id');

        if ($fileId === '') {
            throw new RuntimeException(
                'Google Drive returned an empty backup file ID.'
            );
        }

        try {
            $uploaded = $this->uploadFileContents(
                $fileId,
                $localPath
            );
        } catch (\Throwable $e) {
            $this->deleteFile($fileId, ignoreMissing: true);

            throw $e;
        }

        $remoteSize = (int) ($uploaded['size'] ?? 0);
        $remoteMd5 = strtolower(
            (string) ($uploaded['md5Checksum'] ?? '')
        );
        $localMd5 = strtolower((string) md5_file($localPath));

        if (
            $remoteSize !== (int) $localSize
            || $remoteMd5 === ''
            || ! hash_equals($localMd5, $remoteMd5)
        ) {
            $this->deleteFile($fileId, ignoreMissing: true);

            throw new RuntimeException(
                'Google Drive backup verification failed for '
                . basename($localPath)
            );
        }

        return $uploaded;
    }

    public function pruneFolder(
        string $folderId,
        int $keepCount
    ): int {
        $keepCount = max(1, $keepCount);
        $files = [];
        $pageToken = null;

        do {
            $query = "'{$folderId}' in parents"
                . " and trashed = false"
                . " and appProperties has "
                . "{ key='tabangnow_backup' and value='1' }";

            $params = [
                'q' => $query,
                'orderBy' => 'createdTime desc',
                'pageSize' => 1000,
                'fields' =>
                    'nextPageToken,files(id,name,createdTime,size)',
            ];

            if (is_string($pageToken) && $pageToken !== '') {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->request()
                ->get(self::DRIVE_API . '/files', $params);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Google Drive retention listing failed.'
                );
            }

            $files = array_merge(
                $files,
                (array) $response->json('files', [])
            );

            $pageToken = $response->json('nextPageToken');
        } while (is_string($pageToken) && $pageToken !== '');

        $deleted = 0;

        foreach (array_slice($files, $keepCount) as $file) {
            $id = (string) ($file['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $this->deleteFile($id);
            $deleted++;
        }

        return $deleted;
    }

    private function ensureFolder(
        string $name,
        string $parentId
    ): string {
        $escapedName = $this->escapeQueryLiteral($name);
        $escapedParent = $this->escapeQueryLiteral($parentId);

        $response = $this->request()->get(
            self::DRIVE_API . '/files',
            [
                'q' =>
                    "mimeType = 'application/vnd.google-apps.folder'"
                    . " and name = '{$escapedName}'"
                    . " and '{$escapedParent}' in parents"
                    . ' and trashed = false',
                'pageSize' => 10,
                'fields' => 'files(id,name)',
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Drive folder lookup failed for ' . $name . '.'
            );
        }

        $existing = (array) $response->json('files', []);

        if ($existing !== []) {
            $id = (string) ($existing[0]['id'] ?? '');

            if ($id !== '') {
                return $id;
            }
        }

        $create = $this->request()->post(
            self::DRIVE_API . '/files?fields=id,name',
            [
                'name' => $name,
                'mimeType' =>
                    'application/vnd.google-apps.folder',
                'parents' => [$parentId],
                'appProperties' => [
                    'tabangnow_backup_folder' => '1',
                ],
            ]
        );

        if (! $create->successful()) {
            throw new RuntimeException(
                'Google Drive could not create folder ' . $name . '.'
            );
        }

        $id = (string) $create->json('id');

        if ($id === '') {
            throw new RuntimeException(
                'Google Drive returned an empty folder ID for '
                . $name . '.'
            );
        }

        return $id;
    }

    private function uploadFileContents(
        string $fileId,
        string $localPath
    ): array {
        $url = self::DRIVE_UPLOAD_API
            . '/files/'
            . rawurlencode($fileId)
            . '?uploadType=media'
            . '&fields=id,name,size,md5Checksum,createdTime';

        $lastStatus = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $stream = fopen($localPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException(
                    'Could not open backup artifact for upload.'
                );
            }

            try {
                $response = $this->request()
                    ->timeout(900)
                    ->send(
                        'PATCH',
                        $url,
                        [
                            'headers' => [
                                'Content-Type' =>
                                    'application/octet-stream',
                            ],
                            'body' => $stream,
                        ]
                    );
            } finally {
                fclose($stream);
            }

            if ($response->successful()) {
                return (array) $response->json();
            }

            $lastStatus = $response->status();

            if ($attempt < 3) {
                usleep(1_000_000 * $attempt);
            }
        }

        throw new RuntimeException(
            'Google Drive backup upload failed with HTTP status '
            . (string) $lastStatus
            . '.'
        );
    }

    private function deleteFile(
        string $fileId,
        bool $ignoreMissing = false
    ): void {
        $response = $this->request()->delete(
            self::DRIVE_API . '/files/' . rawurlencode($fileId)
        );

        if ($response->successful()) {
            return;
        }

        if ($ignoreMissing && $response->status() === 404) {
            return;
        }

        throw new RuntimeException(
            'Google Drive could not delete an expired backup.'
        );
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(15)
            ->timeout(60);
    }

    private function accessToken(): string
    {
        if (is_string($this->accessToken)) {
            return $this->accessToken;
        }

        $this->assertConfigured();

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(30)
            ->retry(3, 1000)
            ->post(
                self::TOKEN_ENDPOINT,
                [
                    'client_id' =>
                        config('backup.google_drive.client_id'),
                    'client_secret' =>
                        config('backup.google_drive.client_secret'),
                    'refresh_token' =>
                        config('backup.google_drive.refresh_token'),
                    'grant_type' => 'refresh_token',
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google OAuth token refresh failed.'
            );
        }

        $token = (string) $response->json('access_token');

        if ($token === '') {
            throw new RuntimeException(
                'Google OAuth returned an empty access token.'
            );
        }

        $this->accessToken = $token;

        return $token;
    }

    private function escapeQueryLiteral(string $value): string
    {
        return str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            $value
        );
    }
}
