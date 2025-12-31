<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Test\Unit;

use Aeliot\EnvResolver\ThreadBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThreadBuilder::class)]
final class ThreadBuilderTest extends TestCase
{
    public static function getDataForTestPositiveFlow(): iterable
    {
        // Simple env variable
        yield 'simple env' => [[['env', 'MY_ENV']], 'MY_ENV'];
        yield 'explicit env' => [[['env', 'MY_ENV']], 'env:MY_ENV'];
        yield 'env from env' => [[['env', 'MY_ENV'], ['env']], 'env:env:MY_ENV'];

        // Modifier as env name
        yield 'env as env key' => [[['env', 'env']], 'env'];
        yield 'base64 as env key' => [[['env', 'base64']], 'base64'];
        yield 'const as env key' => [[['env', 'const']], 'const'];
        yield 'file as env key' => [[['env', 'file']], 'file'];
        yield 'require as env key' => [[['env', 'require']], 'require'];

        // Constant processor
        yield 'simple const' => [[['const', 'MY_ENV']], 'const:MY_ENV'];
        yield 'const from const' => [[['const', 'MY_ENV'], ['const']], 'const:const:MY_ENV'];
        yield 'const from env' => [[['env', 'MY_ENV'], ['const']], 'const:env:MY_ENV'];
        yield 'env from const' => [[['const', 'MY_ENV'], ['env']], 'env:const:MY_ENV'];

        // File processor
        yield 'file modifier' => [[['file', 'MY_ENV']], 'file:MY_ENV'];
        yield 'require modifier' => [[['require', 'MY_ENV']], 'require:MY_ENV'];

        yield 'file from env' => [[['env', 'MY_ENV'], ['file']], 'file:env:MY_ENV'];
        yield 'file from file' => [[['file', 'MY_ENV'], ['file']], 'file:file:MY_ENV'];
        yield 'file from require' => [[['require', 'MY_ENV'], ['file']], 'file:require:MY_ENV'];

        yield 'require from require' => [[['require', 'MY_ENV'], ['require']], 'require:require:MY_ENV'];
        yield 'require from file' => [[['file', 'MY_ENV'], ['require']], 'require:file:MY_ENV'];
        yield 'require from env' => [[['env', 'MY_ENV'], ['require']], 'require:env:MY_ENV'];
        yield 'require from const' => [[['const', 'MY_ENV'], ['require']], 'require:const:MY_ENV'];

        yield 'env from file' => [[['file', 'MY_ENV'], ['env']], 'env:file:MY_ENV'];
        yield 'env from require' => [[['require', 'MY_ENV'], ['env']], 'env:require:MY_ENV'];

        // Simple modifiers
        yield 'base64 of env' => [[['env', 'MY_ENV'], ['base64']], 'base64:MY_ENV'];
        yield 'base64 of env explicitly' => [[['env', 'MY_ENV'], ['base64']], 'base64:env:MY_ENV'];
        yield 'double base64 from base64' => [[['env', 'W10='], ['base64'], ['base64']], 'base64:base64:W10='];
        yield 'double base64 from explicit env' => [
            [['env', 'MY_ENV'], ['base64'], ['base64']],
            'base64:base64:env:MY_ENV',
        ];
    }

    #[DataProvider('getDataForTestPositiveFlow')]
    public function testPositiveFlow(array $steps, string $heap): void
    {
        self::assertSame($steps, (new ThreadBuilder())->getSteps($heap));
    }
}