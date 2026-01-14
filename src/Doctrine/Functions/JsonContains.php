<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * "JSON_CONTAINS" "(" StringPrimary "," StringPrimary ")"
 */
class JsonContains extends FunctionNode
{
    private Node $leftValue;
    private Node $rightValue;

    public function getSql(SqlWalker $sqlWalker): string
    {
        $dbPlatform = $sqlWalker->getConnection()->getDatabasePlatform();

        $leftValueExpression = $this->leftValue->dispatch($sqlWalker);
        $rightValueExpression = $this->rightValue->dispatch($sqlWalker);

        if ($dbPlatform instanceof PostgreSQLPlatform) {
            return "{$leftValueExpression}::jsonb @> {$rightValueExpression}::jsonb";
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            return "JSON_CONTAINS({$leftValueExpression}, {$rightValueExpression})";
        } else {
            throw new \LogicException('Operation JSON_CONTAINS is not supported by platform.');
        }
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->leftValue = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->rightValue = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
