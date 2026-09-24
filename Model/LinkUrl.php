<?php
/**
 * Copyright (C) 2026 Benjamin Rosenberger <bensch.rosenberger@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @copyright 2026 Benjamin Rosenberger
 * @author bensch.rosenberger@gmail.com
 * @license MIT
 * @link https://brocode.at
 */
declare(strict_types=1);

namespace BroCode\AdminhtmlQuickLinks\Model;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Turns a browser URL into what a quick link stores, and a stored link back into an href.
 *
 * Admin URLs carry a secret key that changes with every session, so an internal link is
 * stored as a key-free route path and rebuilt through the backend URL builder on render.
 * The route segment is stored as the route *id*, not its frontName: the builder computes
 * the key from the id (`adminhtml`), while the URL shows the frontName (`admin`).
 */
class LinkUrl
{
    public const MAX_LENGTH = 2048;

    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const DROPPED_PARAMS = ['key', 'form_key'];
    private const DEFAULT_PATH = 'adminhtml/dashboard/index';

    /**
     * @var UrlInterface
     */
    private $backendUrl;

    /**
     * @var FrontNameResolver
     */
    private $frontNameResolver;

    /**
     * @var RouteConfigInterface
     */
    private $routeConfig;

    public function __construct(
        UrlInterface $backendUrl,
        FrontNameResolver $frontNameResolver,
        RouteConfigInterface $routeConfig
    ) {
        $this->backendUrl = $backendUrl;
        $this->frontNameResolver = $frontNameResolver;
        $this->routeConfig = $routeConfig;
    }

    /**
     * @return array{url: string, is_external: bool}
     * @throws LocalizedException when the URL is not an absolute http(s) URL, too long,
     *                            or points at an admin route that does not exist
     */
    public function normalize(string $inputUrl): array
    {
        $inputUrl = trim($inputUrl);
        $parts = parse_url($inputUrl);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));

        if ($parts === false || !isset($parts['host']) || !in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new LocalizedException(__('Only absolute http:// or https:// URLs can be saved as quick links.'));
        }

        if (strlen($inputUrl) > self::MAX_LENGTH) {
            throw new LocalizedException(__('The URL is longer than %1 characters.', self::MAX_LENGTH));
        }

        $adminPath = $this->extractAdminPath($parts);

        return $adminPath === null
            ? ['url' => $inputUrl, 'is_external' => true]
            : ['url' => $adminPath, 'is_external' => false];
    }

    public function toHref(QuickLinkInterface $link): string
    {
        if ($link->isExternal()) {
            return $link->getUrl();
        }

        [$path, $query] = array_pad(explode('?', $link->getUrl(), 2), 2, '');
        $params = [];

        if ($query !== '') {
            parse_str($query, $queryParams);
            $params['_query'] = $queryParams;
        }

        return (string)$this->backendUrl->getUrl($path, $params);
    }

    /**
     * The key-free route path for a URL inside this admin, or null for anything else.
     *
     * @param array<string, mixed> $parts result of parse_url()
     * @throws LocalizedException
     */
    private function extractAdminPath(array $parts): ?string
    {
        $segments = $this->stripBasePath($parts);

        if ($segments === null || array_shift($segments) !== $this->frontNameResolver->getFrontName()) {
            return null;
        }

        if ($segments === []) {
            return self::DEFAULT_PATH;
        }

        $routeId = $this->routeConfig->getRouteByFrontName($segments[0], Area::AREA_ADMINHTML);

        if (!$routeId) {
            throw new LocalizedException(__('The URL does not point at a known admin page.'));
        }

        $path = [$routeId, $segments[1] ?? 'index', $segments[2] ?? 'index'];
        $pairs = array_chunk(array_slice($segments, 3), 2);

        foreach ($pairs as $pair) {
            if (count($pair) === 2 && !in_array($pair[0], self::DROPPED_PARAMS, true)) {
                array_push($path, $pair[0], $pair[1]);
            }
        }

        return implode('/', $path) . $this->filterQuery((string)($parts['query'] ?? ''));
    }

    /**
     * Path segments after the admin base URL, or null when the URL is on another host.
     *
     * @param array<string, mixed> $parts
     * @return string[]|null
     */
    private function stripBasePath(array $parts): ?array
    {
        $base = parse_url((string)$this->backendUrl->getBaseUrl());

        if (strcasecmp((string)$parts['host'], (string)($base['host'] ?? '')) !== 0
            || ($parts['port'] ?? null) !== ($base['port'] ?? null)
        ) {
            return null;
        }

        $segments = $this->splitPath((string)($parts['path'] ?? ''));

        foreach ($this->splitPath((string)($base['path'] ?? '')) as $baseSegment) {
            if (($segments[0] ?? null) !== $baseSegment) {
                return null;
            }
            array_shift($segments);
        }

        if (($segments[0] ?? null) === 'index.php') {
            array_shift($segments);
        }

        return $segments;
    }

    /**
     * @return string[]
     */
    private function splitPath(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static function (string $segment): bool {
            return $segment !== '';
        }));
    }

    private function filterQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $params = array_diff_key($params, array_flip(self::DROPPED_PARAMS));

        return $params === [] ? '' : '?' . http_build_query($params);
    }
}
