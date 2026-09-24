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
use BroCode\AdminhtmlQuickLinks\Model\ResourceModel\QuickLink as QuickLinkResource;
use Magento\Framework\Model\AbstractModel;

class QuickLink extends AbstractModel implements QuickLinkInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(QuickLinkResource::class);
    }

    public function getLinkId(): ?int
    {
        $id = $this->getData(self::LINK_ID);

        return $id === null ? null : (int)$id;
    }

    public function getUserId(): int
    {
        return (int)$this->getData(self::USER_ID);
    }

    public function setUserId(int $userId): QuickLinkInterface
    {
        return $this->setData(self::USER_ID, $userId);
    }

    public function getLabel(): string
    {
        return (string)$this->getData(self::LABEL);
    }

    public function setLabel(string $label): QuickLinkInterface
    {
        return $this->setData(self::LABEL, $label);
    }

    public function getUrl(): string
    {
        return (string)$this->getData(self::URL);
    }

    public function setUrl(string $url): QuickLinkInterface
    {
        return $this->setData(self::URL, $url);
    }

    public function isExternal(): bool
    {
        return (bool)$this->getData(self::IS_EXTERNAL);
    }

    public function setIsExternal(bool $isExternal): QuickLinkInterface
    {
        return $this->setData(self::IS_EXTERNAL, (int)$isExternal);
    }

    public function isPinned(): bool
    {
        return (bool)$this->getData(self::IS_PINNED);
    }

    public function setIsPinned(bool $isPinned): QuickLinkInterface
    {
        return $this->setData(self::IS_PINNED, (int)$isPinned);
    }

    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): QuickLinkInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function getCreatedAt(): ?string
    {
        $createdAt = $this->getData(self::CREATED_AT);

        return $createdAt === null ? null : (string)$createdAt;
    }
}
