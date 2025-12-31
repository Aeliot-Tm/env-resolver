<?php

declare(strict_types=1);

/*
 * This file is part of the Env Resolver project.
 *
 * (c) Anatoliy Melnikov <5785276@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Aeliot\EnvResolver\Enum;

/**
 * Don't use native enum for extendibility.
 */
interface Modifier
{
    public const BASE64 = 'base64';
    public const BOOL = 'bool';
    public const CONST = 'const';
    public const DIRECT = 'direct';
    public const ENUM = 'enum';
    public const ENV = 'env';
    public const FILE = 'file';
    public const FLOAT = 'float';
    public const INT = 'int';
    public const JSON = 'json';
    public const NOT = 'not';
    public const KEY = 'key';
    public const QUERY_STRING = 'query_string';
    public const REQUIRE = 'require';
    public const STR_CSV = 'strcsv';
    public const STRING = 'string';
    public const TRIM = 'trim';
    public const URL = 'url';
    public const URL_ENCODE = 'urlencode';
}
