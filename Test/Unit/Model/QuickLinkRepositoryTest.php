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

use BroCode\AdminhtmlQuickLinks\Model\QuickLink;
use BroCode\AdminhtmlQuickLinks\Model\QuickLinkFactory;
use BroCode\AdminhtmlQuickLinks\Model\QuickLinkRepository;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink as QuickLinkResource;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink\Collection;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuickLinkRepositoryTest extends TestCase
{
    private const ROW = ['label' => 'Orders', 'url' => 'sales/order/index'];

    /**
     * @var QuickLinkResource&MockObject
     */
    private $resource;

    /**
     * @var Collection&MockObject
     */
    private $collection;

    /**
     * @var QuickLinkRepository
     */
    private $repository;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->resource = $this->createMock(QuickLinkResource::class);
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->collection->method('setOrder')->willReturnSelf();

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);

        $linkFactory = $this->createMock(QuickLinkFactory::class);
        $linkFactory->method('create')->willReturnCallback(function () {
            return $this->newLink([]);
        });

        $this->repository = new QuickLinkRepository($this->resource, $linkFactory, $collectionFactory);
    }

    public function testGetByIdForUserReturnsOwnLink(): void
    {
        $this->stubLoad(['link_id' => 7, 'user_id' => 3, 'label' => 'Orders']);

        self::assertSame('Orders', $this->repository->getByIdForUser(7, 3)->getLabel());
    }

    public function testGetByIdForUserRefusesAnotherUsersLink(): void
    {
        $this->stubLoad(['link_id' => 7, 'user_id' => 3]);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getByIdForUser(7, 4);
    }

    public function testGetByIdForUserRefusesMissingLink(): void
    {
        $this->stubLoad([]);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getByIdForUser(99, 3);
    }

    public function testReorderWritesGivenOrderIgnoresForeignIdsAndKeepsTheRestAfter(): void
    {
        $links = [
            $this->newLink(['link_id' => 1, 'user_id' => 3, 'sort_order' => 0] + self::ROW),
            $this->newLink(['link_id' => 2, 'user_id' => 3, 'sort_order' => 1] + self::ROW),
            $this->newLink(['link_id' => 3, 'user_id' => 3, 'sort_order' => 2] + self::ROW),
        ];
        $this->collection->method('getItems')->willReturn($links);

        $saved = [];
        $this->resource->method('save')->willReturnCallback(function (QuickLink $link) use (&$saved) {
            $saved[$link->getLinkId()] = $link->getSortOrder();
            return $this->resource;
        });

        // 42 belongs to someone else, 2 is left out.
        $this->repository->reorder(3, [3, 42, 1]);

        self::assertSame([3 => 0, 1 => 1, 2 => 2], $saved);
    }

    public function testSaveAppendsNewLinkToTheEndOfTheUsersList(): void
    {
        $this->collection->method('getItems')->willReturn([
            $this->newLink(['link_id' => 1, 'user_id' => 3, 'sort_order' => 4]),
        ]);
        $link = $this->newLink(['user_id' => 3, 'label' => 'Orders', 'url' => 'sales/order/index']);

        $this->repository->save($link);

        self::assertSame(5, $link->getSortOrder());
    }

    /**
     * @dataProvider invalidLinkProvider
     */
    public function testSaveRejectsInvalidLink(array $data): void
    {
        $this->resource->expects(self::never())->method('save');
        $this->expectException(LocalizedException::class);

        $this->repository->save($this->newLink($data + ['link_id' => 1, 'user_id' => 3]));
    }

    public static function invalidLinkProvider(): array
    {
        return [
            'empty label' => [['label' => '  ', 'url' => 'sales/order/index']],
            'label too long' => [['label' => str_repeat('x', 256), 'url' => 'sales/order/index']],
            'empty url' => [['label' => 'Orders', 'url' => '']],
            'no user' => [['label' => 'Orders', 'url' => 'sales/order/index', 'user_id' => 0]],
        ];
    }

    private function stubLoad(array $data): void
    {
        $this->resource->method('load')->willReturnCallback(function (QuickLink $link) use ($data) {
            $link->setData($data);
            return $this->resource;
        });
    }

    private function newLink(array $data): QuickLink
    {
        /** @var QuickLink $link */
        $link = $this->objectManager->getObject(QuickLink::class);
        $link->setData($data);

        return $link;
    }
}
