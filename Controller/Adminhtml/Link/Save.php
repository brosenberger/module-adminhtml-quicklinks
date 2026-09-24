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

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterfaceFactory;
use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Controller\Adminhtml\AbstractJsonAction;
use BroCode\AdminhtmlQuickLinks\Model\LinkUrl;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Creates a link (no `id`) or updates one (`id`). Only the fields sent are changed.
 *
 * The URL of an internal link is fixed once saved: it came from a real admin page, and
 * letting it be edited would turn it into an arbitrary path. A `url` sent for one is ignored.
 */
class Save extends AbstractJsonAction
{
    /**
     * @var QuickLinkRepositoryInterface
     */
    private $repository;

    /**
     * @var QuickLinkInterfaceFactory
     */
    private $linkFactory;

    /**
     * @var LinkUrl
     */
    private $linkUrl;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        QuickLinkRepositoryInterface $repository,
        QuickLinkInterfaceFactory $linkFactory,
        LinkUrl $linkUrl
    ) {
        parent::__construct($context, $jsonFactory);
        $this->repository = $repository;
        $this->linkFactory = $linkFactory;
        $this->linkUrl = $linkUrl;
    }

    /**
     * @inheritDoc
     */
    protected function handle(int $userId): array
    {
        $request = $this->getRequest();
        $id = (int)$request->getParam('id');
        $url = $request->getParam('url');
        $label = $request->getParam('label');
        $pinned = $request->getParam('pinned');

        $link = $id > 0 ? $this->repository->getByIdForUser($id, $userId) : $this->createLink($userId, $url);

        if ($url !== null && ($link->getLinkId() === null || $link->isExternal())) {
            $normalized = $this->linkUrl->normalize((string)$url);
            $link->setUrl($normalized['url'])->setIsExternal($normalized['is_external']);
        }

        if ($label !== null) {
            $link->setLabel((string)$label);
        } elseif ($link->getLinkId() === null) {
            $link->setLabel((string)$url);
        }

        if ($pinned !== null) {
            $link->setIsPinned((bool)(int)$pinned);
        }

        $this->repository->save($link);

        return ['link' => $this->toArray($link)];
    }

    /**
     * @param mixed $url
     * @throws LocalizedException
     */
    private function createLink(int $userId, $url): QuickLinkInterface
    {
        if (!is_string($url) || trim($url) === '') {
            throw new LocalizedException(__('Enter the URL of the quick link.'));
        }

        return $this->linkFactory->create()->setUserId($userId)->setIsPinned(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(QuickLinkInterface $link): array
    {
        return [
            'id' => $link->getLinkId(),
            'label' => $link->getLabel(),
            'url' => $link->getUrl(),
            'href' => $this->linkUrl->toHref($link),
            'is_external' => $link->isExternal(),
            'is_pinned' => $link->isPinned(),
        ];
    }
}
