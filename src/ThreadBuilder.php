<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver;

final readonly class ThreadBuilder
{
    /**
     * @return string[]
     */
    public function getSteps(string $heap): array
    {
        $steps = [];
        $parts = explode(':', $heap);
        do {
            $part = array_shift($parts);
            $count = count($parts);
            if (!$count) {
                $steps[] = ['env', $part];
                break;
            }

            $step = [];
            if (in_array($part, ['const', 'env', 'file', 'require'], true)) {
                $step[] = $part;
                if (1 === $count) {
                    $step[] = array_shift($parts);
                }
            }

            if (in_array($part, [
                'base64',
            ], true)) {
                $step[] = $part;
                if (1 === $count) {
                    $tail = array_shift($parts);
                    if (!$steps) {
                        $steps[] = [$part];
                        $steps[] = ['env', $tail];
                        break;
                    }
                    $step[] = $tail;
                }
            }

            $steps[] = $step;
        } while ($parts);

        return array_reverse($steps);
    }
}