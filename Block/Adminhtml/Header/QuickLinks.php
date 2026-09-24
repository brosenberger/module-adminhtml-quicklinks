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

namespace BroCode\AdminhtmlQuickLinks\Block\Adminhtml\Header;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Model\LinkUrl;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The quick links in the admin header, rendered as two blocks of this class:
 *
 * - `header/star.phtml` sits with the other header actions (search, notifications, user):
 *   the star toggle for the current page and the dropdown of unpinned links.
 * - `header/bar.phtml` is its own full-width row below them, holding the pinned links.
 *   It must come after every floated header action, or it pushes them down (see default.xml).
 */
class QuickLinks extends Template
{
    /**
     * @var string
     */
    protected $_template = 'BroCode_AdminhtmlQuickLinks::header/star.phtml';

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

    /**
     * @var QuickLinkInterface[]|null
     */
    private $links;

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
    public function getPinnedLinks(): array
    {
        return array_values(array_filter($this->getLinks(), static function (QuickLinkInterface $link): bool {
            return $link->isPinned();
        }));
    }

    /**
     * @return QuickLinkInterface[]
     */
    public function getOverflowLinks(): array
    {
        return array_values(array_filter($this->getLinks(), static function (QuickLinkInterface $link): bool {
            return !$link->isPinned();
        }));
    }

    public function getHref(QuickLinkInterface $link): string
    {
        return $this->linkUrl->toHref($link);
    }

    /**
     * Id of the link that points at the page being rendered, for the filled star.
     */
    public function getCurrentLinkId(): ?int
    {
        try {
            $current = $this->linkUrl->normalize($this->_urlBuilder->getCurrentUrl());
        } catch (LocalizedException $e) {
            return null;
        }

        foreach ($this->getLinks() as $link) {
            if (!$link->isExternal() && $link->getUrl() === $current['url']) {
                return $link->getLinkId();
            }
        }

        return null;
    }

    public function getManageUrl(): string
    {
        return $this->getUrl('brocode_quicklinks/link/index');
    }

    public function getWidgetConfigJson(): string
    {
        return (string)$this->json->serialize([
            'saveUrl' => $this->getUrl('brocode_quicklinks/link/save'),
            'deleteUrl' => $this->getUrl('brocode_quicklinks/link/delete'),
            'currentLinkId' => $this->getCurrentLinkId(),
        ]);
    }

    /**
     * @return QuickLinkInterface[]
     */
    private function getLinks(): array
    {
        if ($this->links === null) {
            $user = $this->authSession->getUser();
            $this->links = $user ? $this->repository->getListForUser((int)$user->getId()) : [];
        }

        return $this->links;
    }
}
