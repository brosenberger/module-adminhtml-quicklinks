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

namespace BroCode\AdminhtmlQuickLinks\Test\Unit\Model;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Model\LinkUrl;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkUrlTest extends TestCase
{
    private const BASE_URL = 'https://shop.example.test/';

    /**
     * @var UrlInterface&MockObject
     */
    private $backendUrl;

    /**
     * @var LinkUrl
     */
    private $linkUrl;

    protected function setUp(): void
    {
        $this->backendUrl = $this->createMock(UrlInterface::class);
        $this->backendUrl->method('getBaseUrl')->willReturn(self::BASE_URL);

        $frontNameResolver = $this->createMock(FrontNameResolver::class);
        $frontNameResolver->method('getFrontName')->willReturn('admin');

        $routeConfig = $this->createMock(RouteConfigInterface::class);
        $routeConfig->method('getRouteByFrontName')->willReturnMap([
            ['admin', 'adminhtml', 'adminhtml'],
            ['sales', 'adminhtml', 'sales'],
            ['catalog', 'adminhtml', 'catalog'],
            ['nope', 'adminhtml', false],
        ]);

        $this->linkUrl = new LinkUrl($this->backendUrl, $frontNameResolver, $routeConfig);
    }

    public function testStripsSecretKeyAndAdminFrontName(): void
    {
        $result = $this->linkUrl->normalize(
            'https://shop.example.test/admin/sales/order/view/order_id/5/key/0123456789abcdef/'
        );

        self::assertSame(['url' => 'sales/order/view/order_id/5', 'is_external' => false], $result);
    }

    public function testMapsAdminFrontNameToRouteIdSoTheSecretKeyValidates(): void
    {
        $result = $this->linkUrl->normalize('https://shop.example.test/admin/admin/dashboard/index/key/abc/');

        self::assertSame('adminhtml/dashboard/index', $result['url']);
    }

    public function testFillsMissingControllerAndAction(): void
    {
        self::assertSame('sales/index/index', $this->linkUrl->normalize('https://shop.example.test/admin/sales/')['url']);
        self::assertSame('adminhtml/dashboard/index', $this->linkUrl->normalize('https://shop.example.test/admin/')['url']);
    }

    public function testKeepsQueryStringButDropsKeys(): void
    {
        $result = $this->linkUrl->normalize(
            'https://shop.example.test/index.php/admin/catalog/product/index/key/abc/?search=bag&form_key=xyz#top'
        );

        self::assertSame('catalog/product/index?search=bag', $result['url']);
    }

    public function testForeignHostIsExternal(): void
    {
        $url = 'https://developer.adobe.com/commerce/docs/?q=1';

        self::assertSame(['url' => $url, 'is_external' => true], $this->linkUrl->normalize($url));
    }

    public function testStorefrontOnAdminHostIsExternal(): void
    {
        $url = 'https://shop.example.test/checkout/cart/';

        self::assertTrue($this->linkUrl->normalize($url)['is_external']);
    }

    /**
     * @dataProvider rejectedUrlProvider
     */
    public function testRejectsNonHttpUrls(string $url): void
    {
        $this->expectException(LocalizedException::class);

        $this->linkUrl->normalize($url);
    }

    public static function rejectedUrlProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'relative' => ['/admin/sales/order/'],
            'protocol-relative' => ['//evil.example/'],
            'empty' => ['   '],
            'too long' => ['https://example.test/' . str_repeat('a', 2048)],
        ];
    }

    public function testRejectsUnknownAdminRoute(): void
    {
        $this->expectException(LocalizedException::class);

        $this->linkUrl->normalize('https://shop.example.test/admin/nope/foo/bar/');
    }

    public function testToHrefRebuildsInternalLinkWithFreshKey(): void
    {
        $this->backendUrl->expects(self::once())
            ->method('getUrl')
            ->with('sales/order/view/order_id/5', [])
            ->willReturn('https://shop.example.test/admin/sales/order/view/order_id/5/key/fresh/');

        self::assertSame(
            'https://shop.example.test/admin/sales/order/view/order_id/5/key/fresh/',
            $this->linkUrl->toHref($this->link('sales/order/view/order_id/5', false))
        );
    }

    public function testToHrefPassesQueryString(): void
    {
        $this->backendUrl->expects(self::once())
            ->method('getUrl')
            ->with('catalog/product/index', ['_query' => ['search' => 'bag']])
            ->willReturn('x');

        $this->linkUrl->toHref($this->link('catalog/product/index?search=bag', false));
    }

    public function testToHrefReturnsExternalUrlUnchanged(): void
    {
        $this->backendUrl->expects(self::never())->method('getUrl');

        self::assertSame('https://example.test/a', $this->linkUrl->toHref($this->link('https://example.test/a', true)));
    }

    public function testSplitGridStateSeparatesSavedFiltersFromTheRestOfTheUrl(): void
    {
        $state = '{"f":{"status":"pending"},"ns":"sales_order_grid"}';

        self::assertSame(
            ['sales/order/index', $state],
            $this->linkUrl->splitGridState('sales/order/index?' . http_build_query([LinkUrl::GRID_STATE_PARAM => $state]))
        );
        self::assertSame(
            ['catalog/product/index?search=bag', $state],
            $this->linkUrl->splitGridState(
                'catalog/product/index?' . http_build_query(['search' => 'bag', LinkUrl::GRID_STATE_PARAM => $state])
            )
        );
    }

    public function testSplitGridStateWithoutSavedFilters(): void
    {
        self::assertSame(['sales/order/index', null], $this->linkUrl->splitGridState('sales/order/index'));
        self::assertSame(['sales/order/index?a=1', null], $this->linkUrl->splitGridState('sales/order/index?a=1'));
    }

    public function testGridStateWithoutFiltersOrKeywordIsUnfiltered(): void
    {
        self::assertTrue($this->linkUrl->isUnfilteredGridState(null));
        self::assertTrue($this->linkUrl->isUnfilteredGridState('{"ns":"sales_order_grid"}'));
        self::assertFalse($this->linkUrl->isUnfilteredGridState('{"f":{"status":"pending"},"ns":"sales_order_grid"}'));
        self::assertFalse($this->linkUrl->isUnfilteredGridState('{"ns":"sales_order_grid","s":"Veronica"}'));
    }

    public function testNormalizeKeepsSavedGridState(): void
    {
        $state = '{"f":{"status":"pending"},"ns":"sales_order_grid"}';
        $result = $this->linkUrl->normalize(
            'https://shop.example.test/admin/sales/order/index/key/abc/?' . http_build_query([LinkUrl::GRID_STATE_PARAM => $state])
        );

        self::assertSame(['sales/order/index', $state], $this->linkUrl->splitGridState($result['url']));
    }

    private function link(string $url, bool $isExternal): QuickLinkInterface
    {
        $link = $this->createMock(QuickLinkInterface::class);
        $link->method('getUrl')->willReturn($url);
        $link->method('isExternal')->willReturn($isExternal);

        return $link;
    }
}
