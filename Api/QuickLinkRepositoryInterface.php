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

namespace BroCode\AdminhtmlQuickLinks\Api;

use BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface;

/**
 * Every read is scoped to one admin user: a link id alone never grants access.
 */
interface QuickLinkRepositoryInterface
{
    /**
     * A new link always goes to the end of its user's list.
     *
     * @param \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface $link
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(QuickLinkInterface $link): QuickLinkInterface;

    /**
     * @param int $linkId
     * @param int $userId
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException when missing or owned by another user
     */
    public function getByIdForUser(int $linkId, int $userId): QuickLinkInterface;

    /**
     * @param int $userId
     * @return \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface[] in display order
     */
    public function getListForUser(int $userId): array;

    /**
     * @param \BroCode\AdminhtmlQuickLinks\Api\Data\QuickLinkInterface $link
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(QuickLinkInterface $link): void;

    /**
     * Ids not owned by the user are ignored; the user's links missing from the list keep
     * their relative order after the listed ones.
     *
     * @param int $userId
     * @param int[] $linkIds
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reorder(int $userId, array $linkIds): void;
}
