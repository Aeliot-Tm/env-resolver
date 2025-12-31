<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Exception\InvalidHeapException;

final readonly class ThreadBuilder
{
    /**
     * @return list<int,list<int,string>>
     */
    public function getSteps(string $heap): array
    {
        $steps = [];
        $parts = $this->getParts($heap);

        do {
            $part = array_shift($parts);
            $step = [$part];
            if (in_array($part, ['const', 'env', 'file', 'require'], true)) {
                if (1 === count($parts)) {
                    $step[] = array_shift($parts);
                }
            } elseif ('direct' === $part) {
                if (1 !== count($parts)) {
                    throw new InvalidHeapException(sprintf('Modifier "direct" allowed only as penultimate. (resolved from "%s")', $heap));
                }
                $step[] = array_shift($parts);
            } elseif (in_array($part, ['enum', 'key'], true)) {
                if (count($parts) < 3) {
                    // expects at least: modifier -> modifier_supporter -> source -> source_key
                    throw new InvalidHeapException(sprintf('Missed "%s" in heap: %s', $part, $heap));
                }
                $step[] = array_shift($parts);
            } elseif (!$this->isLonelyModifier($part)) {
                // DO NOTHING
                throw new InvalidHeapException(sprintf('Unexpected modifier "%s" in heap: %s', $part, $heap));
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
        $count = count($parts);
        if (1 === $count){
            array_unshift($parts, 'env');
        } elseif(!\in_array($parts[$count - 2], ['const', 'direct', 'env', 'file', 'require'], true)) {
            $parts = [...array_slice($parts, 0, $count - 1), 'env', $parts[$count - 1]];
        }

        return $parts;
    }

    public function isLonelyModifier(string $part): bool
    {
        return in_array($part, [
            'bool',
            'base64',
            'float',
            'int',
            'json',
            'not',
            'query_string',
            'strcsv',
            'string',
            'trim',
            'url',
            'urlencode',
        ], true);
    }
}