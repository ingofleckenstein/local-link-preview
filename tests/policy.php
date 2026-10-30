<?php
// SPDX-License-Identifier: MIT
class Yii { public static $app; }
Yii::$app = (object) ['request' => (object) ['hostInfo' => 'https://community.example']];
require __DIR__ . '/../services/PreviewPolicy.php';
require __DIR__ . '/../services/PreviewFetcher.php';
use humhub\modules\localLinkPreview\services\PreviewPolicy;
use humhub\modules\localLinkPreview\services\PreviewFetcher;
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
foreach (['https://community.example/u/alice/', '/u/alice', 'https://COMMUNITY.EXAMPLE./user/profile/home?uguid=abc', 'https://community.example/index.php?r=user%2Fprofile%2Fhome', 'https://community.example/index.php/user/profile/stream'] as $url) { check(PreviewPolicy::isInternalProfile($url), 'Profile not excluded: ' . $url); }
foreach (['https://outside.example/u/alice/', 'https://community.example/s/group/', 'https://outside.example/story'] as $url) { check(!PreviewPolicy::isInternalProfile($url), 'Wrongly excluded: ' . $url); }
check(PreviewPolicy::appearsInMessage('https://outside.example/?a=1&b=2', '<a href="https://outside.example/?a=1&amp;b=2">Link</a>'), 'Encoded URL lost');
$fetcher = new PreviewFetcher();
$parse = new ReflectionMethod($fetcher, 'parse');
$meta = $parse->invoke($fetcher, '<title>Fallback</title><meta property="og:title" content=""><meta name="twitter:title" content="Titel"><meta property="og:image:secure_url" content="https://cdn.example/first.jpg"><meta property="og:image" content="/second.jpg"><meta name="twitter:image:src" content="third.jpg"><link rel="image_src" href="/fourth.png">', 'https://outside.example/articles/post');
check($meta['title'] === 'Titel', 'Nonempty metadata fallback failed');
check($meta['images'] === ['https://cdn.example/first.jpg', 'https://outside.example/second.jpg', 'https://outside.example/articles/third.jpg', 'https://outside.example/fourth.png'], 'Image candidates incorrect');
$normalise = new ReflectionMethod($fetcher, 'normaliseUrl');
foreach (['https://community.example/u/alice/', 'file:///etc/passwd', 'https://user:pass@outside.example/', 'https://outside.example:8080/'] as $url) {
    $rejected = false; try {$normalise->invoke($fetcher, $url);} catch (RuntimeException $e) {$rejected = true;} check($rejected, 'Unsafe/profile URL accepted: ' . $url);
}
echo "PASS: profile filtering, encoded links, metadata/image fallbacks and URL protections\n";
