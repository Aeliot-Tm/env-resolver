<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Exception\RuntimeException;

final readonly class StringProcessor
{
    private const ENV_PREFIX = '%env(';
    private const ENV_SUFFIX = ')%';

    /**
     * Marker used to temporarily replace escaped %% sequences.
     */
    private const ESCAPE_MARKER = "\x00__ESCAPED_PERCENT__\x00";

    /**
     * Maximum nesting depth for placeholders.
     */
    private const MAX_NESTING_DEPTH = 100;

    public function __construct(private ResolverInterface $resolver = new Resolver())
    {
    }

    public function process(string $config, ?\Closure $postProcessor = null): string
    {
        $postProcessor ??= static function (mixed $value): string {
            return is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        };

        // Step 1: Replace escaped %% with temporary marker
        $config = str_replace('%%', self::ESCAPE_MARKER, $config);

        // Step 2: Iteratively resolve nested placeholders from innermost to outermost
        // Each iteration processes ALL placeholders at the current deepest level
        $depth = 0;

        while (($placeholders = $this->findAllInnermostPlaceholders($config)) !== []) {
            if ($depth >= self::MAX_NESTING_DEPTH) {
                throw new RuntimeException('Maximum nesting depth exceeded while processing string');
            }

            // Process placeholders from right to left to preserve positions
            foreach (array_reverse($placeholders) as [$start, $end, $heap]) {
                $resolvedValue = (string)$postProcessor($this->resolver->resolve($heap));
                $config = substr($config, 0, $start).$resolvedValue.substr($config, $end);
            }

            $depth++;
        }

        // Step 3: Restore escaped %% back to single %
        return str_replace(self::ESCAPE_MARKER, '%', $config);
    }

    /**
     * Find all innermost %env(...)% placeholders (those that contain no nested %env(...) inside).
     *
     * @return list<array{0: int, 1: int, 2: string}> List of [start_position, end_position, heap]
     */
    private function findAllInnermostPlaceholders(string $input): array
    {
        $prefixLen = strlen(self::ENV_PREFIX);
        $suffixLen = strlen(self::ENV_SUFFIX);

        // Find all %env( positions
        $positions = [];
        $offset = 0;
        while (($pos = strpos($input, self::ENV_PREFIX, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + $prefixLen;
        }

        if ($positions === []) {
            return [];
        }

        $result = [];
        $len = strlen($input);

        foreach ($positions as $startPos) {
            $contentStart = $startPos + $prefixLen;
            $depth = 1;
            $i = $contentStart;

            while ($i < $len && $depth > 0) {
                // Check for nested %env(
                if (substr($input, $i, $prefixLen) === self::ENV_PREFIX) {
                    $depth++;
                    $i += $prefixLen;
                    continue;
                }

                // Check for )%
                if (substr($input, $i, $suffixLen) === self::ENV_SUFFIX) {
                    $depth--;
                    if ($depth === 0) {
                        $heap = substr($input, $contentStart, $i - $contentStart);
                        // Only add if this placeholder has no nested %env( inside
                        if (!str_contains($heap, self::ENV_PREFIX)) {
                            $result[] = [$startPos, $i + $suffixLen, $heap];
                        }
                    }
                    $i += $suffixLen;
                    continue;
                }

                $i++;
            }
        }

        return $result;
    }
}
