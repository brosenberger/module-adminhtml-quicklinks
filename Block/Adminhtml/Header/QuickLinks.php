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
use BroCode\AdminhtmlQuickLinks\Model\PendingSystemMessages;
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
 * - `header/bar.phtml` holds the pinned links, shown first in the notices area above
 *   the header, where core reserves room for system messages (see default.xml).
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
     * @var PendingSystemMessages
     */
    private $pendingSystemMessages;

    /**
     * @var QuickLinkInterface[]|null
     */
    private $links;

    /**
     * @var array<int, array{id: int, state: string|null}>|null
     */
    private $pageLinks;

    public function __construct(
        Context $context,
        QuickLinkRepositoryInterface $repository,
        LinkUrl $linkUrl,
        AuthSession $authSession,
        Json $json,
        PendingSystemMessages $pendingSystemMessages,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->repository = $repository;
        $this->linkUrl = $linkUrl;
        $this->authSession = $authSession;
        $this->json = $json;
        $this->pendingSystemMessages = $pendingSystemMessages;
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

    /**
     * Whether core's system messages box is about to render below the chips, so its space
     * must stay reserved. Only meaningful in the bar, which renders after that box.
     */
    public function isSystemMessageComing(): bool
    {
        return $this->pendingSystemMessages->exist();
    }

    public function getHref(QuickLinkInterface $link): string
    {
        return $this->linkUrl->toHref($link);
    }

    /**
     * The links pointing at the page being rendered, each with its saved grid state.
     *
     * Grid filters live in UI bookmarks, not in the URL, so the server cannot tell which of
     * several links to the same grid matches what is on screen; the star widget compares
     * the states with the grid's live filters (see js/grid-state.js).
     *
     * @return array<int, array{id: int, state: string|null}>
     */
    public function getPageLinks(): array
    {
        if ($this->pageLinks !== null) {
            return $this->pageLinks;
        }

        $this->pageLinks = [];
        try {
            $current = $this->linkUrl->normalize($this->_urlBuilder->getCurrentUrl());
        } catch (LocalizedException $e) {
            return $this->pageLinks;
        }

        [$currentBase] = $this->linkUrl->splitGridState($current['url']);
        foreach ($this->getLinks() as $link) {
            [$base, $state] = $this->linkUrl->splitGridState($link->getUrl());
            if (!$link->isExternal() && $base === $currentBase) {
                $this->pageLinks[] = ['id' => (int)$link->getLinkId(), 'state' => $state];
            }
        }

        return $this->pageLinks;
    }

    /**
     * Server-side guess for the star before the grid has loaded: a link without saved
     * filters. The widget corrects it once the grid's filters are known (a link without
     * any state does not count on a grid page there).
     */
    public function isCurrentPageLinked(): bool
    {
        foreach ($this->getPageLinks() as $pageLink) {
            if ($this->linkUrl->isUnfilteredGridState($pageLink['state'])) {
                return true;
            }
        }

        return false;
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
            'reorderUrl' => $this->getUrl('brocode_quicklinks/link/reorder'),
            'pageLinks' => $this->getPageLinks(),
            'order' => array_map(static function (QuickLinkInterface $link): int {
                return (int)$link->getLinkId();
            }, $this->getLinks()),
            'stateParam' => LinkUrl::GRID_STATE_PARAM,
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
