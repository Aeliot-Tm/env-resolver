<?php
declare(strict_types=1);

namespace Aeliot\EnvResolver\Service;

use Aeliot\EnvResolver\Exception\RuntimeException;

interface ResolverInterface
{
    /**
     * @throws RuntimeException
     */
    public function resolve(string $heap): mixed;
}