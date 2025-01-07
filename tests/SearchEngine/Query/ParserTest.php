<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\Query;

use App\SearchEngine\Query\Parser;
use App\SearchEngine\Query\Tokenizer;
use App\SearchEngine\Query\SyntaxError;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testParseText(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'emails NOT "evil corp"';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(2, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isTextCondition());
        $this->assertSame('emails', $conditions[0]->getValue());

        $this->assertTrue($conditions[1]->and());
        $this->assertTrue($conditions[1]->not());
        $this->assertTrue($conditions[1]->isTextCondition());
        $this->assertSame('evil corp', $conditions[1]->getValue());
    }

    public function testParseListOfText(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'emails, evil, corp';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(1, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isTextCondition());
        $this->assertSame(['emails', 'evil', 'corp'], $conditions[0]->getValue());
    }

    public function testParseQualifier(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'status:open';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(1, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isQualifierCondition());
        $this->assertSame('status', $conditions[0]->getQualifier());
        $this->assertSame('open', $conditions[0]->getValue());
    }

    public function testParseQualifierList(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'status:open,resolved';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(1, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isQualifierCondition());
        $this->assertSame('status', $conditions[0]->getQualifier());
        $this->assertSame(['open', 'resolved'], $conditions[0]->getValue());
    }

    public function testParseListOfQualifiers(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'status:open no:assignee';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(2, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isQualifierCondition());
        $this->assertSame('status', $conditions[0]->getQualifier());
        $this->assertSame('open', $conditions[0]->getValue());

        $this->assertTrue($conditions[1]->and());
        $this->assertFalse($conditions[1]->not());
        $this->assertTrue($conditions[1]->isQualifierCondition());
        $this->assertSame('no', $conditions[1]->getQualifier());
        $this->assertSame('assignee', $conditions[1]->getValue());
    }

    public function testParseSubQuery(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'status:open NOT (assignee:@me OR requester:@me)';
        $tokens = $tokenizer->tokenize($stringQuery);

        $query = $parser->parse($tokens);

        $conditions = $query->getConditions();
        $this->assertSame(2, count($conditions));

        $this->assertTrue($conditions[0]->and());
        $this->assertFalse($conditions[0]->not());
        $this->assertTrue($conditions[0]->isQualifierCondition());
        $this->assertSame('status', $conditions[0]->getQualifier());
        $this->assertSame('open', $conditions[0]->getValue());

        $this->assertTrue($conditions[1]->and());
        $this->assertTrue($conditions[1]->not());
        $this->assertTrue($conditions[1]->isQueryCondition());

        $subQuery = $conditions[1]->getQuery();
        $subConditions = $subQuery->getConditions();
        $this->assertSame(2, count($subConditions));

        $this->assertTrue($subConditions[0]->and());
        $this->assertFalse($subConditions[0]->not());
        $this->assertTrue($subConditions[0]->isQualifierCondition());
        $this->assertSame('assignee', $subConditions[0]->getQualifier());
        $this->assertSame('@me', $subConditions[0]->getValue());

        $this->assertTrue($subConditions[1]->or());
        $this->assertFalse($subConditions[1]->not());
        $this->assertTrue($subConditions[1]->isQualifierCondition());
        $this->assertSame('requester', $subConditions[1]->getQualifier());
        $this->assertSame('@me', $subConditions[1]->getValue());
    }

    public function testParseFailsIfTheTokensAreEmpty(): void
    {
        $parser = new Parser();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The parser cannot be called with an empty list of tokens.');

        $parser->parse([]);
    }

    public function testParseFailsIfQueryDoesNotStartByAnd(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'emails';
        $tokens = $tokenizer->tokenize($stringQuery);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An AND keyword is expected char 1');

        // The Tokenizer always insert an AND token at the beginning, so we
        // make sure to remove it.
        array_shift($tokens);
        $parser->parse($tokens);
    }

    public function testParseFailsIfQueryStartsWithOr(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'OR emails';
        $tokens = $tokenizer->tokenize($stringQuery);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An AND keyword is expected char 1');

        // The Tokenizer always insert an AND token at the beginning, so we
        // make sure to remove it.
        array_shift($tokens);
        $parser->parse($tokens);
    }

    public function testParseFailsIfQueryDoesNotEndWithEndOfQuery(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'emails';
        $tokens = $tokenizer->tokenize($stringQuery);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The parser expected a token to be present but the list is empty.');

        // The Tokenizer insert an EndOfQuery token automatically, so we make
        // sure to remove it.
        array_pop($tokens);
        $parser->parse($tokens);
    }

    public function testParseFailsIfUnexpectedTokenFollowsCondition(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = '(AND)';
        $tokens = $tokenizer->tokenize($stringQuery);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('unexpected value "close bracket" at char 5');

        $parser->parse($tokens);
    }

    public function testParseFailsIfUnexpectedTokenFollowsQualifier(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $stringQuery = 'type:(foo)';
        $tokens = $tokenizer->tokenize($stringQuery);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('unexpected value "open bracket" at char 6');

        $parser->parse($tokens);
    }
}
