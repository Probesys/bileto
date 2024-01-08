<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\Pagination;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaginationTest extends WebTestCase
{
    public function testGetPagesNumbersWithNoPage(): void
    {
        $pagination = new Pagination([], countAll: 0, countPerPage: 10, currentPage: 1);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(0, count($pages));
    }

    public function testGetPagesNumbersWith1Page(): void
    {
        $pagination = new Pagination([], countAll: 10, countPerPage: 10, currentPage: 1);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(0, count($pages));
    }

    public function testGetPagesNumbersWith2Pages(): void
    {
        $pagination = new Pagination([], countAll: 15, countPerPage: 10, currentPage: 1);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(2, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
    }

    public function testGetPagesNumbersWith5Pages(): void
    {
        $pagination = new Pagination([], countAll: 45, countPerPage: 10, currentPage: 1);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(5, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
        $this->assertEquals(3, $pages[2]);
        $this->assertEquals(4, $pages[3]);
        $this->assertEquals(5, $pages[4]);
    }

    public function testGetPagesNumbersWith8PagesAndCurrentPage1(): void
    {
        $pagination = new Pagination([], countAll: 80, countPerPage: 10, currentPage: 1);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(8, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
        $this->assertEquals(3, $pages[2]);
        $this->assertEquals(4, $pages[3]);
        $this->assertEquals(5, $pages[4]);
        $this->assertEquals('ellipsis', $pages[5]);
        $this->assertEquals(7, $pages[6]);
        $this->assertEquals(8, $pages[7]);
    }

    public function testGetPagesNumbersWith8PagesAndCurrentPage4(): void
    {
        $pagination = new Pagination([], countAll: 80, countPerPage: 10, currentPage: 4);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(8, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
        $this->assertEquals(3, $pages[2]);
        $this->assertEquals(4, $pages[3]);
        $this->assertEquals(5, $pages[4]);
        $this->assertEquals(6, $pages[5]);
        $this->assertEquals(7, $pages[6]);
        $this->assertEquals(8, $pages[7]);
    }

    public function testGetPagesNumbersWith8PagesAndCurrentPage8(): void
    {
        $pagination = new Pagination([], countAll: 80, countPerPage: 10, currentPage: 8);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(8, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
        $this->assertEquals('ellipsis', $pages[2]);
        $this->assertEquals(4, $pages[3]);
        $this->assertEquals(5, $pages[4]);
        $this->assertEquals(6, $pages[5]);
        $this->assertEquals(7, $pages[6]);
        $this->assertEquals(8, $pages[7]);
    }

    public function testGetPagesNumbersWith11PagesAndCurrentPage6(): void
    {
        $pagination = new Pagination([], countAll: 110, countPerPage: 10, currentPage: 6);

        $pages = $pagination->getPagesNumbers();

        $this->assertSame(11, count($pages));
        $this->assertEquals(1, $pages[0]);
        $this->assertEquals(2, $pages[1]);
        $this->assertEquals('ellipsis', $pages[2]);
        $this->assertEquals(4, $pages[3]);
        $this->assertEquals(5, $pages[4]);
        $this->assertEquals(6, $pages[5]);
        $this->assertEquals(7, $pages[6]);
        $this->assertEquals(8, $pages[7]);
        $this->assertEquals('ellipsis', $pages[8]);
        $this->assertEquals(10, $pages[9]);
        $this->assertEquals(11, $pages[10]);
    }
}
