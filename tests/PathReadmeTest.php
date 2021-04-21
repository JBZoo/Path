<?php

/**
 * JBZoo Toolbox - Path
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Path
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/Path
 */

declare(strict_types=1);

namespace JBZoo\PHPUnit;

/**
 * Class PathReadmeTest
 *
 * @package JBZoo\PHPUnit
 */
class PathReadmeTest extends AbstractReadmeTest
{
    protected $packageName = 'Path';

    protected function setUp(): void
    {
        parent::setUp();
        $this->params['strict_types'] = true;
    }
}
