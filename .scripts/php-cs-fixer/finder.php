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

return $finder = (new PhpCsFixer\Finder())
    ->files()
    ->ignoreVCS(true)
    ->in([dirname(__DIR__, 2), dirname(__DIR__, 2) . '/.scripts'])
    ->exclude(['tests/fixtures', 'var', 'vendor']);
