<?php
/**
 * JBZoo Path
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package   Path
 * @license   MIT
 * @copyright Copyright (C) JBZoo.com,  All rights reserved.
 * @link      https://github.com/JBZoo/Path
 */

namespace JBZoo\PHPUnit;

use JBZoo\Path\Package;
use JBZoo\Path\Exception;

/**
 * Class Test
 * @package JBZoo\PHPUnit
 */
class Test extends PHPUnit
{

    public function testShouldDoSomeStreetMagic()
    {
        $obj = new Package();

        is('street magic', $obj->doSomeStreetMagic());
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testShouldShowException()
    {
        throw new Exception('Test message');
    }
}
