<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Test\Unit;

use Aeliot\EnvResolver\Exception\EnvFoundException;
use Aeliot\EnvResolver\Exception\FileNotFoundException;
use Aeliot\EnvResolver\Exception\InvalidNameException;
use Aeliot\EnvResolver\Exception\InvalidValueException;
use Aeliot\EnvResolver\Resolver;
use Aeliot\EnvResolver\ThreadBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolver::class)]
#[UsesClass(ThreadBuilder::class)]
final class ResolverTest extends TestCase
{
    protected function setUp(): void
    {
        define('RESOLVER_TEST_CONST_A', 'CONST_A');
        define('RESOLVER_TEST_CONST_FROM_FILE', 'CONST_FROM_FILE');
        define('RESOLVER_TEST_CONST_FROM_REQUIRE', 'CONST_FROM_REQUIRE');
        define('RESOLVER_TEST_CONST_NAME_DONOR', 'RESOLVER_TEST_CONST_RESULTING');
        define('RESOLVER_TEST_CONST_RESULTING', 'CONST_RESULTING');
        define('RESOLVER_TEST_CONST_UNDEFINED_DONOR', 'RESOLVER_TEST_CONST_UNDEFINED');

        $_ENV['RESOLVER_TEST_ENV_A'] = 'ENV_A';
        $_ENV['RESOLVER_TEST_ENV_BASE64_EMPTY_ARRAY'] = 'W10=';
        $_ENV['RESOLVER_TEST_ENV_BASE64_INVALID'] = '()';
        $_ENV['RESOLVER_TEST_ENV_FROM_FILE'] = 'ENV_FROM_FILE';
        $_ENV['RESOLVER_TEST_ENV_FROM_REQUIRE'] = 'ENV_FROM_REQUIRE';
        $_ENV['RESOLVER_TEST_ENV_NAME_DONOR'] = 'RESOLVER_TEST_ENV_RESULTING';
        $_ENV['RESOLVER_TEST_ENV_RESULTING'] = 'ENV_RESULTING';
        $_ENV['RESOLVER_TEST_ENV_FILE_WITH_CONTENT'] = 'tests/fixtures/Unit/ResolverTest/file_with_content.txt';
        $_ENV['RESOLVER_TEST_ENV_REQUIRE_WITH_CONTENT'] = 'tests/fixtures/Unit/ResolverTest/require_with_content.php';
        $_ENV['RESOLVER_TEST_ENV_UNDEFINED_DONOR'] = 'RESOLVER_TEST_ENV_UNDEFINED';
        putenv("RESOLVER_TEST_ENV_PUTENV=ENV_PUTENV");
    }

    public static function getDataForTestRuntimeException(): iterable
    {
        yield 'const undefined' => [EnvFoundException::class, 'const:RESOLVER_TEST_CONST_UNDEFINED'];
        yield 'const undefined from const' => [
            EnvFoundException::class,
            'const:const:RESOLVER_TEST_CONST_UNDEFINED_DONOR',
        ];
        yield 'const from required array' => [
            InvalidNameException::class,
            'const:require:tests/fixtures/Unit/ResolverTest/empty_array.php',
        ];

        yield 'env undefined by $_ENV' => [
            EnvFoundException::class,
            'RESOLVER_TEST_ENV_UNDEFINED',
        ];
        yield 'env undefined by getenv' => [
            EnvFoundException::class,
            'RESOLVER_TEST_ENV_UNDEFINED_FROM_PUTENV',
        ];
        yield 'env undefined from env' => [
            EnvFoundException::class,
            'env:env:RESOLVER_TEST_ENV_UNDEFINED_DONOR',
        ];
        yield 'env from required array' => [
            InvalidNameException::class,
            'env:require:tests/fixtures/Unit/ResolverTest/empty_array.php',
        ];

        yield 'file not fount' => [
            FileNotFoundException::class,
            'file:tests/fixtures/Unit/ResolverTest/not_existing_file.txt',
        ];
        yield 'file from required array' => [
            InvalidNameException::class,
            'file:require:tests/fixtures/Unit/ResolverTest/empty_array.php',
        ];
        yield 'require from required array' => [
            InvalidNameException::class,
            'require:require:tests/fixtures/Unit/ResolverTest/empty_array.php',
        ];
        yield 'require not fount' => [
            FileNotFoundException::class,
            'require:tests/fixtures/Unit/ResolverTest/not_existing_file.txt',
        ];

        yield 'base64 invalid' => [
            InvalidValueException::class,
            'base64:RESOLVER_TEST_ENV_BASE64_INVALID',
        ];

        yield 'json invalid array with elements from base64 of direct value' => [
            InvalidValueException::class,
            'json:base64:direct:WyJhIjoxLCJiIjoyXQ==',
        ];
        yield 'json invalid from direct string without quotes' => [
            InvalidValueException::class,
            'json:direct:a_string',
        ];
    }

    public static function getDataForTestPositiveFlow(): iterable
    {
        // Simple env variable
        yield 'simple env' => ['ENV_A', 'RESOLVER_TEST_ENV_A'];
        yield 'explicit env' => ['ENV_A', 'env:RESOLVER_TEST_ENV_A'];
        yield 'simple env from getenv' => ['ENV_PUTENV', 'RESOLVER_TEST_ENV_PUTENV'];
        yield 'env from env' => ['ENV_RESULTING', 'env:env:RESOLVER_TEST_ENV_NAME_DONOR'];

        // Simple constant
        yield 'simple const' => ['CONST_A', 'const:RESOLVER_TEST_CONST_A'];
        yield 'const from const' => ['CONST_RESULTING', 'const:const:RESOLVER_TEST_CONST_NAME_DONOR'];

        // File processor
        yield 'file modifier' => [
            'FILE_CONTENT',
            'file:tests/fixtures/Unit/ResolverTest/file_with_content.txt',
        ];
        yield 'file from env' => [
            'FILE_CONTENT',
            'file:env:RESOLVER_TEST_ENV_FILE_WITH_CONTENT',
        ];
        yield 'file from file' => [
            'FILE_CONTENT',
            'file:file:tests/fixtures/Unit/ResolverTest/file_from_file.txt',
        ];

        yield 'require modifier' => [
            'REQUIRE_CONTENT',
            'require:tests/fixtures/Unit/ResolverTest/require_with_content.php',
        ];
        yield 'require from env' => [
            'REQUIRE_CONTENT',
            'require:env:RESOLVER_TEST_ENV_REQUIRE_WITH_CONTENT',
        ];
        yield 'require from require' => [
            'REQUIRE_CONTENT',
            'require:require:tests/fixtures/Unit/ResolverTest/require_from_require.php',
        ];
        yield 'file from require' => [
            'FILE_CONTENT',
            'file:require:tests/fixtures/Unit/ResolverTest/file_from_require.php',
        ];
        yield 'require from file' => [
            'REQUIRE_CONTENT',
            'require:file:tests/fixtures/Unit/ResolverTest/require_from_file.txt',
        ];

        yield 'env from file' => [
            'ENV_FROM_FILE',
            'env:file:tests/fixtures/Unit/ResolverTest/env_from_file.txt',
        ];
        yield 'env from require' => [
            'ENV_FROM_REQUIRE',
            'env:require:tests/fixtures/Unit/ResolverTest/env_from_require.php',
        ];

        yield 'const from file' => [
            'CONST_FROM_FILE',
            'const:file:tests/fixtures/Unit/ResolverTest/const_from_file.txt',
        ];
        yield 'const from require' => [
            'CONST_FROM_REQUIRE',
            'const:require:tests/fixtures/Unit/ResolverTest/const_from_require.php',
        ];

        yield 'base64 to empty array string' => ['[]', 'base64:RESOLVER_TEST_ENV_BASE64_EMPTY_ARRAY',];
        yield 'direct double base64 to empty array string' => ['[]', 'base64:base64:direct:VzEwPQ=='];

        yield 'bool true from direct int value' => [true, 'bool:direct:1'];
        yield 'bool false from direct int value' => [false, 'bool:direct:0'];
        yield 'bool true from direct float value' => [true, 'bool:direct:1.58'];
        yield 'bool false from direct float value' => [false, 'bool:direct:0.00'];
        yield 'bool true from direct base64 of int string' => [true, 'bool:base64:direct:MQ=='];
        yield 'bool false from direct base64 of int string' => [false, 'bool:base64:direct:MA=='];

        yield 'bool false by not of bool from direct int value' => [false, 'not:bool:direct:1'];
        yield 'csv string from direct base64 value' => [
            ['John Doe', 'Nancy Adams'],
            'strcsv:base64:direct:IkpvaG4gRG9lIiwiTmFuY3kgQWRhbXMi',
        ];

        yield 'float 0.1 from direct value' => [0.1, 'float:direct:0.1'];
        yield 'float 0.0 from direct value' => [0.0, 'float:direct:0.0'];
        yield 'float 1.0 from direct value with tail on zeros' => [1.0, 'float:direct:1.000'];
        yield 'float 1.0 from direct int value' => [1.0, 'float:direct:1'];

        yield 'int 1 from direct value' => [1, 'int:direct:1'];
        yield 'int 0 from direct value' => [0, 'int:direct:0'];
        yield 'int 1 from direct float value 1.1' => [1, 'int:direct:1.1'];
        yield 'int 0 from direct float value' => [0, 'int:direct:0.0'];
        yield 'int 0 from direct float value 0.1 (less then zero)' => [0, 'int:direct:0.1'];

        yield 'json empty array from direct value' => [[], 'json:direct:[]'];
        yield 'json array with elements from base64 of direct value' => [
            ['a' => 1, 'b' => 2],
            'json:base64:direct:eyJhIjoxLCJiIjoyfQ==',
        ];
        yield 'json string from direct value' => ['a_string', 'json:direct:"a_string"'];

        yield 'string 1 from direct int value' => ['1', 'string:direct:1'];
        yield 'string 0 from direct int value' => ['0', 'string:direct:0'];
        yield 'string 0.1 from direct float value' => ['0.1', 'string:direct:0.1'];
        yield 'string 0.0 from direct float value' => ['0.0', 'string:direct:0.0'];
        yield 'string 1.000 from direct value with tail on zeros' => ['1.000', 'string:direct:1.000'];
        yield 'string 1.01 from floated direct value with tail on zeros' => ['1.01', 'string:float:direct:1.010'];
        yield 'string 1 from floated direct value with tail on zeros' => ['1', 'string:float:direct:1.000'];
        yield 'string 1 from floated direct int value' => ['1', 'string:float:direct:1'];
        yield 'string 0 from inted direct float value' => ['0', 'string:int:direct:0.0'];

        yield 'trim value' => ['a', 'trim:base64:direct:ICBhIA=='];
        yield 'urlencode value' => ['Data123%21%40-_+%2B', 'urlencode:base64:direct:RGF0YTEyMyFALV8gKw=='];
        yield 'key int from direct base64 json value' => [
            'Nancy Adams',
            // ["John Doe","Nancy Adams"]
            'key:1:json:base64:direct:WyJKb2huIERvZSIsIk5hbmN5IEFkYW1zIl0=',
        ];
        yield 'key nested from direct base64 json value' => [
            'Nancy Adams',
            // {"parent":{"name": "John Doe","child":"Nancy Adams"}}
            'key:child:key:parent:json:base64:direct:eyJwYXJlbnQiOnsibmFtZSI6ICJKb2huIERvZSIsImNoaWxkIjoiTmFuY3kgQWRhbXMifX0=',
        ];
    }

    #[DataProvider('getDataForTestPositiveFlow')]
    public function testPositiveFlow(mixed $expected, string $heap): void
    {
        self::assertSame($expected, (new Resolver())->resolve($heap));
    }

    #[DataProvider('getDataForTestRuntimeException')]
    public function testRuntimeException(string $expected, string $heap): void
    {
        $this->expectException($expected);
        (new Resolver())->resolve($heap);
    }
}