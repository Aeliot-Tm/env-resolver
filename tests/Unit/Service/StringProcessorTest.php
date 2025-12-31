<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Test\Unit\Service;

use Aeliot\EnvResolver\Exception\RuntimeException;
use Aeliot\EnvResolver\Service\Resolver;
use Aeliot\EnvResolver\Service\StringProcessor;
use Aeliot\EnvResolver\Service\ThreadBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringProcessor::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(ThreadBuilder::class)]
final class StringProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['STRING_PROCESSOR_TEST_VAR'] = 'test_value';
        $_ENV['STRING_PROCESSOR_TEST_HOST'] = 'localhost';
        $_ENV['STRING_PROCESSOR_TEST_PORT'] = '8080';
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['STRING_PROCESSOR_TEST_VAR'],
            $_ENV['STRING_PROCESSOR_TEST_HOST'],
            $_ENV['STRING_PROCESSOR_TEST_PORT']
        );
    }

    public static function getDataForTestPositiveFlow(): iterable
    {
        // Simple substitution
        yield 'single placeholder' => [
            'test_value',
            '%env(STRING_PROCESSOR_TEST_VAR)%',
        ];

        yield 'placeholder with text before' => [
            'value: test_value',
            'value: %env(STRING_PROCESSOR_TEST_VAR)%',
        ];

        yield 'placeholder with text after' => [
            'test_value is the value',
            '%env(STRING_PROCESSOR_TEST_VAR)% is the value',
        ];

        yield 'placeholder surrounded by text' => [
            'The value is test_value here',
            'The value is %env(STRING_PROCESSOR_TEST_VAR)% here',
        ];

        // Multiple placeholders
        yield 'multiple placeholders' => [
            'http://localhost:8080',
            'http://%env(STRING_PROCESSOR_TEST_HOST)%:%env(STRING_PROCESSOR_TEST_PORT)%',
        ];

        yield 'same placeholder multiple times' => [
            'test_value and test_value',
            '%env(STRING_PROCESSOR_TEST_VAR)% and %env(STRING_PROCESSOR_TEST_VAR)%',
        ];

        // No placeholders
        yield 'string without placeholders' => [
            'plain string without placeholders',
            'plain string without placeholders',
        ];

        yield 'empty string' => [
            '',
            '',
        ];

        // Direct values
        yield 'direct value' => [
            'hello',
            '%env(direct:hello)%',
        ];

        yield 'int from direct value' => [
            '42',
            '%env(int:direct:42)%',
        ];

        // Complex modifiers
        yield 'base64 decoded' => [
            'hello',
            '%env(base64:direct:aGVsbG8=)%',
        ];

        // Partial replacement
        yield 'env in url template' => [
            'postgres://localhost:8080/db',
            'postgres://%env(STRING_PROCESSOR_TEST_HOST)%:%env(STRING_PROCESSOR_TEST_PORT)%/db',
        ];
    }

    public static function getDataForTestJsonEncode(): iterable
    {
        // Arrays are automatically json_encoded
        yield 'json array' => [
            '["a","b"]',
            '%env(json:direct:["a","b"])%',
        ];

        // Note: JSON object with colon is parsed incorrectly by ThreadBuilder (colon is separator),
        // so we use base64 encoded JSON object: {"key":"value"} = eyJrZXkiOiJ2YWx1ZSJ9
        yield 'json object via base64' => [
            '{"key":"value"}',
            '%env(json:base64:direct:eyJrZXkiOiJ2YWx1ZSJ9)%',
        ];

        // Integer values are json_encoded (become string representation)
        yield 'int value' => [
            '42',
            '%env(int:direct:42)%',
        ];

        // Boolean values are json_encoded
        yield 'bool true' => [
            'true',
            '%env(bool:direct:1)%',
        ];

        yield 'bool false' => [
            'false',
            '%env(bool:direct:0)%',
        ];

        // Float values are json_encoded
        yield 'float value' => [
            '3.14',
            '%env(float:direct:3.14)%',
        ];
    }

    #[DataProvider('getDataForTestPositiveFlow')]
    public function testPositiveFlow(string $expected, string $config): void
    {
        $processor = new StringProcessor();
        self::assertSame($expected, $processor->process($config));
    }

    #[DataProvider('getDataForTestJsonEncode')]
    public function testDefaultJsonEncode(string $expected, string $config): void
    {
        $processor = new StringProcessor();
        self::assertSame($expected, $processor->process($config));
    }

    public function testJsonEncodeInContext(): void
    {
        $processor = new StringProcessor();

        // Array in the middle of a string
        $result = $processor->process('data: %env(json:direct:["apple","banana"])% end');
        self::assertSame('data: ["apple","banana"] end', $result);
    }

    public function testCustomPostProcessorOverridesDefault(): void
    {
        $processor = new StringProcessor();

        // Custom postProcessor to join array elements instead of json_encode
        $result = $processor->process(
            'items: %env(json:direct:["apple","banana"])%',
            static fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value
        );
        self::assertSame('items: apple, banana', $result);
    }

    public function testNestedPlaceholders(): void
    {
        $_ENV['STRING_PROCESSOR_NESTED_KEY'] = 'STRING_PROCESSOR_TEST_VAR';

        $processor = new StringProcessor();

        // Chain resolvers (env:env:...) - NOT nested placeholders
        $result = $processor->process('%env(env:STRING_PROCESSOR_NESTED_KEY)%');
        self::assertSame('STRING_PROCESSOR_TEST_VAR', $result);

        $result = $processor->process('%env(env:env:STRING_PROCESSOR_NESTED_KEY)%');
        self::assertSame('test_value', $result);

        unset($_ENV['STRING_PROCESSOR_NESTED_KEY']);
    }

    public function testNestedPlaceholdersWithPercentEnv(): void
    {
        $_ENV['STRING_PROCESSOR_ENV_NAME'] = 'STRING_PROCESSOR_TEST_VAR';

        $processor = new StringProcessor();

        // Nested %env()% inside another %env()%
        // Inner: %env(STRING_PROCESSOR_ENV_NAME)% → STRING_PROCESSOR_TEST_VAR
        // Outer: %env(STRING_PROCESSOR_TEST_VAR)% → test_value
        $result = $processor->process('%env(%env(STRING_PROCESSOR_ENV_NAME)%)%');
        self::assertSame('test_value', $result);

        unset($_ENV['STRING_PROCESSOR_ENV_NAME']);
    }

    public function testDeepNestedPlaceholders(): void
    {
        $_ENV['LEVEL_3'] = 'LEVEL_2';
        $_ENV['LEVEL_2'] = 'LEVEL_1';
        $_ENV['LEVEL_1'] = 'final_value';

        $processor = new StringProcessor();

        // Triple nesting: innermost resolves first
        $result = $processor->process('%env(%env(%env(LEVEL_3)%)%)%');
        self::assertSame('final_value', $result);

        unset($_ENV['LEVEL_3'], $_ENV['LEVEL_2'], $_ENV['LEVEL_1']);
    }

    public function testNestedWithModifiers(): void
    {
        $_ENV['FILE_PATH_VAR'] = 'tests/fixtures/Unit/ResolverTest/file_with_content.txt';

        $processor = new StringProcessor();

        // Nested placeholder with file modifier
        // Inner: %env(FILE_PATH_VAR)% → tests/fixtures/Unit/ResolverTest/file_with_content.txt
        // Outer: %env(file:tests/fixtures/...)% → FILE_CONTENT
        $result = $processor->process('%env(file:%env(FILE_PATH_VAR)%)%');
        self::assertSame('FILE_CONTENT', $result);

        unset($_ENV['FILE_PATH_VAR']);
    }

    public function testEscapedPlaceholder(): void
    {
        $processor = new StringProcessor();

        // Escaped placeholder: %%env(...)%% → %env(...)% (literal, not processed)
        $result = $processor->process('%%env(STRING_PROCESSOR_TEST_VAR)%%');
        self::assertSame('%env(STRING_PROCESSOR_TEST_VAR)%', $result);

        // Mixed: escaped and non-escaped
        $result = $processor->process('Value: %env(STRING_PROCESSOR_TEST_VAR)%, literal: %%env(other)%%');
        self::assertSame('Value: test_value, literal: %env(other)%', $result);

        // Just escaped percents without env
        $result = $processor->process('100%% discount');
        self::assertSame('100% discount', $result);

        // Multiple escaped percents
        $result = $processor->process('a %% b %% c');
        self::assertSame('a % b % c', $result);
    }

    public function testRegularPercentNotProcessed(): void
    {
        $processor = new StringProcessor();

        // Regular percent signs that don't match the pattern should remain
        $result = $processor->process('100% complete');
        self::assertSame('100% complete', $result);

        $result = $processor->process('env(test) without percent');
        self::assertSame('env(test) without percent', $result);

        $result = $processor->process('%not_env_pattern%');
        self::assertSame('%not_env_pattern%', $result);
    }

    public function testMaxNestingDepthExceeded(): void
    {
        $processor = new StringProcessor();

        // Build string with 101 levels of nesting using direct: (exceeds limit of 100)
        // Each level: %env(direct:...)% strips one level and returns the content
        $config = str_repeat('%env(direct:', 101) . 'x' . str_repeat(')%', 101);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maximum nesting depth exceeded while processing string');
        $processor->process($config);
    }

    public function testManyPlaceholdersWithoutNesting(): void
    {
        $processor = new StringProcessor();

        // Create 200 independent placeholders (should work without limit)
        $config = str_repeat('%env(direct:x)% ', 200);
        $expected = str_repeat('x ', 200);

        $result = $processor->process($config);
        self::assertSame($expected, $result);
    }

    public function test101NonNestedPlaceholders(): void
    {
        $processor = new StringProcessor();

        // 101 non-nested placeholders should work (depth = 1, not exceeding limit)
        $config = str_repeat('%env(direct:a)% ', 101);
        $expected = str_repeat('a ', 101);

        $result = $processor->process($config);
        self::assertSame($expected, $result);
    }

    public function testTwoNestedSetsDepth51Each(): void
    {
        $processor = new StringProcessor();

        // Two separate nested placeholders using direct:, each 51 levels deep
        // Using direct: at each level - it strips one level and returns the content
        $config = str_repeat('%env(direct:', 51) . 'result_a' . str_repeat(')%', 51)
            . ' AND ' . str_repeat('%env(direct:', 51) . 'result_b' . str_repeat(')%', 51);

        $result = $processor->process($config);
        self::assertSame('result_a AND result_b', $result);
    }
}
