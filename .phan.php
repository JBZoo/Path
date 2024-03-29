<?php

/**
 * JBZoo Toolbox - Path.
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @see        https://github.com/JBZoo/Path
 */

declare(strict_types=1);

$default = include __DIR__ . '/vendor/jbzoo/codestyle/src/phan.php';

$config = \array_merge($default, [
    'directory_list' => [
        'src',

        // Libs
        'vendor/jbzoo/data',
        'vendor/jbzoo/utils',
    ],
]);
