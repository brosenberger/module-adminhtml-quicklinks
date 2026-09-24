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

namespace BroCode\AdminhtmlQuickLinks\Controller\Adminhtml\Link;

use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Controller\Adminhtml\AbstractJsonAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Reorder extends AbstractJsonAction
{
    /**
     * @var QuickLinkRepositoryInterface
     */
    private $repository;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        QuickLinkRepositoryInterface $repository
    ) {
        parent::__construct($context, $jsonFactory);
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    protected function handle(int $userId): array
    {
        $ids = $this->getRequest()->getParam('ids');
        $this->repository->reorder($userId, array_map('intval', is_array($ids) ? $ids : []));

        return [];
    }
}
