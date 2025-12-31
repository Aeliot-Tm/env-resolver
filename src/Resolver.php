<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver;

use Aeliot\EnvResolver\Exception\EnvFoundException;
use Aeliot\EnvResolver\Exception\FileNotFoundException;
use Aeliot\EnvResolver\Exception\InvalidEnumException;
use Aeliot\EnvResolver\Exception\InvalidNameException;
use Aeliot\EnvResolver\Exception\InvalidValueException;
use Aeliot\EnvResolver\Exception\KeyFoundException;
use Aeliot\EnvResolver\Exception\NotSupportedEnumCaseException;

final readonly class Resolver
{
    public function __construct(private ThreadBuilder $threadBuilder = new ThreadBuilder())
    {
    }

    public function resolve(string $heap): mixed
    {
        $value = null;
        $steps = $this->threadBuilder->getSteps($heap);
        foreach ($steps as $step) {
            $modifier = $step[0];
            switch ($modifier) {
                case 'base64':
                    $value = $step[1] ?? $value;
                    if (!\is_scalar($value)) {
                        throw new InvalidNameException(\sprintf('Non-scalar base64 (resolved from "%s").', $heap));
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
                    break;
                case 'bool':
                case 'not':
                    if (1 < \count($step)) {
                        throw new InvalidValueException(
                            \sprintf('Undefined direct value (resolved from "%s").', $heap)
                        );
                    }
                    $value = (bool)(
                    filter_var($value, \FILTER_VALIDATE_BOOL)
                        ?: filter_var($value, \FILTER_VALIDATE_INT)
                        ?: filter_var($value, \FILTER_VALIDATE_FLOAT)
                    );

                    if ('not' === $modifier) {
                        $value = !$value;
                    }

                    break;
                case 'const':
                    $name = $step[1] ?? $value;
                    if (!\is_scalar($name)) {
                        throw new InvalidNameException(
                            \sprintf(
                                'Invalid constant name: const var "%s" is non-scalar (resolved from "%s").',
                                $name,
                                $heap
                            )
                        );
                    }
                    if (!\defined($name)) {
                        throw new EnvFoundException(
                            \sprintf('Undefined constant "%s" (resolved from "%s").', $name, $heap)
                        );
                    }

                    $value = \constant($name);
                    break;
                case 'direct':
                    if (2 < \count($step)) {
                        throw new InvalidValueException(
                            \sprintf('Undefined direct value (resolved from "%s").', $heap)
                        );
                    }
                    $value = $step[1];
                    break;
                case 'enum':
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
                    break;
                case 'env':
                    $name = $step[1] ?? $value;
                    if (!\is_scalar($name)) {
                        throw new InvalidNameException(
                            \sprintf(
                                'Invalid environment name: env var "%s" is non-scalar (resolved from "%s").',
                                $name,
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

                    break;
                case 'file':
                case 'require':
                    $name = $step[1] ?? $value;
                    if (!\is_scalar($name)) {
                        throw new InvalidNameException(
                            \sprintf(
                                'Invalid file name: env var "%s" is non-scalar (resolved from "%s").',
                                $name,
                                $heap
                            )
                        );
                    }
                    if (!is_file($name)) {
                        throw new FileNotFoundException(
                            \sprintf('File "%s" not found (resolved from "%s").', $name, $heap)
                        );
                    }

                    $value = 'file' === $modifier ? \file_get_contents($name) : require $name;

                    break;
                case 'float':
                    $value = filter_var($value, \FILTER_VALIDATE_FLOAT);
                    if (false === $value) {
                        throw new InvalidValueException(
                            \sprintf('Non-numeric env var cannot be cast to float (resolved from "%s").', $heap)
                        );
                    }
                    break;
                case 'int':
                    $value = filter_var($value, \FILTER_VALIDATE_INT) ?: filter_var($value, \FILTER_VALIDATE_FLOAT);
                    if (false === $value) {
                        throw new InvalidValueException(
                            \sprintf('Non-numeric env var cannot be cast to float (resolved from "%s").', $heap)
                        );
                    }
                    $value = (int)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    if (\JSON_ERROR_NONE !== json_last_error()) {
                        throw new InvalidValueException(
                            \sprintf('Invalid JSON "%s" (resolved from "%s")', json_last_error_msg(), $heap)
                        );
                    }
                    // TODO: consider checking if not array?

                    break;
                case 'key':
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

                    $value = $value[$key];
                    break;
                case 'query_string':
                    $queryString = parse_url($value, \PHP_URL_QUERY) ?: (parse_url($value, \PHP_URL_SCHEME) ? '' : $value);
                    parse_str($queryString, $result);
                    $value = $result;
                    break;
                case 'strcsv':
                    if (!\is_scalar($value)) {
                        throw new InvalidNameException(\sprintf('Non-scalar csv (resolved from "%s").', $heap));
                    }

                    $value = '' === (string)$value ? [] : str_getcsv((string)$value, ',', '"', '');
                    break;
                case 'string':
                    $value = (string)$value;
                    break;
                case 'trim':
                    $value = trim((string)$value);
                    break;
                case 'urlencode':
                    $value = urlencode((string)$value);
                    break;
            }
        }

        return $value;
    }
}