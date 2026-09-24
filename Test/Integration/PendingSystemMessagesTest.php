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

use BroCode\AdminhtmlQuickLinks\Model\PendingSystemMessages;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\TestFramework\Helper\Bootstrap as BootstrapHelper;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class PendingSystemMessagesTest extends TestCase
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    protected function setUp(): void
    {
        $this->resource = BootstrapHelper::getObjectManager()->get(ResourceConnection::class);
        $this->connection()->delete($this->table());
    }

    public function testNoPersistedMessagesMeansNothingWillLoad(): void
    {
        self::assertFalse($this->create(true)->exist());
    }

    public function testPersistedMessageMeansTheMessagesBoxWillRender(): void
    {
        $this->connection()->insert($this->table(), ['identity' => 'brocode_test', 'severity' => 2]);

        self::assertTrue($this->create(true)->exist());
    }

    public function testUserWithoutTheMessagesAclGetsNoReserve(): void
    {
        $this->connection()->insert($this->table(), ['identity' => 'brocode_test', 'severity' => 2]);

        self::assertFalse($this->create(false)->exist());
    }

    private function create(bool $allowed): PendingSystemMessages
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with('Magento_AdminNotification::show_list')->willReturn($allowed);

        return BootstrapHelper::getObjectManager()->create(
            PendingSystemMessages::class,
            ['authorization' => $authorization]
        );
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName('admin_system_messages');
    }
}
