<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Query;

use App\SearchEngine\Query;

/**
 * The LL grammar is defined by the following rules:
 *
 * S -> QUERY
 *
 * QUERY -> CONDITIONAL_QUERY
 * QUERY -> CONDITIONAL_QUERY QUERY
 * QUERY -> CONDITIONAL_QUERY end_of_query
 *
 * CONDITIONAL_QUERY -> or CONDITION
 * CONDITIONAL_QUERY -> and CONDITION
 *
 * CONDITION -> CRITERIA
 * CONDITION -> not CRITERIA
 *
 * CRITERIA -> TEXT_OR_LIST
 * CRITERIA -> qualifier TEXT_OR_LIST
 * CRITERIA -> open_bracket QUERY close_bracket
 *
 * TEXT_OR_LIST -> text
 * TEXT_OR_LIST -> text comma TEXT_OR_LIST
 *
 * Each rule of the grammar is implemented by a method in the Parser class to
 * make the code as clear as possible.
 *
 * @phpstan-import-type Token from Tokenizer
 */
class Parser
{
    /** @var Token[] */
    private array $tokens;

    /** @param Token[] $tokens */
    public function parse(array $tokens): Query
    {
        if (empty($tokens)) {
            throw new \LogicException('The parser cannot be called with an empty list of tokens.');
        }

        if ($tokens[0]['type'] != TokenType::And) {
            // We want to be sure that the first token is an AND (and not an
            // OR), otherwise the query is invalid. If it has been an OR, it
            // would be grammatically valid. Thus, we could improve the
            // grammar, but I think that would make it more complicated for
            // nothing.
            // Also, the tokenizer makes sure to insert an AND at the
            // beginning of the tokens, so this code should never be reached.
            // But we're never too sure.
            throw new \LogicException('An AND keyword is expected char 1');
        }

        $this->tokens = $tokens;

        $query = new Query();
        $this->ruleQuery($query);
        return $query;
    }

    private function ruleQuery(Query $query): void
    {
        $this->ruleConditionalQuery($query);

        $currentToken = $this->readToken();

        if (
            $currentToken['type'] === TokenType::Or ||
            $currentToken['type'] === TokenType::And
        ) {
            $this->ruleQuery($query);
        } elseif ($currentToken['type'] === TokenType::EndOfQuery) {
            $this->consumeToken(TokenType::EndOfQuery);
        }
    }

    private function ruleConditionalQuery(Query $query): void
    {
        $currentToken = $this->readToken();

        if ($currentToken['type'] === TokenType::Or) {
            $this->consumeToken(TokenType::Or);
            $this->ruleCondition($query, 'or');
        } elseif ($currentToken['type'] === TokenType::And) {
            $this->consumeToken(TokenType::And);
            $this->ruleCondition($query, 'and');
        } else {
            // This is a LogicException (not SyntaxError) because the Tokenizer
            // should insert AND keywords when expected.
            $position = $currentToken['position'];
            throw new \LogicException("An AND keyword is expected char {$position}");
        }
    }

    /**
     * @param value-of<Query\Condition::OPERATORS> $operator
     */
    private function ruleCondition(Query $query, string $operator): void
    {
        $currentToken = $this->readToken();

        if ($currentToken['type'] === TokenType::Not) {
            $this->consumeToken(TokenType::Not);
            $this->ruleCriteria($query, $operator, true);
        } else {
            $this->ruleCriteria($query, $operator, false);
        }
    }

    /**
     * @param value-of<Query\Condition::OPERATORS> $operator
     */
    private function ruleCriteria(Query $query, string $operator, bool $not): void
    {
        $currentToken = $this->readToken();

        if ($currentToken['type'] === TokenType::Text) {
            $value = $this->ruleTextOrList();

            $condition = Query\Condition::textCondition($operator, $value, $not);
            $query->addCondition($condition);
        } elseif ($currentToken['type'] === TokenType::Qualifier) {
            $this->consumeToken(TokenType::Qualifier);

            assert(isset($currentToken['value']));

            $value = $this->ruleTextOrList();

            $condition = Query\Condition::qualifierCondition($operator, $currentToken['value'], $value, $not);
            $query->addCondition($condition);
        } elseif ($currentToken['type'] === TokenType::OpenBracket) {
            $this->consumeToken(TokenType::OpenBracket);

            $subQuery = new Query();
            $this->ruleQuery($subQuery);

            $condition = Query\Condition::queryCondition($operator, $subQuery, $not);
            $query->addCondition($condition);

            $this->consumeToken(TokenType::CloseBracket);
        } else {
            throw SyntaxError::tokenUnexpected(
                $currentToken['position'],
                $currentToken,
            );
        }
    }

    /**
     * @return non-empty-string|non-empty-string[]
     */
    private function ruleTextOrList(): mixed
    {
        $currentToken = $this->readToken();

        $this->consumeToken(TokenType::Text);

        assert(isset($currentToken['value']));

        $nextToken = $this->readToken();

        if ($nextToken['type'] === TokenType::Comma) {
            $this->consumeToken(TokenType::Comma);
            $textOrList = $this->ruleTextOrList();
            if (is_array($textOrList)) {
                return array_merge([$currentToken['value']], $textOrList);
            } else {
                return [$currentToken['value'], $textOrList];
            }
        } else {
            return $currentToken['value'];
        }
    }

    /**
     * @return Token
     */
    private function readToken(): array
    {
        $token = reset($this->tokens);

        if ($token === false) {
            throw new \LogicException('The parser expected a token to be present but the list is empty.');
        }

        return $token;
    }

    private function consumeToken(TokenType $expectedTokenType): void
    {
        $currentToken = $this->readToken();
        if ($currentToken['type'] !== $expectedTokenType) {
            throw SyntaxError::tokenUnexpected(
                $currentToken['position'],
                $currentToken,
            );
        }

        array_shift($this->tokens);
    }
}
