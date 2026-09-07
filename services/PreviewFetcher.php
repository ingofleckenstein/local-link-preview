<?php

namespace humhub\modules\localLinkPreview\services;

use DOMDocument;
use DOMXPath;
use humhub\modules\localLinkPreview\models\LinkPreview;
use RuntimeException;
use Yii;
use yii\helpers\FileHelper;

class PreviewFetcher
{
    private const MAX_HTML_BYTES = 2097152;
    private const USER_AGENT = 'HumHub LocalLinkPreview/0.0.3';

    public function get(string $url): LinkPreview
    {
        $url = $this->normaliseUrl($url);
        $hash = hash('sha256', $url);
        $cached = LinkPreview::findOne(['url_hash' => $hash]);
        $cacheTtl = max(1, (int) Yii::$app->getModule('local-link-preview')->settings->get('cacheDays', 7)) * 86400;
        if ($cached && $cached->fetched_at >= time() - $cacheTtl) {
            return $cached;
        }

        [$html, $finalUrl] = $this->request($url, self::MAX_HTML_BYTES, ['text/html', 'application/xhtml+xml']);
        $meta = $this->parse($html, $finalUrl);
        $model = $cached ?: new LinkPreview(['url_hash' => $hash]);
        $model->url = $finalUrl;
        $model->title = $meta['title'];
        $model->description = $meta['description'];
        $model->site_name = $meta['siteName'];
        $model->fetched_at = time();

        if ($meta['image']) {
            try {
                $imageLimit = max(1, (int) Yii::$app->getModule('local-link-preview')->settings->get('maxImageMb', 5)) * 1048576;
                [$image, , $mime] = $this->request($meta['image'], $imageLimit, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
                if ($extension && @getimagesizefromstring($image) !== false) {
                    FileHelper::createDirectory(self::imageDirectory(), 0750, true);
                    $filename = $hash . '.' . $extension;
                    file_put_contents(self::imageDirectory() . DIRECTORY_SEPARATOR . $filename, $image, LOCK_EX);
                    $model->image_file = $filename;
                    $model->image_mime = $mime;
                }
            } catch (\Throwable $e) {
                Yii::warning('Vorschaubild konnte nicht lokal gespeichert werden: ' . $e->getMessage(), 'local-link-preview');
            }
        }

        if (!$model->save()) {
            throw new RuntimeException('Vorschau konnte nicht gespeichert werden.');
        }
        return $model;
    }

    public static function imageDirectory(): string
    {
        return Yii::getAlias('@runtime/local-link-preview/images');
    }

    private function request(string $url, int $limit, array $allowedTypes, int $redirects = 0): array
    {
        if ($redirects > 3) {
            throw new RuntimeException('Zu viele Weiterleitungen.');
        }
        $url = $this->normaliseUrl($url);
        $parts = parse_url($url);
        $ips = $this->publicIps($parts['host']);
        $port = ($parts['scheme'] === 'https') ? 443 : 80;
        $headers = [];
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $ips[0]],
            CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$headers) {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, $chunk) use (&$body, $limit) {
                if (strlen($body) + strlen($chunk) > $limit) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mime = strtolower(trim(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
        $error = curl_error($ch);
        curl_close($ch);

        if ($status >= 300 && $status < 400 && isset($headers['location'])) {
            return $this->request($this->absoluteUrl($headers['location'], $url), $limit, $allowedTypes, $redirects + 1);
        }
        if (!$ok || $status < 200 || $status >= 300) {
            throw new RuntimeException('Externe Seite nicht erreichbar: ' . ($error ?: 'HTTP ' . $status));
        }
        if (!in_array($mime, $allowedTypes, true)) {
            throw new RuntimeException('Nicht unterstützter Inhaltstyp.');
        }
        return [$body, $url, $mime];
    }

    private function publicIps(string $host): array
    {
        $records = dns_get_record($host, DNS_A);
        $ips = array_column($records ?: [], 'ip');
        if (!$ips || count($ips) > 8) {
            throw new RuntimeException('Host konnte nicht sicher aufgelöst werden.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Lokale oder reservierte Zieladressen sind nicht erlaubt.');
            }
        }
        return $ips;
    }

    private function normaliseUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Ungültige URL.');
        }
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Nur öffentliche HTTP- und HTTPS-Adressen sind erlaubt.');
        }
        if (isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
            throw new RuntimeException('Nur die Standardports 80 und 443 sind erlaubt.');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        $blocked = preg_split('/\R+/', (string) Yii::$app->getModule('local-link-preview')->settings->get('blockedDomains', ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($blocked as $blockedHost) {
            $blockedHost = strtolower(trim($blockedHost));
            if ($blockedHost !== '' && ($host === $blockedHost || str_ends_with($host, '.' . $blockedHost))) {
                throw new RuntimeException('Diese Domain ist für Linkvorschauen gesperrt.');
            }
        }
        $this->publicIps($parts['host']);
        return $url;
    }

    private function parse(string $html, string $baseUrl): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new DOMXPath($document);
        $meta = function (array $names) use ($xpath): ?string {
            foreach ($names as $name) {
                $query = '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . strtolower($name) . '" or translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . strtolower($name) . '"]/@content';
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length) {
                    return trim($nodes->item(0)->nodeValue);
                }
            }
            return null;
        };
        $title = $meta(['og:title', 'twitter:title']);
        if (!$title) {
            $nodes = $xpath->query('//title');
            $title = $nodes && $nodes->length ? trim($nodes->item(0)->textContent) : parse_url($baseUrl, PHP_URL_HOST);
        }
        $image = $meta(['og:image', 'twitter:image']);
        return [
            'title' => $this->clean($title, 512),
            'description' => $this->clean($meta(['og:description', 'twitter:description', 'description']) ?: '', 500),
            'siteName' => $this->clean($meta(['og:site_name']) ?: parse_url($baseUrl, PHP_URL_HOST), 255),
            'image' => $image ? $this->absoluteUrl($image, $baseUrl) : null,
        ];
    }

    private function clean(string $value, int $length): string
    {
        $value = preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5))) ?: '';
        return mb_substr(trim($value), 0, $length);
    }

    private function absoluteUrl(string $candidate, string $base): string
    {
        $candidate = trim($candidate);
        if (preg_match('~^https?://~i', $candidate)) {
            return $candidate;
        }
        $baseParts = parse_url($base);
        if (str_starts_with($candidate, '//')) {
            return $baseParts['scheme'] . ':' . $candidate;
        }
        if (str_starts_with($candidate, '/')) {
            return $baseParts['scheme'] . '://' . $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '') . $candidate;
        }
        $path = $baseParts['path'] ?? '/';
        return $baseParts['scheme'] . '://' . $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '') . rtrim(dirname($path), '/\\') . '/' . $candidate;
    }
}
