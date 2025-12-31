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

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Enum\Modifier;
use Aeliot\EnvResolver\Exception\InvalidHeapException;

final readonly class ThreadBuilder implements ThreadBuilderInterface
{
    /**
     * @return list<int,array{0: string, 1?: string}>
     */
    public function getSteps(string $heap): array
    {
        $steps = [];
        $parts = $this->getParts($heap);
        $supported = array_flip((new \ReflectionClass(Modifier::class))->getConstants());

        do {
            $part = array_shift($parts);
            if (!isset($supported[$part])) {
                throw new InvalidHeapException(\sprintf('Unexpected modifier "%s" in heap: %s', $part, $heap));
            }
            $step = [$part];
            if (\in_array($part, [Modifier::CONST, Modifier::ENV, Modifier::FILE, Modifier::REQUIRE], true)) {
                if (1 === \count($parts)) {
                    $step[] = array_shift($parts);
                }
            } elseif (Modifier::DIRECT === $part) {
                if (1 !== \count($parts)) {
                    throw new InvalidHeapException(
                        \sprintf('Modifier "direct" allowed only as penultimate. (resolved from "%s")', $heap)
                    );
                }
                $step[] = array_shift($parts);
            } elseif (\in_array($part, [Modifier::ENUM, Modifier::KEY], true)) {
                if (\count($parts) < 3) {
                    // expects at least: modifier -> modifier_supporter -> source -> source_key
                    throw new InvalidHeapException(\sprintf('Missed "%s" in heap: %s', $part, $heap));
                }
                $step[] = array_shift($parts);
            }

            $steps[] = $step;
        } while ($parts);

        return array_reverse($steps);
    }

    /**
     * @return list<string>
     */
    private function getParts(string $heap): array
    {
        $parts = explode(':', $heap);
        $count = \count($parts);
        if (1 === $count) {
            // add default 'env' when single
            array_unshift($parts, Modifier::ENV);
        } elseif (!\in_array($parts[$count - 2], [
            Modifier::CONST,
            Modifier::DIRECT,
            Modifier::ENV,
            Modifier::FILE,
            Modifier::REQUIRE,
        ], true)) {
            // default penultimate to 'env'
            $parts = [...\array_slice($parts, 0, $count - 1), Modifier::ENV, $parts[$count - 1]];
        }

        return $parts;
    }
}
