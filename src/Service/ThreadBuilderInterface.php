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
