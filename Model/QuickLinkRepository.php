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

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;
use BroCode\AdminhtmlQuickLinks\Api\QuickLinkRepositoryInterface;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink as QuickLinkResource;
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class QuickLinkRepository implements QuickLinkRepositoryInterface
{
    public const MAX_LABEL_LENGTH = 255;

    /**
     * @var QuickLinkResource
     */
    private $resource;

    /**
     * @var QuickLinkFactory
     */
    private $linkFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        QuickLinkResource $resource,
        QuickLinkFactory $linkFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->resource = $resource;
        $this->linkFactory = $linkFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(QuickLinkInterface $link): QuickLinkInterface
    {
        $this->validate($link);

        if ($link->getLinkId() === null) {
            $link->setSortOrder($this->nextSortOrder($link->getUserId()));
        }

        try {
            $this->resource->save($this->toModel($link));
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the quick link.'), $e);
        }

        return $link;
    }

    /**
     * @inheritDoc
     */
    public function getByIdForUser(int $linkId, int $userId): QuickLinkInterface
    {
        $link = $this->linkFactory->create();
        $this->resource->load($link, $linkId);

        if ($link->getLinkId() === null || $link->getUserId() !== $userId) {
            throw new NoSuchEntityException(__('The quick link does not exist.'));
        }

        return $link;
    }

    /**
     * @inheritDoc
     */
    public function getListForUser(int $userId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(QuickLinkInterface::USER_ID, ['eq' => $userId])
            ->setOrder(QuickLinkInterface::SORT_ORDER, 'ASC')
            ->setOrder(QuickLinkInterface::LINK_ID, 'ASC');
        /** @var QuickLinkInterface[] $links */
        $links = array_values($collection->getItems());

        return $links;
    }

    /**
     * @inheritDoc
     */
    public function delete(QuickLinkInterface $link): void
    {
        try {
            $this->resource->delete($this->toModel($link));
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the quick link.'), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function reorder(int $userId, array $linkIds): void
    {
        $remaining = [];
        foreach ($this->getListForUser($userId) as $link) {
            $remaining[$link->getLinkId()] = $link;
        }

        $ordered = [];
        foreach ($linkIds as $linkId) {
            if (isset($remaining[(int)$linkId])) {
                $ordered[] = $remaining[(int)$linkId];
                unset($remaining[(int)$linkId]);
            }
        }

        foreach (array_merge($ordered, array_values($remaining)) as $position => $link) {
            if ($link->getSortOrder() !== $position) {
                $link->setSortOrder($position);
                $this->save($link);
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    private function validate(QuickLinkInterface $link): void
    {
        $label = trim($link->getLabel());

        if ($label === '' || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new LocalizedException(
                __('The label must be between 1 and %1 characters.', self::MAX_LABEL_LENGTH)
            );
        }

        if ($link->getUrl() === '' || strlen($link->getUrl()) > LinkUrl::MAX_LENGTH) {
            throw new LocalizedException(__('The quick link needs a URL.'));
        }

        if ($link->getUserId() <= 0) {
            throw new LocalizedException(__('The quick link must belong to an admin user.'));
        }

        $link->setLabel($label);
    }

    /**
     * @throws LocalizedException
     */
    private function toModel(QuickLinkInterface $link): QuickLink
    {
        if (!$link instanceof QuickLink) {
            throw new LocalizedException(__('Unsupported quick link implementation %1.', get_class($link)));
        }

        return $link;
    }

    private function nextSortOrder(int $userId): int
    {
        $max = -1;
        foreach ($this->getListForUser($userId) as $link) {
            $max = max($max, $link->getSortOrder());
        }

        return $max + 1;
    }
}
