<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Query;

/**
 * @phpstan-type Token array{
 *     'type': TokenType,
 *     'position': int,
 *     'value'?: non-empty-string,
 * }
 */
class Tokenizer
{
    /**
     * @return Token[]
     */
    public function tokenize(string $query): array
    {
        $tokens = [];

        // Add a final whitespace to simplify the foreach loop
        $query = $query . ' ';

        $charPosition = 0;
        $currentText = '';
        $quoteOpenPosition = 0;
        $bracketsOpeningPositions = [];
        $escaped = false;

        foreach (mb_str_split($query) as $char) {
            $charPosition += 1;
            $isWhitespace = \ctype_space($char);
            $inQuotes = $quoteOpenPosition > 0;

            if ($escaped) {
                // The current char is escaped, so we add it to the current
                // text, even if it's a special char (e.g. whitespace, comma,
                // quote, etc.)
                $currentText = $currentText . $char;
                $escaped = false;
            } elseif ($char === '\\') {
                // The current char is a (not escaped) backslash, so we set
                // the variable $escaped to true to escape the next char.
                $escaped = true;
            } elseif ($char === '"') {
                // The current char is a quote, so we change the quoteOpenPosition
                // depending on the fact we're already in quotes or not.
                $quoteOpenPosition = $inQuotes ? 0 : $charPosition;
            } elseif ($inQuotes) {
                // The current char is in quotes, so we add it to the current
                // text, even if it's a special char.
                $currentText = $currentText . $char;
            } elseif ($char === '(') {
                // The current char is an open bracket. The bracket can be in
                // the middle of text, so we transform the current text into a
                // token, then we add the OpenBracket token to the list of
                // tokens.
                if ($currentText) {
                    $token = $this->textToToken($currentText, $charPosition);
                    $this->addToken($tokens, $token);
                    $currentText = '';
                }

                $token = [
                    'type' => TokenType::OpenBracket,
                    'position' => $charPosition,
                ];
                $this->addToken($tokens, $token);

                $bracketsOpeningPositions[] = $charPosition;
            } elseif ($char === ')') {
                // Same as the open bracket (see above), except that we check
                // first that the closing bracket has a corresponding opened
                // bracket.
                if (empty($bracketsOpeningPositions)) {
                    throw SyntaxError::bracketUnexpected($charPosition);
                }

                if ($currentText) {
                    $token = $this->textToToken($currentText, $charPosition);
                    $this->addToken($tokens, $token);
                    $currentText = '';
                }

                $tokens[] = [
                    'type' => TokenType::CloseBracket,
                    'position' => $charPosition,
                ];

                array_pop($bracketsOpeningPositions);
            } elseif ($char === ',') {
                // Same as the open and close brackets (see above): the comma
                // can be in the middle of the text, so we transform the text
                // into a token.
                if ($currentText) {
                    $token = $this->textToToken($currentText, $charPosition);
                    $this->addToken($tokens, $token);
                    $currentText = '';
                }

                $tokens[] = [
                    'type' => TokenType::Comma,
                    'position' => $charPosition,
                ];
            } elseif ($char === ':') {
                // The colon (:) marks the presence of a qualifier (i.e. the
                // text before the colon).
                if ($currentText === '') {
                    throw SyntaxError::qualifierEmpty($charPosition);
                }

                if (!preg_match('/^-?[\w\-]+$/', $currentText)) {
                    $position = $charPosition - mb_strlen($currentText);
                    throw SyntaxError::qualifierInvalid($position, $currentText);
                }

                if ($currentText[0] === '-') {
                    // If the qualifier starts with a "-", we transform this
                    // char into a "Not" token.
                    $token = [
                        'type' => TokenType::Not,
                        'position' => $charPosition - mb_strlen($currentText),
                    ];
                    $this->addToken($tokens, $token);

                    $currentText = substr($currentText, 1);
                }

                assert($currentText !== '');

                $token = [
                    'type' => TokenType::Qualifier,
                    'value' => $currentText,
                    'position' => $charPosition - mb_strlen($currentText),
                ];
                $this->addToken($tokens, $token);

                $currentText = '';
            } elseif (!$isWhitespace) {
                // We are at the end of the possibilities. We just check that
                // the current char is not a whitespace, and we add it to the
                // currentText.
                $currentText = $currentText . $char;
            } elseif ($isWhitespace && $currentText !== '') {
                // If the current char is a whitespace, the token is complete.
                // We add it to the list of tokens.
                $token = $this->textToToken($currentText, $charPosition);
                $this->addToken($tokens, $token);
                $currentText = '';
            }
        }

        if ($quoteOpenPosition > 0) {
            throw SyntaxError::quoteNotClosed($quoteOpenPosition);
        }

        if (count($bracketsOpeningPositions) > 0) {
            throw SyntaxError::bracketNotClosed($bracketsOpeningPositions[0]);
        }

        $tokens[] = [
            'type' => TokenType::EndOfQuery,
            'position' => $charPosition + 1,
        ];

        return $tokens;
    }

    /**
     * @param non-empty-string $text
     *
     * @return Token
     */
    private function textToToken(string $text, int $positionEnd): array
    {
        $position = $positionEnd - mb_strlen($text);

        if ($text === 'NOT') {
            return [
                'type' => TokenType::Not,
                'position' => $position,
            ];
        } elseif ($text === 'AND') {
            return [
                'type' => TokenType::And,
                'position' => $position,
            ];
        } elseif ($text === 'OR') {
            return [
                'type' => TokenType::Or,
                'position' => $position,
            ];
        } else {
            return [
                'type' => TokenType::Text,
                'value' => $text,
                'position' => $position,
            ];
        }
    }

    /**
     * @param Token[] $tokens
     * @param Token $token
     */
    private function addToken(array &$tokens, array $token): void
    {
        // In our DSL, the AND token is implicit between two terms. This method
        // makes sure to insert these implicit tokens at the right moment.
        // This probably could be done differently (maybe in an intermediate
        // step between the tokenizer and the parser?), but it's good enough
        // as a first implementation.

        if (empty($tokens)) {
            // The list of tokens always starts by an AND
            $tokens[] = [
                'type' => TokenType::And,
                'position' => 0
            ];
        } elseif (
            // These tokens must be preceded by an AND
            $token['type'] === TokenType::Text ||
            $token['type'] === TokenType::Qualifier ||
            $token['type'] === TokenType::OpenBracket ||
            $token['type'] === TokenType::Not
        ) {
            $lastToken = $tokens[array_key_last($tokens)];
            if (
                // ... except if the last token in the list is one of these
                $lastToken['type'] !== TokenType::Qualifier &&
                $lastToken['type'] !== TokenType::Comma &&
                $lastToken['type'] !== TokenType::Not &&
                $lastToken['type'] !== TokenType::Or &&
                $lastToken['type'] !== TokenType::And
            ) {
                $tokens[] = [
                    'type' => TokenType::And,
                    'position' => $token['position'],
                ];
            }
        }

        $tokens[] = $token;
    }
}
