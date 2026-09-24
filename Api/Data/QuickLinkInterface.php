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

namespace BroCode\AdminhtmlQuickLinks\Api\Data;

/**
 * One quick link of one admin user.
 *
 * An internal link stores the admin route path without base URL, admin frontName or
 * secret key (e.g. `sales/order/view/order_id/5`), because the secret key changes with
 * every session. An external link stores its absolute http(s) URL.
 */
interface QuickLinkInterface
{
    public const LINK_ID = 'link_id';
    public const USER_ID = 'user_id';
    public const LABEL = 'label';
    public const URL = 'url';
    public const IS_EXTERNAL = 'is_external';
    public const IS_PINNED = 'is_pinned';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getLinkId(): ?int;

    /**
     * @return int
     */
    public function getUserId(): int;

    /**
     * @param int $userId
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setUserId(int $userId): QuickLinkInterface;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setLabel(string $label): QuickLinkInterface;

    /**
     * @return string
     */
    public function getUrl(): string;

    /**
     * @param string $url
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setUrl(string $url): QuickLinkInterface;

    /**
     * @return bool
     */
    public function isExternal(): bool;

    /**
     * @param bool $isExternal
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setIsExternal(bool $isExternal): QuickLinkInterface;

    /**
     * Pinned links show as chips in the admin header, the others in the star dropdown.
     *
     * @return bool
     */
    public function isPinned(): bool;

    /**
     * @param bool $isPinned
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setIsPinned(bool $isPinned): QuickLinkInterface;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     */
    public function setSortOrder(int $sortOrder): QuickLinkInterface;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
