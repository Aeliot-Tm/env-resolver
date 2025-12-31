<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver;

use Aeliot\EnvResolver\Exception\EnvFoundException;
use Aeliot\EnvResolver\Exception\FileNotFoundException;
use Aeliot\EnvResolver\Exception\InvalidNameException;
use Aeliot\EnvResolver\Exception\InvalidValueException;

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
                    $value = (int) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    if (\JSON_ERROR_NONE !== json_last_error()) {
                        throw new InvalidValueException(\sprintf('Invalid JSON "%s" (resolved from "%s")', json_last_error_msg(), $heap));
                    }
                    // TODO: consider checking if not array?

                    break;
            }
        }

        return $value;
    }
}