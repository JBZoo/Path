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

use JBZoo\Path\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PerformanceTest
 * @package JBZoo\Path
 */
class PerformanceTest extends PHPUnit
{
    public function testBenchmark()
    {
        $fs = new Filesystem();

        runBench(array(
            'JBZoo\Path' => function () use ($fs) {

                $dirName = mt_rand();
                $path    = __DIR__ . DS . $dirName;
                $fs->mkdir($path);

                // start
                $Path = Path::getInstance('JBZooPath');
                $Path->set(Path::DEFAULT_ALIAS, __DIR__ . DS . $dirName);
                $result = $Path->get('default:');
                // end

                $fs->remove($path);

                return $result;
            },
            'RealPath'   => function () use ($fs) {
                $dirName = mt_rand();
                $path    = __DIR__ . DS . $dirName;
                $fs->mkdir($path);

                // start
                $result = realpath($path);
                // end

                $fs->remove($path);

                return $result;
            },
        ), array('count' => 500, 'name' => 'Path lib'));
    }
}
