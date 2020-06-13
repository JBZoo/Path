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

use JBZoo\Path\Path;
use JBZoo\Utils\FS;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PathPerformanceTest
 *
 * @package JBZoo\Path
 */
class PathPerformanceTest extends PHPUnit
{
    /**
     * @var string
     */
    protected $root;

    protected function setUp(): void
    {
        skip('1');

        $root = FS::clean(__DIR__ . '/test', '/');
        FS::rmdir($root);

        mkdir($root, 0777, true);
        $this->root = $root;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $fs = new Filesystem();
        $fs->remove($this->root);
    }

    public function testCompareWithRealpath()
    {
        $fs = new Filesystem();
        $root = $this->root . '/';

        Benchmark::compare([
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
        ], ['count' => 1000, 'name' => 'Compare with realpath']);
        isTrue(true);
    }

    public function testPathResolver()
    {
        $fs = new Filesystem();
        $root = $this->root . '/';

        $virtPath = new Path();
        $virtPath->set('default', $root);

        Benchmark::compare([
            'new path (new obj and dir)' => function () use ($fs, $root) {

                $newDir = $root . mt_rand();
                $fs->mkdir($newDir);

                // start
                $virtPath = new Path();
                $virtPath->set('default', $newDir);
                $result = $virtPath->get('default:');
                // end

                return $result;
            },
            'same path (new)'            => function () use ($fs, $root) {
                $virtPath = new Path();
                $virtPath->set('default', $root);
                $result = $virtPath->get('default:');

                return $result;
            },
            'same path'                  => function () use ($fs, $root, $virtPath) {
                $result = $virtPath->get('default:');
                return $result;
            },
        ], ['count' => 1000, 'name' => 'path resolver']);
        isTrue(true);
    }
}
