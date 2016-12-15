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

use JBZoo\Utils\FS;
use JBZoo\Path\Path;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PerformanceTest
 * @package JBZoo\Path
 */
class PerformanceTest extends PHPUnit
{
    /**
     * @var string
     */
    protected $_root;

    public function setUp()
    {
        $root = FS::clean(__DIR__ . '/test', '/');
        FS::rmdir($root);

        mkdir($root, 0777, true);
        $this->_root = $root;
    }

    public function tearDown()
    {
        parent::tearDown();
        $fs = new Filesystem();
        $fs->remove($this->_root);
    }

    public function testCompareWithRealpath()
    {
        $fs   = new Filesystem();
        $root = $this->_root . '/';

        runBench(array(
            'JBZoo\Path' => function () use ($fs, $root) {

                $newDir = $root . mt_rand();
                $fs->mkdir($newDir);

                // start
                $virtPath = new Path();
                $virtPath->set('default', $newDir);
                $result = $virtPath->get('default:');
                // end

                $fs->remove($result);

                return $result;
            },
            'realpath()' => function () use ($fs, $root) {
                $newDir = $root . mt_rand();
                $fs->mkdir($newDir);

                // start
                $result = realpath($newDir);
                // end

                $fs->remove($result);

                return $result;
            },
        ), array('count' => 1000, 'name' => 'Compare with realpath'));
    }

    public function testPathResolver()
    {
        $fs   = new Filesystem();
        $root = $this->_root . '/';

        $virtPath = new Path();
        $virtPath->set('default', $root);

        runBench(array(
            'new path (new obj and dir)'  => function () use ($fs, $root) {

                $newDir = $root . mt_rand();
                $fs->mkdir($newDir);

                // start
                $virtPath = new Path();
                $virtPath->set('default', $newDir);
                $result = $virtPath->get('default:');
                // end

                return $result;
            },
            'same path (new)' => function () use ($fs, $root) {
                $virtPath = new Path();
                $virtPath->set('default', $root);
                $result = $virtPath->get('default:');

                return $result;
            },
            'same path' => function () use ($fs, $root, $virtPath) {
                $result = $virtPath->get('default:');
                return $result;
            },
        ), array('count' => 1000, 'name' => 'path resolver'));
    }
}
