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

namespace JBZoo\PHPUnit;

/**
 * Class PathCodeStyleTest
 *
 * @package JBZoo\PHPUnit
 */
class PathCodeStyleTest extends AbstractCodestyleTest
{
    protected function setUp(): void
    {
        $this->excludePaths[] = 'resource';
        parent::setUp();
    }
}
