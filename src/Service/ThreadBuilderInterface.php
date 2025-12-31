<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Exception\RuntimeException;

interface ThreadBuilderInterface
{
    /**
     * @return list<int,array{0: string, 1?: string}>
     *
     * @throws RuntimeException
     */
    public function getSteps(string $heap): iterable;
}