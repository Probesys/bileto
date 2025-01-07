<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Query;

/**
 * @phpstan-import-type Token from Tokenizer
 */
class SyntaxError extends \UnexpectedValueException
{
    private int $position;

    private string $value;

    public const BRACKET_UNEXPECTED = 0;
    public const BRACKET_NOT_CLOSED = 1;
    public const QUOTE_NOT_CLOSED = 2;
    public const QUALIFIER_EMPTY = 3;
    public const QUALIFIER_INVALID = 4;
    public const TOKEN_UNEXPECTED = 5;

    public static function bracketUnexpected(int $position): SyntaxError
    {
        $message = "unexpected bracket at char {$position}";
        return new SyntaxError($message, self::BRACKET_UNEXPECTED, $position);
    }

    public static function bracketNotClosed(int $position): SyntaxError
    {
        $message = "bracket at char {$position} is not closed";
        return new SyntaxError($message, self::BRACKET_NOT_CLOSED, $position);
    }

    public static function quoteNotClosed(int $position): SyntaxError
    {
        $message = "double quote at char {$position} is not closed";
        return new SyntaxError($message, self::QUOTE_NOT_CLOSED, $position);
    }

    public static function qualifierEmpty(int $position): SyntaxError
    {
        $message = "qualifier at char {$position} is empty";
        return new SyntaxError($message, self::QUALIFIER_EMPTY, $position);
    }

    public static function qualifierInvalid(int $position, string $value): SyntaxError
    {
        $message = "qualifier \"{$value}\" at char {$position} is invalid";
        return new SyntaxError($message, self::QUALIFIER_INVALID, $position);
    }

    /**
     * @param Token $token
     */
    public static function tokenUnexpected(int $position, array $token): SyntaxError
    {
        $value = $token['value'] ?? $token['type']->value;
        $message = "unexpected value \"{$value}\" at char {$position}";
        return new SyntaxError($message, self::TOKEN_UNEXPECTED, $position);
    }

    private function __construct(string $message, int $code, int $position, string $value = '')
    {
        parent::__construct("Syntax error: {$message}", $code);
        $this->position = $position;
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
