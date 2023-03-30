<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils\Queries;

enum TokenType: string
{
    case And = 'and';
    case CloseBracket = 'close bracket';
    case Comma = 'comma';
    case EndOfQuery = 'end of query';
    case Id = 'id';
    case Not = 'not';
    case OpenBracket = 'open bracket';
    case Or = 'or';
    case Qualifier = 'qualifier';
    case Text = 'text';
}
