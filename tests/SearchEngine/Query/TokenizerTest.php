<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\Query;

use App\SearchEngine\Query\TokenType;
use App\SearchEngine\Query\Tokenizer;
use App\SearchEngine\Query\SyntaxError;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type Token from Tokenizer
 */
class TokenizerTest extends TestCase
{
    /**
     * @dataProvider tokensProvider
     *
     * @param Token[] $expectedTokens
     */
    public function testTokenize(string $query, array $expectedTokens): void
    {
        $tokenizer = new Tokenizer();
        // EndOfQuery must be present at the end of all the list of tokens.
        // This allows to not clutter the provider with a token that is always
        // present.
        $expectedTokens[] = ['type' => TokenType::EndOfQuery];

        $tokens = $tokenizer->tokenize($query);

        $this->assertSame(count($expectedTokens), count($tokens));
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $expectedToken = $expectedTokens[$i];
            $this->assertSame($expectedToken['type'], $token['type']);
            if (isset($expectedToken['value'])) {
                $this->assertTrue(isset($token['value']));
                $this->assertSame($expectedToken['value'], $token['value']);
            }
        }
    }

    public function testTokenizeFailsIfQuotesAreNotClosed(): void
    {
        $tokenizer = new Tokenizer();
        $query = '"some string';

        $this->expectException(SyntaxError::class);
        $this->expectExceptionCode(SyntaxError::QUOTE_NOT_CLOSED);
        $this->expectExceptionMessage('Syntax error: double quote at char 1 is not closed');

        $tokenizer->tokenize($query);
    }

    public function testTokenizeFailsIfBracketsAreNotClosed(): void
    {
        $tokenizer = new Tokenizer();
        $query = '(some (string';

        $this->expectException(SyntaxError::class);
        $this->expectExceptionCode(SyntaxError::BRACKET_NOT_CLOSED);
        $this->expectExceptionMessage('Syntax error: bracket at char 1 is not closed');

        $tokenizer->tokenize($query);
    }

    public function testTokenizeFailsIfBracketsAreNotOpen(): void
    {
        $tokenizer = new Tokenizer();
        $query = '(some string))';

        $this->expectException(SyntaxError::class);
        $this->expectExceptionCode(SyntaxError::BRACKET_UNEXPECTED);
        $this->expectExceptionMessage('Syntax error: unexpected bracket at char 14');

        $tokenizer->tokenize($query);
    }

    public function testTokenizeFailsIfQualifierIsInvalid(): void
    {
        $tokenizer = new Tokenizer();
        $query = 'some type@:incident';

        $this->expectException(SyntaxError::class);
        $this->expectExceptionCode(SyntaxError::QUALIFIER_INVALID);
        $this->expectExceptionMessage('Syntax error: qualifier "type@" at char 6 is invalid');

        $tokenizer->tokenize($query);
    }

    public function testTokenizeFailsIfQualifierIsEmpty(): void
    {
        $tokenizer = new Tokenizer();
        $query = 'some :incident';

        $this->expectException(SyntaxError::class);
        $this->expectExceptionCode(SyntaxError::QUALIFIER_EMPTY);
        $this->expectExceptionMessage('Syntax error: qualifier at char 6 is empty');

        $tokenizer->tokenize($query);
    }

    /**
     * @return array<array{string, Token[]}>
     */
    public static function tokensProvider(): array
    {
        // Note that positions are wrong in the Tokens. This is because it
        // would be too complicated to maintain correctly and efficiently.
        // Also, they are not used during tests. It's mainly so that PHPStan
        // doesn't scream too loud.
        return [
            [
                'some text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                '"some text"',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some text', 'position' => 0],
                ],
            ],

            [
                '\"some text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => '"some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                '\some text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                'some\ text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some text', 'position' => 0],
                ],
            ],

            [
                '\\\\', // equivalent to '\\' in the string passed to the tokenizer
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => '\\', 'position' => 0],
                ],
            ],

            [
                'some,text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::Comma, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                '(some text)',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::OpenBracket, 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                    ['type' => TokenType::CloseBracket, 'position' => 0],
                ],
            ],

            [
                'some(text)',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::OpenBracket, 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                    ['type' => TokenType::CloseBracket, 'position' => 0],
                ],
            ],

            [
                '(some)text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::OpenBracket, 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::CloseBracket, 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                '"(some text)"',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => '(some text)', 'position' => 0],
                ],
            ],

            [
                '\(some text\)',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => '(some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text)', 'position' => 0],
                ],
            ],

            [
                'some NOT text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Not, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                'some AND text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                'some OR text',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'some', 'position' => 0],
                    ['type' => TokenType::Or, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'text', 'position' => 0],
                ],
            ],

            [
                'type:incident',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Qualifier, 'value' => 'type', 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'incident', 'position' => 0],
                ],
            ],

            [
                'type:incident,request',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Qualifier, 'value' => 'type', 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'incident', 'position' => 0],
                    ['type' => TokenType::Comma, 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'request', 'position' => 0],
                ],
            ],

            [
                '-type:incident',
                [
                    ['type' => TokenType::And, 'position' => 0],
                    ['type' => TokenType::Not, 'position' => 0],
                    ['type' => TokenType::Qualifier, 'value' => 'type', 'position' => 0],
                    ['type' => TokenType::Text, 'value' => 'incident', 'position' => 0],
                ],
            ],
        ];
    }
}
