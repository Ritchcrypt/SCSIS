<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Private, manually curated *links* to original publications.
 * No article text, publisher photos, feeds, HTTP requests or remote embeds.
 */
final class DashboardDaoNewsService
{
    private const PATH = 'dao-external-links/entries.json';
    private const MAX_NEWS_STORIES = 9; // One welcome slide + nine external links.
    private const MAX_STORED_LINKS = 100;
    private const MAX_AUDIT_EVENTS = 4000;
    private const AUDIT_KEY_PATH = 'dao-external-links/audit-key.json';
    private const ARCHIVE_DIR = 'dao-external-links/archives';
    private const SNAPSHOT_DIR = 'dao-external-links/snapshots';

    public const TOPICS = [
        'Weather and disaster', 'Roads and transportation', 'Public safety',
        'Health and services', 'Education', 'Local government',
        'Community activities', 'Economy and livelihood', 'Local news and updates',
    ];

    public function forUser(?User $user): Collection
    {
        if ($user === null) return collect();

        try { $items = $this->entries(); }
        catch (\RuntimeException $exception) {
            // An unreadable private JSON file must never take down all dashboards.
            report($exception);
            return collect();
        }

        return collect($items)
            ->filter(fn (array $row): bool => $this->isApprovedAndCurrent($row))
            ->take(self::MAX_NEWS_STORIES)
            ->map(function (array $row): array {
                return [
                    'title' => 'Dao, Capiz · '.$row['topic'],
                    'summary' => 'Open the original publication to read this external update.',
                    'url' => $row['url'], 'image' => null,
                    'source' => parse_url($row['url'], PHP_URL_HOST) ?: 'External publication',
                    'published' => $row['published'] ?: null,
                ];
            })->values();
    }

    public function entries(): array
    {
        return $this->readDocument()['entries'];
    }

    /** Most recent 40 events, including deleted links. Admin dashboard only. */
    public function auditHistory(): array
    {
        $doc = $this->readDocument();
        $events = $doc['audit'];
        $disk = Storage::disk('local');
        for ($i = count($doc['archives']) - 1; count($events) < 40 && $i >= 0; $i--) {
            $segment = json_decode($disk->get(self::ARCHIVE_DIR.'/'.$doc['archives'][$i]), true, 512, JSON_THROW_ON_ERROR);
            $events = array_merge($segment['events'], $events);
        }
        return array_reverse(array_slice($events, -40));
    }

    /** Validate all stored signatures, archive references and the signed document seal. */
    private function readDocument(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) return ['entries' => [], 'audit' => [], 'archives' => [], 'audit_base_hash' => ''];
        $raw = $disk->get(self::PATH);
        if (! is_string($raw) || strlen($raw) > 4000000) {
            throw new \RuntimeException('External news storage is unreadable or exceeds 4 MB. Restore a verified snapshot.');
        }
        try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('External news storage is corrupt. Restore a verified snapshot.', 0, $e); }
        if (! is_array($data) || ! isset($data['entries']) || ! is_array($data['entries'])) {
            throw new \RuntimeException('Invalid news document format; writes disabled.');
        }
        if (! array_is_list($data['entries']) || count($data['entries']) > self::MAX_STORED_LINKS) {
            throw new \RuntimeException('Invalid external-news entries list; writes disabled.');
        }
        $version = (int) ($data['version'] ?? 1);
        if ($version >= 4) {
            $secret = $this->auditSecret();
            if (! isset($data['document_seal']) || ! is_string($data['document_seal'])) {
                throw new \RuntimeException('Signed news document seal is missing.');
            }
            $unsigned = $data;
            unset($unsigned['document_seal']);
            if (! hash_equals($this->signDocument($unsigned, $secret), $data['document_seal'])) {
                throw new \RuntimeException('News document signature check failed; writes disabled.');
            }
        }
        $items = [];
        $knownIds = [];
        $allowedStates = ['manual', 'ok', 'broken', 'redirected', 'unreachable', 'manual_check'];
        foreach ($data['entries'] as $row) {
            if (! is_array($row)) throw new \RuntimeException('Malformed news record; edits disabled to protect stored links.');
            $url = (string) ($row['url'] ?? '');
            $topic = (string) ($row['topic'] ?? '');
            $id = (string) ($row['id'] ?? '');
            $date = (string) ($row['published'] ?? '');
            $review = (string) ($row['reviewed_at'] ?? '');
            if (! $this->hasValidHttpsStructure($url) || ! in_array($topic, self::TOPICS, true)
                || ! preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/iD', $id)
                || isset($knownIds[strtolower($id)])) {
                throw new \RuntimeException('Invalid or duplicate news record. Edits disabled to protect stored links.');
            }
            $knownIds[strtolower($id)] = true;
            if ($date !== '' && ! $this->isCalendarDate($date)) throw new \RuntimeException('Malformed publication date in saved news.');
            if ($review !== '' && ! $this->isCalendarDate($review)) throw new \RuntimeException('Malformed review date in saved news.');
            $relevance = (string) ($row['dao_verified_at'] ?? '');
            if ($relevance !== '' && ! $this->isCalendarDate($relevance)) throw new \RuntimeException('Malformed relevance-review date in saved news.');
            $state = (string) ($row['link_status'] ?? 'manual');
            $lastChecked = (string) ($row['last_checked_at'] ?? '');
            $httpStatus = $row['last_http_status'] ?? null;
            if (! in_array($state, $allowedStates, true) || (strlen($lastChecked) > 32)
                || ($lastChecked !== '' && strtotime($lastChecked) === false)
                || ($httpStatus !== null && (! is_int($httpStatus) || $httpStatus < 100 || $httpStatus > 599))) {
                throw new \RuntimeException('Invalid link-health record. Edits disabled to protect stored links.');
            }
            $reviewedBy = $row['reviewed_by'] ?? null;
            $items[] = ['id' => $id, 'url' => $url, 'topic' => $topic,
                'published' => $date, 'reviewed_at' => $review,
                'dao_verified_at' => $relevance,
                'reviewed_by' => is_int($reviewedBy) && $reviewedBy > 0 ? $reviewedBy : null,
                'link_status' => $state, 'last_checked_at' => $lastChecked,
                'last_http_status' => $httpStatus];
        }
        if ($version >= 3 && ! array_key_exists('audit', $data)) {
            throw new \RuntimeException('Audit history missing from news data.');
        }
        $audit = $data['audit'] ?? [];
        if (! is_array($audit) || ! array_is_list($audit) || count($audit) > self::MAX_AUDIT_EVENTS) {
            throw new \RuntimeException('Malformed external-news audit history.');
        }
        $archives = $data['archives'] ?? [];
        $base = (string) ($data['audit_base_hash'] ?? '');
        if ($version >= 4) {
            if (! is_array($archives) || ! array_is_list($archives) || ! preg_match('/^(?:[0-9a-f]{64})?$/D', $base)) {
                throw new \RuntimeException('Malformed news archive metadata.');
            }
            $previous = '';
            foreach ($archives as $name) {
                if (! is_string($name) || ! preg_match('/^[0-9]{8}T[0-9]{6}-[0-9a-f]{16}\.json$/D', $name)) {
                    throw new \RuntimeException('Unsafe audit archive reference.');
                }
                $path = self::ARCHIVE_DIR.'/'.$name;
                if (! $disk->exists($path)) throw new \RuntimeException('Referenced audit archive is missing.');
                try { $segment = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR); }
                catch (\JsonException $e) { throw new \RuntimeException('Audit archive is corrupt.', 0, $e); }
                if (! is_array($segment) || ! isset($segment['events']) || ! is_array($segment['events'])
                    || ! array_is_list($segment['events']) || ! isset($segment['seal'])) {
                    throw new \RuntimeException('Malformed audit archive.');
                }
                $unsignedArchive = $segment;
                unset($unsignedArchive['seal']);
                if (! hash_equals($this->signDocument($unsignedArchive, $this->auditSecret()), (string) $segment['seal'])) {
                    throw new \RuntimeException('Audit archive seal mismatch.');
                }
                $previous = $this->verifyEvents($segment['events'], $previous);
            }
            if (! hash_equals($previous, $base)) throw new \RuntimeException('Audit archive boundary mismatch.');
        } else {
            if ($archives !== [] || $base !== '') throw new \RuntimeException('Unexpected legacy archive fields.');
        }
        $this->verifyEvents($audit, $version >= 4 ? $base : '');
        return ['entries' => $items, 'audit' => $audit, 'archives' => $archives, 'audit_base_hash' => $base];
    }

    /** A dedicated random secret survives ordinary Laravel APP_KEY rotations. */
    private function auditSecret(): string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::AUDIT_KEY_PATH)) {
            throw new \RuntimeException('Private news audit key missing. Restore its matching snapshot before editing.');
        }
        try { $data = json_decode($disk->get(self::AUDIT_KEY_PATH), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('News audit key file is damaged.', 0, $e); }
        $hex = $data['key_hex'] ?? null;
        if (($data['version'] ?? null) !== 1 || ! is_string($hex) || ! preg_match('/^[0-9a-f]{64}$/D', $hex)) {
            throw new \RuntimeException('Invalid news audit key.');
        }
        return hex2bin($hex);
    }

    private function createDedicatedAuditKey(): string
    {
        $disk = Storage::disk('local');
        if ($disk->exists(self::AUDIT_KEY_PATH)) return $this->auditSecret();
        $secret = random_bytes(32);
        $json = json_encode(['version' => 1, 'key_hex' => bin2hex($secret)], JSON_THROW_ON_ERROR);
        $target = $disk->path(self::AUDIT_KEY_PATH);
        // Lock is already held by mutate(). Exclusive create prevents accidental replacement.
        $h = fopen($target, 'x');
        if ($h === false) throw new \RuntimeException('Cannot securely create independent audit key.');
        try {
            if (fwrite($h, $json) !== strlen($json) || ! fflush($h)) throw new \RuntimeException('Audit key save failed.');
        } finally { fclose($h); }
        @chmod($target, 0600);
        if (! hash_equals($secret, $this->auditSecret())) throw new \RuntimeException('Audit key verification failed.');
        return $secret;
    }

    private function signDocument(array $data, string $key): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $key);
    }

    /** Legacy v3 events are verified with the existing APP_KEY, never silently trusted. */
    private function signAudit(array $event): string
    {
        $key = isset($event['key_id']) ? $this->auditSecret() : (string) config('app.key');
        if (strlen($key) < 20) throw new \RuntimeException('Audit signing key missing; edits disabled.');
        return $this->signDocument($event, $key);
    }

    private function verifyEvents(array $events, string $previous): string
    {
        foreach ($events as $event) {
            if (! is_array($event) || ! isset($event['hash'], $event['previous_hash'])
                || ! is_string($event['hash']) || ! hash_equals($previous, (string) $event['previous_hash'])) {
                throw new \RuntimeException('External-news audit hash chain is invalid.');
            }
            $unsigned = $event;
            unset($unsigned['hash']);
            if (! hash_equals($this->signAudit($unsigned), $event['hash'])) {
                throw new \RuntimeException('External-news audit signature mismatch; edits disabled.');
            }
            $previous = $event['hash'];
        }
        return $previous;
    }

    private function migrateLegacyAudit(array $events): array
    {
        $this->verifyEvents($events, '');
        $previous = '';
        foreach ($events as &$event) {
            $event['key_id'] = 'dao-local-v1';
            $event['previous_hash'] = $previous;
            unset($event['hash']);
            $event['hash'] = $this->signAudit($event);
            $previous = $event['hash'];
        }
        unset($event);
        return $events;
    }

    /** Includes the JSON, independent signing secret, and every referenced audit archive. */
    private function writeVerifiedSnapshot($disk, ?string $replacementContent = null): void
    {
        if ($replacementContent === null && ! $disk->exists(self::PATH)) return;
        $content = $replacementContent ?? $disk->get(self::PATH);
        try { $doc = json_decode($content, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('Cannot snapshot corrupt news JSON.', 0, $e); }
        if (! is_array($doc)) throw new \RuntimeException('Cannot snapshot invalid news data.');
        $name = gmdate('Ymd\THis').'-'.bin2hex(random_bytes(8));
        $root = self::SNAPSHOT_DIR.'/'.$name;
        if (! $disk->put($root.'/entries.json', $content)
            || ! hash_equals(hash('sha256', $content), hash('sha256', $disk->get($root.'/entries.json')))) {
            throw new \RuntimeException('News snapshot could not be saved or verified.');
        }
        $keyDigest = null;
        if ($disk->exists(self::AUDIT_KEY_PATH)) {
            if (! $disk->copy(self::AUDIT_KEY_PATH, $root.'/audit-key.json')) {
                throw new \RuntimeException('Audit key snapshot creation failed.');
            }
            $keyDigest = hash('sha256', $disk->get(self::AUDIT_KEY_PATH));
            if (! hash_equals($keyDigest, hash('sha256', $disk->get($root.'/audit-key.json')))) {
                throw new \RuntimeException('Audit key snapshot verification failed.');
            }
        }
        $archiveDigests = [];
        foreach (($doc['archives'] ?? []) as $archive) {
            if (! is_string($archive) || ! preg_match('/^[0-9]{8}T[0-9]{6}-[0-9a-f]{16}\.json$/D', $archive)) {
                throw new \RuntimeException('Invalid audit archive name in snapshot.');
            }
            $source = self::ARCHIVE_DIR.'/'.$archive;
            if (! $disk->exists($source) || ! $disk->copy($source, $root.'/archives/'.$archive)) {
                throw new \RuntimeException('Referenced archive snapshot failed.');
            }
            $archiveDigests[$archive] = hash('sha256', $disk->get($source));
            if (! hash_equals($archiveDigests[$archive], hash('sha256', $disk->get($root.'/archives/'.$archive)))) {
                throw new \RuntimeException('Archive snapshot checksum mismatch.');
            }
        }
        $metadata = ['version' => 1, 'entry_sha256' => hash('sha256', $content),
            'key_sha256' => $keyDigest, 'archives' => $archiveDigests];
        if (! $disk->put($root.'/manifest.json', json_encode($metadata, JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('News snapshot manifest save failed.');
        }
    }

    public function availableSnapshots(): array
    {
        $dirs = Storage::disk('local')->directories(self::SNAPSHOT_DIR);
        $valid = array_values(array_filter($dirs, fn ($name) => (bool) preg_match('~^'.preg_quote(self::SNAPSHOT_DIR,'~').'/[0-9]{8}T[0-9]{6}-[0-9a-f]{16}$~D', $name)));
        rsort($valid, SORT_STRING);
        return $valid;
    }

    /** Explicit host allowlist, *not* a suffix wildcard or a checkbox. */
    public function isTrustedUrl(string $url): bool
    {
        if (! $this->hasValidHttpsStructure($url)) return false;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Only the optional conventional www prefix is accepted.
        $root = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        return in_array($root, config('dao_news.trusted_hosts', []), true);
    }

    public function isApprovedAndCurrent(array $row): bool
    {
        $review = (string) ($row['reviewed_at'] ?? '');
        $today = gmdate('Y-m-d');
        $published = (string) ($row['published'] ?? '');
        $relevance = (string) ($row['dao_verified_at'] ?? '');
        if (! in_array((string) ($row['link_status'] ?? 'manual'), ['manual', 'ok', 'manual_check'], true)
            || ! $this->isCalendarDate($review) || ! $this->isCalendarDate($relevance)
            || ! $this->isTrustedUrl((string) ($row['url'] ?? ''))
            || ! $this->isRecentPublication($published)) return false;
        $reviewDays = max(1, min(365, (int) config('dao_news.review_days', 60)));
        return $review >= gmdate('Y-m-d', strtotime('-'.$reviewDays.' days'))
            && $review <= $today && $relevance === $review;
    }

    public function addCuratedLink(string $url, string $topic, ?string $published): void
    {
        $url = trim($url);
        $this->assertAllowed($url, $topic, $published);
        $id = (string) Str::uuid();
        $this->mutate(function (array $items) use ($url, $topic, $published, $id): array {
            foreach ($items as $item) {
                if (rtrim($item['url'], '/') === rtrim($url, '/')) {
                    throw ValidationException::withMessages(['url' => 'This link is already listed.']);
                }
            }
            if (count($items) >= self::MAX_STORED_LINKS) {
                throw ValidationException::withMessages(['url' => 'The curated link directory is full. Remove an old entry before adding another.']);
            }
            array_unshift($items, [
                'id' => $id, 'url' => $url, 'topic' => $topic,
                'published' => $published ?: '', 'reviewed_at' => gmdate('Y-m-d'),
                'dao_verified_at' => gmdate('Y-m-d'), 'reviewed_by' => auth()->id(),
                'link_status' => 'manual', 'last_checked_at' => '', 'last_http_status' => null,
            ]);
            return $items;
        }, 'added', $id);
    }

    /** Previously stored links, including legacy links, are hidden until Admin re-verifies them. */
    public function reviewCuratedLink(string $id, ?string $published = null): void
    {
        // The latest publication date must be checked against the actual original article.
        if (! $this->isRecentPublication((string) $published)) {
            throw ValidationException::withMessages(['published' => 'Use the article actual publication date (within the past 90 days).']);
        }
        $this->mutate(function (array $items) use ($id, $published): array {
            $found = false;
            foreach ($items as &$row) {
                if ($row['id'] !== $id) continue;
                $found = true;
                if (! $this->isTrustedUrl($row['url'])) {
                    throw ValidationException::withMessages(['review_id' => 'This source is not on the approved publisher list. Remove it or request a source review.']);
                }
                $row['published'] = $published;
                $row['reviewed_at'] = gmdate('Y-m-d');
                $row['dao_verified_at'] = gmdate('Y-m-d');
                $row['reviewed_by'] = auth()->id();
                $row['link_status'] = 'manual'; // Manually rechecked on publisher website.
                $row['last_checked_at'] = '';
                $row['last_http_status'] = null;
            }
            unset($row);
            if (! $found) throw ValidationException::withMessages(['review_id' => 'The link no longer exists.']);
            return $items;
        }, 'reviewed', $id);
    }

    public function removeCuratedLink(string $id): void
    {
        $this->mutate(function (array $items) use ($id): array {
            $filtered = array_values(array_filter($items, fn (array $row): bool => $row['id'] !== $id));
            if (count($filtered) === count($items)) {
                throw ValidationException::withMessages(['url' => 'Link not found; nothing was removed.']);
            }
            return $filtered;
        }, 'removed', $id);
    }

    /** Editing keeps the original UUID and logs before/after versions. */
    public function editCuratedLink(string $id, string $url, string $topic, string $published): void
    {
        $url = trim($url);
        $this->assertAllowed($url, $topic, $published);
        $this->mutate(function (array $items) use ($id, $url, $topic, $published): array {
            $found = false;
            foreach ($items as $row) {
                if ($row['id'] !== $id && rtrim($row['url'], '/') === rtrim($url, '/')) {
                    throw ValidationException::withMessages(['url' => 'Another entry already uses this URL.']);
                }
            }
            foreach ($items as &$row) {
                if ($row['id'] !== $id) continue;
                $found = true;
                $row['url'] = $url;
                $row['topic'] = $topic;
                $row['published'] = $published;
                $row['reviewed_at'] = gmdate('Y-m-d');
                $row['dao_verified_at'] = gmdate('Y-m-d');
                $row['reviewed_by'] = auth()->id();
                $row['link_status'] = 'manual';
                $row['last_checked_at'] = '';
                $row['last_http_status'] = null;
            }
            unset($row);
            if (! $found) throw ValidationException::withMessages(['edit_id' => 'Link no longer exists.']);
            return $items;
        }, 'edited', $id);
    }

    /** Admin can immediately unpublish an item with incorrect or changed publisher content. */
    public function flagCuratedLink(string $id): void
    {
        $this->mutate(function (array $items) use ($id): array {
            $found = false;
            foreach ($items as &$row) {
                if ($row['id'] !== $id) continue;
                $found = true;
                $row['reviewed_at'] = '';
                $row['dao_verified_at'] = '';
                $row['link_status'] = 'manual_check';
            }
            unset($row);
            if (! $found) throw ValidationException::withMessages(['flag_id' => 'Link not found.']);
            return $items;
        }, 'unpublished', $id);
    }

    /** HEAD only: no content is downloaded, no redirects are followed, and public DNS is pinned. */
    public function checkCuratedLink(string $id, ?callable $probe = null): string
    {
        $existing = collect($this->entries())->firstWhere('id', $id);
        if ($existing === null) throw ValidationException::withMessages(['check_id' => 'Link not found.']);
        if (! $this->isTrustedUrl($existing['url'])) {
            throw ValidationException::withMessages(['check_id' => 'The source is not allowlisted.']);
        }
        $result = $probe ? $probe($existing['url']) : $this->probeUrlHeaders($existing['url']);
        $code = $result['code'] ?? null;
        $status = $result['state'] ?? 'unreachable';
        if ($status === 'unreachable') $status = 'manual_check'; // Timeout is inconclusive, not evidence of a deleted article.
        if (! in_array($status, ['ok', 'broken', 'redirected', 'unreachable', 'manual_check'], true)
            || ($code !== null && (! is_int($code) || $code < 100 || $code > 599))) {
            throw new \RuntimeException('Invalid link-health check result.');
        }
        $this->mutate(function (array $items) use ($id, $existing, $status, $code): array {
            $found = false;
            foreach ($items as &$row) {
                if ($row['id'] !== $id) continue;
                $found = true;
                if ($row['url'] !== $existing['url']) {
                    throw ValidationException::withMessages(['check_id' => 'Article URL changed while checking. Retry.']);
                }
                $row['link_status'] = $status;
                $row['last_checked_at'] = gmdate('c');
                $row['last_http_status'] = $code;
                if (in_array($status, ['broken', 'redirected'], true)) {
                    $row['reviewed_at'] = '';
                    $row['dao_verified_at'] = '';
                }
            }
            unset($row);
            if (! $found) throw ValidationException::withMessages(['check_id' => 'Link was removed while checking.']);
            return $items;
        }, 'link_checked', $id);
        return $status;
    }

    private function probeUrlHeaders(string $url): array
    {
        // Fail closed if the environment lacks HEAD support; never silently use GET.
        if (! function_exists('curl_init') || ! function_exists('dns_get_record')) {
            return ['state' => 'manual_check', 'code' => null];
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (! $this->isTrustedUrl($url)) return ['state' => 'manual_check', 'code' => null];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) return ['state' => 'unreachable', 'code' => null];
        $publicIp = null;
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $publicIp = $ip;
                break;
            }
        }
        if ($publicIp === null) return ['state' => 'unreachable', 'code' => null];
        $pin = $host.':443:'.(str_contains($publicIp, ':') ? '['.$publicIp.']' : $publicIp);
        $ch = curl_init($url);
        if ($ch === false) return ['state' => 'unreachable', 'code' => null];
        try {
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 7,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_RESOLVE => [$pin],
                CURLOPT_USERAGENT => 'TabangNow-NewsLinkCheck/1.0',
            ]);
            if (defined('CURLOPT_PROTOCOLS_STR')) curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'https');
            else curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false || $code < 100 || $code > 599) {
                return ['state' => 'unreachable', 'code' => null];
            }
            if ($code >= 200 && $code < 300) return ['state' => 'ok', 'code' => $code];
            if ($code >= 300 && $code < 400) return ['state' => 'redirected', 'code' => $code];
            if ($code === 404 || $code === 410) return ['state' => 'broken', 'code' => $code];
            return ['state' => 'manual_check', 'code' => $code];
        } finally {
            curl_close($ch);
        }
    }

    /** All mutations are lock-serialized and snapshot-backed; no write on invalid audit. */
    private function mutate(callable $change, string $action, string $id): void
    {
        $disk = Storage::disk('local');
        $directory = dirname($disk->path(self::PATH));
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Cannot create private external-link storage folder.');
        }
        $handle = fopen($directory.DIRECTORY_SEPARATOR.'.entries.lock', 'c');
        if ($handle === false) throw new \RuntimeException('Cannot open external-link update lock.');
        try {
            if (! flock($handle, LOCK_EX)) throw new \RuntimeException('Cannot lock external-link data.');
            $data = $this->readDocument(); // Fail closed on corrupt files, audits or missing keys.
            $items = $change($data['entries']);
            $this->writeVerifiedSnapshot($disk); // Preserve originals BEFORE audit-key migration.
            $this->createDedicatedAuditKey();
            $old = $data['audit'];
            $archives = $data['archives'];
            $base = $data['audit_base_hash'];
            // Legacy history was verified above, then re-signed without losing events or actor IDs.
            if ($disk->exists(self::PATH)) {
                $rawData = json_decode($disk->get(self::PATH), true, 512, JSON_THROW_ON_ERROR);
                if ((int) ($rawData['version'] ?? 1) < 4) $old = $this->migrateLegacyAudit($old);
            }
            $before = collect($data['entries'])->firstWhere('id', $id);
            $after = collect($items)->firstWhere('id', $id);
            $event = [
                'action' => $action, 'link_id' => $id, 'actor_id' => auth()->id(),
                'at' => gmdate('c'), 'before' => $before, 'after' => $after,
                'previous_hash' => $old === [] ? $base : $old[count($old) - 1]['hash'],
                'key_id' => 'dao-local-v1',
            ];
            $event['hash'] = $this->signAudit($event);
            $old[] = $event;
            $archiveThreshold = max(10, min(3900, (int) config('dao_news.audit_archive_threshold', 3000)));
            if (count($old) >= $archiveThreshold) {
                $countToArchive = max(1, intdiv(count($old), 2));
                $segmentEvents = array_splice($old, 0, $countToArchive);
                $archiveName = gmdate('Ymd\THis').'-'.bin2hex(random_bytes(8)).'.json';
                $archive = ['version' => 1, 'events' => $segmentEvents];
                $archive['seal'] = $this->signDocument($archive, $this->auditSecret());
                if (! $disk->put(self::ARCHIVE_DIR.'/'.$archiveName,
                    json_encode($archive, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Audit archive write failed; no news changes saved.');
                }
                $archives[] = $archiveName;
                $base = $segmentEvents[count($segmentEvents)-1]['hash'];
            }
            $document = ['version' => 4, 'entries' => $items, 'audit' => $old,
                'archives' => $archives, 'audit_base_hash' => $base];
            $document['document_seal'] = $this->signDocument($document, $this->auditSecret());
            $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (strlen($json) > 4000000) throw new \RuntimeException('News storage size cap reached; update canceled.');
            // Back up the NEW signed document and matching key before replacing the live file.
            $this->writeVerifiedSnapshot($disk, $json);
            if ($disk->exists(self::PATH) && ! $disk->copy(self::PATH, self::PATH.'.bak')) {
                throw new \RuntimeException('Cannot back up external-link data; update canceled.');
            }
            // Write same-directory temp then rename for atomic replacement on the local disk.
            $tmp = $directory.DIRECTORY_SEPARATOR.'.entries-'.bin2hex(random_bytes(8)).'.tmp';
            try {
                if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || ! rename($tmp, $disk->path(self::PATH))) {
                    throw new \RuntimeException('Atomic news document replacement failed; prior file remains backed up.');
                }
            } finally { if (file_exists($tmp)) unlink($tmp); }
            // Keep 40 recent verified snapshots. Pruning must never break successful writes.
            try {
                $all = $this->availableSnapshots();
                foreach (array_slice($all, 40) as $oldSnapshot) $disk->deleteDirectory($oldSnapshot);
            } catch (\Throwable $e) { report($e); }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function assertAllowed(string $url, string $topic, ?string $published): void
    {
        if (! $this->isTrustedUrl($url)) {
            throw ValidationException::withMessages(['url' => 'Use a direct HTTPS story link from an approved government agency or publisher; no query strings, tracking links or redirects.']);
        }
        if (! in_array($topic, self::TOPICS, true)) {
            throw ValidationException::withMessages(['topic' => 'Choose an allowed category.']);
        }
        if (! $this->isRecentPublication((string) $published)) {
            throw ValidationException::withMessages(['published' => 'The original article must have a verified publication date within the past 90 days.']);
        }
    }

    private function isRecentPublication(string $date): bool
    {
        if (! $this->isCalendarDate($date)) return false;
        $ageDays = max(1, min(365, (int) config('dao_news.max_story_age_days', 90)));
        return $date <= gmdate('Y-m-d') && $date >= gmdate('Y-m-d', strtotime('-'.$ageDays.' days'));
    }

    private function isCalendarDate(string $date): bool
    {
        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year);
    }

    private function hasValidHttpsStructure(string $url): bool
    {
        // Reject ambiguous/browser-normalized URLs before validating the canonical host.
        if ($url === '' || strlen($url) > 1800 || preg_match('/[\x00-\x20\x7f]/', $url)
            || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) return false;
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/D', $host)) return false;
        $path = (string) ($parts['path'] ?? '');
        if (str_contains($path, '\\') || str_contains($path, '//')
            || preg_match('~(?:^|/)(?:\.\.?)(?:/|$)~', $path)
            || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|2f|5c|3f|23|40|25|2e)/i', $path)
            || preg_match('~(?:^|/)(?:redirect|out|forward|external-link)(?:/|$)~i', $path)) return false;
        return true;
    }
}
