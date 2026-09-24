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

namespace BroCode\AdminhtmlQuickLinks\Block\Adminhtml;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Model\LinkUrl;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * System > Tools > Quick Links: reorder, rename, pin, delete and add links.
 */
class Manage extends Template
{
    /**
     * @var string
     */
    protected $_template = 'BroCode_AdminhtmlQuickLinks::manage.phtml';

    /**
     * @var QuickLinkRepositoryInterface
     */
    private $repository;

    /**
     * @var LinkUrl
     */
    private $linkUrl;

    /**
     * @var AuthSession
     */
    private $authSession;

    /**
     * @var Json
     */
    private $json;

    public function __construct(
        Context $context,
        QuickLinkRepositoryInterface $repository,
        LinkUrl $linkUrl,
        AuthSession $authSession,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->repository = $repository;
        $this->linkUrl = $linkUrl;
        $this->authSession = $authSession;
        $this->json = $json;
    }

    /**
     * @return QuickLinkInterface[]
     */
    public function getLinks(): array
    {
        $user = $this->authSession->getUser();

        return $user ? $this->repository->getListForUser((int)$user->getId()) : [];
    }

    public function getHref(QuickLinkInterface $link): string
    {
        return $this->linkUrl->toHref($link);
    }

    public function getWidgetConfigJson(): string
    {
        return (string)$this->json->serialize([
            'saveUrl' => $this->getUrl('brocode_quicklinks/link/save'),
            'deleteUrl' => $this->getUrl('brocode_quicklinks/link/delete'),
            'reorderUrl' => $this->getUrl('brocode_quicklinks/link/reorder'),
        ]);
    }
}
