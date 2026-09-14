<?php

namespace App\Services\Appearance;

/**
 * Allowed iframe hosts for embed / map / video widgets.
 */
final class EmbedHostAllowlist
{
    /** @var list<string> */
    public const HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
        'youtu.be',
        'player.vimeo.com',
        'vimeo.com',
        'www.google.com',
        'maps.google.com',
        'www.google.com',
        'maps.googleapis.com',
    ];

    public static function isAllowedUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (preg_match('#^(javascript|data|vbscript):#i', $url) === 1) {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only iframe tags whose src host is allowlisted.
     */
    public static function sanitizeIframeHtml(string $html): string
    {
        $kept = [];
        if (preg_match_all('/<iframe\b[^>]*src=["\']([^"\']+)["\'][^>]*(?:\/>|>.*?<\/iframe>)/is', $html, $m)) {
            foreach ($m[0] as $i => $tag) {
                if (self::isAllowedUrl((string) ($m[1][$i] ?? ''))) {
                    $kept[] = $tag;
                }
            }
        }

        return implode('', $kept);
    }
}
