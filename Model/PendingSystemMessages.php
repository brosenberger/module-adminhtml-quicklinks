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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Whether core's system messages box will render on this page.
 *
 * The box is drawn by knockout after first paint, into the 5rem core reserves above the
 * header. The chips sit in that space, so when a box is coming the space has to be kept
 * free for it, or the page jumps. Core's messages component writes the messages it shows
 * to `admin_system_messages` while rendering; read after it (see default.xml), the table
 * is exact for the current request. Reading it avoids loading core's Synchronized
 * collection a second time, which writes to the database.
 */
class PendingSystemMessages
{
    private const MODULE = 'Magento_AdminNotification';
    private const ACL_RESOURCE = 'Magento_AdminNotification::show_list';
    private const TABLE = 'admin_system_messages';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    public function __construct(
        ResourceConnection $resource,
        ModuleManager $moduleManager,
        AuthorizationInterface $authorization
    ) {
        $this->resource = $resource;
        $this->moduleManager = $moduleManager;
        $this->authorization = $authorization;
    }

    public function exist(): bool
    {
        if (!$this->moduleManager->isEnabled(self::MODULE) || !$this->authorization->isAllowed(self::ACL_RESOURCE)) {
            return false;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['identity'])
            ->limit(1);

        return $connection->fetchOne($select) !== false;
    }
}
