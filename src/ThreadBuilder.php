<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver;

final readonly class ThreadBuilder
{
    /**
     * @return array<int,array<int,string>>
     */
    public function getSteps(string $heap): array
    {
        $steps = [];
        $parts = explode(':', $heap);
        $count = count($parts);
        if (1 === $count){
            array_unshift($parts, 'env');
        } elseif(!\in_array($parts[$count - 2], ['const', 'direct', 'env', 'file', 'require'], true)) {
            $parts = [...array_slice($parts, 0, $count - 1), 'env', $parts[$count - 1]];
        }

        do {
            $part = array_shift($parts);
            $count = count($parts);

            $step = [];
            if (in_array($part, ['const', 'env', 'file', 'require'], true)) {
                $step[] = $part;
                if (1 === $count) {
                    $step[] = array_shift($parts);
                }
            }

            if ('direct' === $part) {
                $step[] = $part;
                if (1 === $count) {
                    $step[] = array_shift($parts);
                }
            }

            if (in_array($part, [
                'bool',
                'base64',
                'float',
                'int',
                'json',
                'not',
            ], true)) {
                $step[] = $part;
            }

            $steps[] = $step;
        } while ($parts);

        return array_reverse($steps);
    }
}