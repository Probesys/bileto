<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Query;

enum TokenType: string
{
    case And = 'and';
    case CloseBracket = 'close bracket';
    case Comma = 'comma';
    case EndOfQuery = 'end of query';
    case Not = 'not';
    case OpenBracket = 'open bracket';
    case Or = 'or';
    case Qualifier = 'qualifier';
    case Text = 'text';
}
