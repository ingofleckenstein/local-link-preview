<?php
// SPDX-License-Identifier: MIT
namespace humhub\modules\localLinkPreview\services;

use Yii;

final class PreviewPolicy
{
    public static function isInternalProfile(string $url): bool
    {
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if (!$parts) { return false; }
        $localHost = strtolower(rtrim((string) parse_url(Yii::$app->request->hostInfo, PHP_URL_HOST), '.'));
        $host = strtolower(rtrim($parts['host'] ?? $localHost, '.'));
        if ($host !== $localHost) { return false; }
        $path = rawurldecode($parts['path'] ?? '/');
        parse_str($parts['query'] ?? '', $query);
        $route = ltrim(is_string($query['r'] ?? null) ? $query['r'] : '', '/');
        return (bool) preg_match('~(?:^|/)(?:u(?:/|$)|user/profile(?:/|$))~i', $path)
            || str_starts_with(strtolower($route), 'user/profile/');
    }

    public static function appearsInMessage(string $url, string $message): bool
    {
        return str_contains(html_entity_decode($message, ENT_QUOTES | ENT_HTML5), $url);
    }
}
