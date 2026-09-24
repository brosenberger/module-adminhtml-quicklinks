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

namespace BroCode\AdminhtmlQuickLinks\Test\Integration;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterfaceFactory;
use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink as QuickLinkResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\Helper\Bootstrap as BootstrapHelper;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class QuickLinkRepositoryTest extends TestCase
{
    /**
     * @var QuickLinkRepositoryInterface
     */
    private $repository;

    /**
     * @var QuickLinkInterfaceFactory
     */
    private $linkFactory;

    protected function setUp(): void
    {
        $objectManager = BootstrapHelper::getObjectManager();
        $this->repository = $objectManager->get(QuickLinkRepositoryInterface::class);
        $this->linkFactory = $objectManager->get(QuickLinkInterfaceFactory::class);
    }

    public function testLinksAreScopedToTheirUser(): void
    {
        $owner = $this->loadUser(Bootstrap::ADMIN_NAME);
        $other = $this->createUser('quicklinks_other');
        $link = $this->addLink((int)$owner->getId(), 'Orders', 'sales/order/index');

        self::assertCount(1, $this->repository->getListForUser((int)$owner->getId()));
        self::assertSame([], $this->repository->getListForUser((int)$other->getId()));

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByIdForUser((int)$link->getLinkId(), (int)$other->getId());
    }

    public function testNewLinksAppendAndReorderIsStored(): void
    {
        $userId = (int)$this->createUser('quicklinks_order')->getId();
        $first = $this->addLink($userId, 'First', 'sales/order/index');
        $second = $this->addLink($userId, 'Second', 'catalog/product/index');
        $third = $this->addLink($userId, 'Third', 'https://example.test/', true);

        self::assertSame([0, 1, 2], $this->sortOrdersFromDb($userId, [$first, $second, $third]));

        $this->repository->reorder($userId, [(int)$third->getLinkId(), (int)$first->getLinkId()]);

        self::assertSame(['Third', 'First', 'Second'], array_map(static function (QuickLinkInterface $link) {
            return $link->getLabel();
        }, $this->repository->getListForUser($userId)));
    }

    public function testDeletingTheAdminUserDeletesTheirLinks(): void
    {
        $user = $this->createUser('quicklinks_cascade');
        $userId = (int)$user->getId();
        $this->addLink($userId, 'Orders', 'sales/order/index');
        self::assertSame(1, $this->countRowsInDb($userId));

        // Straight at the table: this pins the foreign key, not the User model's delete hooks.
        $connection = $this->connection();
        $connection->delete($connection->getTableName('admin_user'), ['user_id = ?' => $userId]);

        self::assertSame(0, $this->countRowsInDb($userId));
    }

    private function addLink(int $userId, string $label, string $url, bool $isExternal = false): QuickLinkInterface
    {
        $link = $this->linkFactory->create()
            ->setUserId($userId)
            ->setLabel($label)
            ->setUrl($url)
            ->setIsExternal($isExternal)
            ->setIsPinned(true);

        return $this->repository->save($link);
    }

    private function loadUser(string $username): User
    {
        $user = BootstrapHelper::getObjectManager()->get(UserFactory::class)->create();
        $user->loadByUsername($username);

        return $user;
    }

    private function createUser(string $username): User
    {
        $user = BootstrapHelper::getObjectManager()->get(UserFactory::class)->create();
        $user->setData([
            'username' => $username,
            'firstname' => 'Quick',
            'lastname' => 'Links',
            'email' => $username . '@example.test',
            'password' => 'Quicklinks123!',
            'is_active' => 1,
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param QuickLinkInterface[] $links
     * @return int[]
     */
    private function sortOrdersFromDb(int $userId, array $links): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($connection->getTableName(QuickLinkResource::TABLE_NAME), ['link_id', 'sort_order'])
            ->where('user_id = ?', $userId);
        $rows = $connection->fetchPairs($select);

        return array_map(static function (QuickLinkInterface $link) use ($rows) {
            return (int)$rows[$link->getLinkId()];
        }, $links);
    }

    private function countRowsInDb(int $userId): int
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($connection->getTableName(QuickLinkResource::TABLE_NAME), ['COUNT(*)'])
            ->where('user_id = ?', $userId);

        return (int)$connection->fetchOne($select);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return BootstrapHelper::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }
}
