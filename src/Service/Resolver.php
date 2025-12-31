<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Enum\Modifier;
use Aeliot\EnvResolver\Exception\EnvFoundException;
use Aeliot\EnvResolver\Exception\FileNotFoundException;
use Aeliot\EnvResolver\Exception\InvalidEnumException;
use Aeliot\EnvResolver\Exception\InvalidNameException;
use Aeliot\EnvResolver\Exception\InvalidValueException;
use Aeliot\EnvResolver\Exception\KeyFoundException;
use Aeliot\EnvResolver\Exception\NotSupportedEnumCaseException;

final readonly class Resolver implements ResolverInterface
{
    public function __construct(private ThreadBuilderInterface $threadBuilder = new ThreadBuilder())
    {
    }

    public function resolve(string $heap): mixed
    {
        $value = null;
        foreach ($this->threadBuilder->getSteps($heap) as $step) {
            $value = match ($step[0]) {
                Modifier::BASE64 => $this->resolveBase64($step, $value, $heap),
                Modifier::BOOL => $this->resolveBool($step, $value, $heap),
                Modifier::CONST => $this->resolveConst($step, $value, $heap),
                Modifier::DIRECT => $this->resolveDirect($step, $heap),
                Modifier::ENUM => $this->resolveEnum($step, $value, $heap),
                Modifier::ENV => $this->resolveEnv($step, $value, $heap),
                Modifier::FILE => $this->resolveFile($step, $value, $heap),
                Modifier::FLOAT => $this->resolveFloat($value, $heap),
                Modifier::INT => $this->resolveInt($value, $heap),
                Modifier::JSON => $this->resolveJson($value, $heap),
                Modifier::NOT => !$this->resolveBool($step, $value, $heap),
                Modifier::KEY => $this->resolveKey($step, $value, $heap),
                Modifier::QUERY_STRING => $this->resolveQueryString($value),
                Modifier::REQUIRE => $this->resolveRequire($step, $value, $heap),
                Modifier::STR_CSV => $this->resolveCsvString($value, $heap),
                Modifier::STRING => (string)$value,
                Modifier::TRIM => trim((string)$value),
                Modifier::URL => $this->resolveURL($value, $heap),
                Modifier::URL_ENCODE => urlencode((string)$value),
            };
        }

        return $value;
    }

    private function resolveBase64(array $step, mixed $value, string $heap): string
    {
        $value = $step[1] ?? $value;
        if (!\is_scalar($value)) {
            throw new InvalidValueException(\sprintf('Non-scalar base64 (resolved from "%s").', $heap));
        }
        // replace for the handling of URL-safe
        $value = strtr((string)($step[1] ?? $value), '-_', '+/');
        if (preg_match('~[^A-Za-z0-9+/=]~', $value)) {
            throw new InvalidValueException(\sprintf('Invalid base64 (resolved from "%s").', $heap));
        }
        $value = base64_decode($value);
        if (false === $value) {
            throw new InvalidValueException(\sprintf('Cannot decode base64 (resolved from "%s").', $heap));
        }

        return $value;
    }

    private function resolveBool(array $step, mixed $value, string $heap): bool
    {
        if (1 < \count($step)) {
            throw new InvalidValueException(
                \sprintf('Undefined direct value (resolved from "%s").', $heap)
            );
        }

        return (bool)(
        filter_var($value, \FILTER_VALIDATE_BOOL)
            ?: filter_var($value, \FILTER_VALIDATE_INT)
            ?: filter_var($value, \FILTER_VALIDATE_FLOAT)
        );
    }

    private function resolveConst(array $step, mixed $value, string $heap): mixed
    {
        $name = $step[1] ?? $value;
        if (!\is_scalar($name)) {
            throw new InvalidNameException(
                \sprintf(
                    'Invalid constant name: value of type "%s" is non-scalar (resolved from "%s").',
                    get_debug_type($name),
                    $heap
                )
            );
        }
        if (!\defined($name)) {
            throw new EnvFoundException(
                \sprintf('Undefined constant "%s" (resolved from "%s").', $name, $heap)
            );
        }

        return \constant($name);
    }

    /**
     * @return string[]
     */
    private function resolveCsvString(mixed $value, string $heap): array
    {
        if (!\is_scalar($value)) {
            throw new InvalidValueException(\sprintf('Non-scalar csv (resolved from "%s").', $heap));
        }

        return '' === (string)$value ? [] : str_getcsv((string)$value, ',', '"', '');
    }

    private function resolveDirect(array $step, string $heap): string
    {
        if (2 < \count($step)) {
            throw new InvalidValueException(
                \sprintf('Undefined direct value (resolved from "%s").', $heap)
            );
        }

        return $step[1];
    }

    private function resolveEnum(array $step, mixed $value, string $heap): \BackedEnum
    {
        if (!(\is_string($value) || \is_int($value))) {
            throw new InvalidValueException(
                \sprintf('Resolved value did not result in a string or int (resolved from "%s").', $heap)
            );
        }
        $backedEnumClassName = $step[1];
        if (!is_subclass_of($backedEnumClassName, \BackedEnum::class)) {
            throw new InvalidEnumException(
                \sprintf('"%s" is not a "%s".', $backedEnumClassName, \BackedEnum::class)
            );
        }

        $value = $backedEnumClassName::tryFrom($value);
        if (null === $value) {
            throw new NotSupportedEnumCaseException(
                \sprintf('Enum value "%s" is not backed by "%s".', $value, $backedEnumClassName)
            );
        }

        return $value;
    }

    private function resolveEnv(array $step, mixed $value, string $heap): mixed
    {
        $name = $step[1] ?? $value;
        if (!\is_scalar($name)) {
            throw new InvalidNameException(
                \sprintf(
                    'Invalid environment name: value of type "%s" is non-scalar (resolved from "%s").',
                    get_debug_type($name),
                    $heap
                )
            );
        }

        if (isset($_ENV) && array_key_exists($name, $_ENV)) {
            $value = $_ENV[$name];
        } elseif (false === ($value = getenv($name))) {
            throw new EnvFoundException(
                \sprintf('Undefined environment variable "%s" (resolved from "%s").', $name, $heap)
            );
        }

        return $value;
    }

    private function resolveFile(array $step, mixed $value, string $heap): string
    {
        $name = $step[1] ?? $value;
        $this->validateFileName($name, $heap);

        $contents = \file_get_contents($name);
        if (false === $contents) {
            throw new FileNotFoundException(
                \sprintf('File "%s" not readable (resolved from "%s").', $name, $heap)
            );
        }

        return $contents;
    }

    private function resolveFloat(mixed $value, string $heap): float
    {
        $value = filter_var($value, \FILTER_VALIDATE_FLOAT);
        if (false === $value) {
            throw new InvalidValueException(
                \sprintf('Non-numeric env var cannot be cast to float (resolved from "%s").', $heap)
            );
        }

        return (float)$value;
    }

    private function resolveInt(mixed $value, string $heap): int
    {
        $value = filter_var($value, \FILTER_VALIDATE_INT) ?: filter_var($value, \FILTER_VALIDATE_FLOAT);
        if (false === $value) {
            throw new InvalidValueException(
                \sprintf('Non-numeric env var cannot be cast to float (resolved from "%s").', $heap)
            );
        }

        return (int)$value;
    }

    private function resolveJson(mixed $value, string $heap): mixed
    {
        $value = json_decode($value, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidValueException(
                \sprintf('Invalid JSON "%s" (resolved from "%s")', json_last_error_msg(), $heap)
            );
        }

        // TODO: consider checking if not array?

        return $value;
    }

    private function resolveKey(array $step, mixed $value, string $heap): mixed
    {
        if (!\is_array($value)) {
            throw new InvalidValueException(
                \sprintf('Cannot get value by key from not array value (resolved from "%s")', $heap)
            );
        }
        $key = $step[1];
        if (!\array_key_exists($key, $value)) {
            throw new KeyFoundException(
                \sprintf('Key "%s" not found in %s (resolved from "%s").', $key, json_encode($value), $heap)
            );
        }

        return $value[$key];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveQueryString(mixed $value): array
    {
        $queryString = parse_url($value, \PHP_URL_QUERY)
            ?: (parse_url($value, \PHP_URL_SCHEME) ? '' : $value);
        parse_str($queryString, $result);

        return $result;
    }

    private function resolveRequire(array $step, mixed $value, string $heap): mixed
    {
        $name = $step[1] ?? $value;
        $this->validateFileName($name, $heap);

        return require $name;
    }

    private function resolveURL(mixed $value, string $heap): mixed
    {
        $params = parse_url($value);
        if (false === $params) {
            throw new InvalidValueException(\sprintf('Invalid URL in env var (resolved from "%s")', $heap));
        }
        if (!isset($params['scheme'], $params['host'])) {
            throw new InvalidValueException(
                \sprintf('Invalid URL in env var: scheme and host expected (resolved from "%s").', $heap)
            );
        }
        if (('\\' !== \DIRECTORY_SEPARATOR || 'file' !== $params['scheme']) && false !== ($i = strpos(
                $value,
                '\\'
            )) && $i < strcspn($value, '?#')) {
            throw new InvalidValueException(
                \sprintf('Invalid URL in env var: backslashes are not allowed (resolved from "%s").', $heap)
            );
        }
        if (\ord($value[0]) <= 32 || \ord($value[-1]) <= 32 || \strlen($value) !== strcspn(
                $value,
                "\r\n\t"
            )) {
            throw new InvalidValueException(
                \sprintf(
                    'Invalid URL in env var: leading/trailing ASCII control characters or whitespaces are not allowed (resolved from "%s")',
                    $heap
                )
            );
        }
        $params += [
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => null,
            'fragment' => null,
        ];

        $params['user'] = null !== $params['user'] ? rawurldecode($params['user']) : null;
        $params['pass'] = null !== $params['pass'] ? rawurldecode($params['pass']) : null;

        // remove the '/' separator
        $params['path'] = '/' === ($params['path'] ?? '/') ? '' : substr($params['path'], 1);

        return $params;
    }

    private function validateFileName(mixed $name, string $heap): void
    {
        if (!\is_scalar($name)) {
            throw new InvalidNameException(
                \sprintf(
                    'Invalid file name: value of type "%s" is non-scalar (resolved from "%s").',
                    get_debug_type($name),
                    $heap
                )
            );
        }
        if (!is_file($name)) {
            throw new FileNotFoundException(
                \sprintf('File "%s" not found (resolved from "%s").', $name, $heap)
            );
        }
    }
}