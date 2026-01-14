<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

use Doctrine\ORM;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * This class allows to paginate easily the results of a Doctrine Query.
 *
 * @phpstan-type PaginationOptions array{
 *     page: int,
 *     maxResults: int,
 * }
 *
 * @template T
 */
class Pagination
{
    /**
     * The paginated items (i.e. actually loaded)
     *
     * @var array<T>
     */
    public readonly array $items;

    /** The number of loaded items */
    public readonly int $count;

    /** The total number of items in the database */
    public readonly int $countAll;

    /** The number of items per page */
    public readonly int $countPerPage;

    /** The number of pages in the pagination */
    public readonly int $countPages;

    /** The number of the current page (bouded between 1 and $countPages) */
    public readonly int $currentPage;

    /**
     * Paginate the results of a Doctrine Query.
     *
     * @param PaginationOptions $paginationOptions
     *
     * @param ORM\Query<null, T> $query
     * @return Pagination<T>
     */
    public static function paginate(ORM\Query $query, array $paginationOptions): self
    {
        // We need to count all the entities matching the query. Doctrine provides
        // the Paginator class that allows to do it easily.
        $doctrinePaginator = new Paginator($query);
        $countAll = count($doctrinePaginator);

        $currentPage = $paginationOptions['page'];
        $countPerPage = $paginationOptions['maxResults'];

        $maxPage = intval(ceil($countAll / $countPerPage));
        if ($currentPage > $maxPage) {
            $currentPage = $maxPage;
        }

        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $firstResult = $countPerPage * ($currentPage - 1);

        $query->setFirstResult($firstResult);
        $query->setMaxResults($countPerPage);

        $items = $query->getResult();

        return new self($items, $countAll, $countPerPage, $currentPage);
    }

    /**
     * Return an empty pagination, for placeholder purpose.
     *
     * @return Pagination<T>
     */
    public static function empty(): self
    {
        return new self([], 0, 1, 0);
    }

    /**
     * Build a Pagination.
     *
     * You should not call the constructor directly. It is public only to
     * facilitate the tests.
     *
     * @param array<T> $items
     */
    public function __construct(
        array $items,
        int $countAll,
        int $countPerPage,
        int $currentPage,
    ) {
        $this->items = $items;
        $this->count = count($items);
        $this->countAll = $countAll;
        $this->countPerPage = $countPerPage;
        $this->countPages = intval(ceil($this->countAll / $this->countPerPage));
        $this->currentPage = $currentPage;
    }

    /**
     * Return whether we should display the pagination component or not.
     */
    public function mustPaginate(): bool
    {
        return $this->countPages >= 2;
    }

    /**
     * Return whether the current page is the first page or not.
     */
    public function currentPageIsFirst(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Return whether the current page is the last page or not.
     */
    public function currentPageIsLast(): bool
    {
        return $this->currentPage === $this->countPages;
    }

    /**
     * Return whether the specified page is the current page or not.
     */
    public function isCurrentPage(int $page): bool
    {
        return $this->currentPage === $page;
    }

    /**
     * Return the page number before the current page.
     *
     * If the current page is the first page, it returns the current page
     * number.
     */
    public function getPreviousPage(): int
    {
        if ($this->currentPageIsFirst()) {
            return $this->currentPage;
        } else {
            return $this->currentPage - 1;
        }
    }

    /**
     * Return the page number after the current page.
     *
     * If the current page is the last page, it returns the current page
     * number.
     */
    public function getNextPage(): int
    {
        if ($this->currentPageIsLast()) {
            return $this->currentPage;
        } else {
            return $this->currentPage + 1;
        }
    }

    /**
     * Return the list of the pages numbers to paginate.
     *
     * This method tries to always return:
     *
     * - the 2 first pages numbers (i.e. the pages 1 and 2)
     * - five pages numbers around the current page (e.g. if the current page
     *   is 5, it will get the pages 3, 4, 5, 6 and 7).
     * - and the 2 last pages numbers
     *
     * Obviously, it limits the pages numbers to the existing pages. For
     * instance, if there are only 3 pages, it will return the pages 1, 2 and
     * 3.
     *
     * If the current page is at the beginning or at the end of the pages, the
     * range will move to the right or the left. For instance, if the current
     * page is 1, the returned pages will be 1, 2, 3, 4 and 5. If it's at the
     * end of 10 pages, the returned pages will be 6, 7, 8, 9 and 10.
     *
     * If there is a gap between two numbers, the `ellipsis` string is inserted
     * between the numbers.
     *
     * For instance, if the pagination contains 15 pages, and that the current
     * page is at page 7, the result of this method will be:
     *
     * [1, 2, 'ellipsis', 5, 6, 7, 8, 9, 'ellipsis', 14, 15]
     *
     * If the current page is at page 1:
     *
     * [1, 2, 3, 4, 5, 'ellipsis', 14, 15]
     *
     * If the current page is at page 14:
     *
     * [1, 2, 'ellipsis', 11, 12, 13, 14, 15]
     *
     * @return array<int|'ellipsis'>
     */
    public function getPagesNumbers(): array
    {
        if (!$this->mustPaginate()) {
            return [];
        }

        // We paginate only if there are at least 2 pages (ensured by the
        // `mustPaginate()` method.

        // We get the three ranges (i.e. 2 first pages, the pages around the
        // current page, and the 2 last pages).
        $startRange = [1, 2];
        $currentRange = $this->getCurrentPageRange();
        $endRange = [$this->countPages - 1, $this->countPages];

        // We merge the 3 ranges
        $pagesRange = array_merge($startRange, $currentRange, $endRange);
        // Make sure to have unique values
        $pagesRange = array_unique($pagesRange);
        // And reset the keys so we can use them while iterating on the pages
        $pagesRange = array_values($pagesRange);

        $pagesNumbers = [];

        foreach ($pagesRange as $key => $pageNumber) {
            $previousNumber = $pagesRange[$key - 1] ?? 0;

            if ($pageNumber > $previousNumber + 1) {
                // Insert an `ellipsis` string in the array if there is a gap
                // between the current page number and the previous one.
                $pagesNumbers[] = 'ellipsis';
            }

            $pagesNumbers[] = $pageNumber;
        }

        return $pagesNumbers;
    }

    /**
     * Return a range of 5 pages numbers around the current page.
     *
     * @return int[]
     */
    private function getCurrentPageRange(): array
    {
        // Get the "default" start and end numbers (i.e. 2 before and 2 after the
        // current page).
        $startOffset = $this->currentPage - 2;
        $endOffset = $this->currentPage + 2;

        // But the range may not start at these exact numbers: we need to bound
        // them in the limit of the existing pages (i.e. between 1 and
        // $countPages).
        $rangeStart = max(1, $startOffset);
        $rangeEnd = min($this->countPages, $endOffset);

        // We may have to move the range slightly on the right or on the left,
        // depending on if the current page is at the beginning or at the end
        // of the pages. E.g. if the current page number is 1, startOffset will
        // be -1, and the range will start at 1; we want to move the range by 2
        // (i.e. 1 - (-1)) on the right (i.e. adding 2 to the range end). The
        // same applies if the current page is nearly at the end of the pages
        // (i.e. we need to move the range on the left, meaning substracting a
        // certain number to the range start).
        $endPositiveOffset = $rangeStart - $startOffset;
        $startNegativeOffset = $endOffset - $rangeEnd;

        // We apply the offsets on the start and end numbers of the range, BUT
        // we make sure they are still bounded in the limit of the existing
        // pages.
        $rangeStart = max(1, $rangeStart - $startNegativeOffset);
        $rangeEnd = min($this->countPages, $rangeEnd + $endPositiveOffset);

        // Finally, we return the range, congratulations!
        return range($rangeStart, $rangeEnd);
    }
}
