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
use JBZoo\Path\Exception;
use JBZoo\Utils\FS;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PathTest
 * @package JBZoo\PHPUnit
 */
class PathTest extends PHPUnit
{

    protected $_root;
    protected $_paths = array();

    public function setup()
    {
        $this->_root = __DIR__;

        $this->_paths = array(
            $this->_root,
            $this->_root . DS . 'folder'
        );
    }

    public function testRegisterAppend()
    {
        $path = new Path();

        $path->register($this->_paths);
        $path->register($this->_paths, 'test');

        $expected = array(
            $this->_root . DS . 'folder',
            $this->_root,
        );

        $defaultPaths = $path->getPaths();
        $testPaths    = $path->getPaths('test');

        isSame($expected, $testPaths);
        isSame($expected, $defaultPaths);
    }

    public function testRegisterPrepend()
    {
        $path  = new Path();
        $paths = $this->_paths;

        $appendPath = $this->_root . DS . 'append';

        $path->register($paths);
        $path->register($appendPath, Path::DEFAULT_PACKAGE, Path::APPEND);

        array_push($paths, $appendPath);

        $expected = array(
            $this->_root . DS . 'folder',
            $this->_root,
            $appendPath,
        );

        $package = $path->getPaths();
        isSame($expected, $package);
    }

    public function testRegisterReset()
    {
        $path    = new Path();
        $newPath = array(
            $this->_root . DS . 'new-folder'
        );

        $path->register($this->_paths);
        $path->register($newPath, $path::DEFAULT_PACKAGE, $path::RESET);

        isSame($newPath, $path->getPaths());
    }

    public function testEmptyPackage()
    {
        $path = new Path();
        $path->register($this->_paths);

        $packagePaths = $path->getPaths('alias');
        isNull($packagePaths);
    }

    public function testIsVirtual()
    {
        $path = new Path();
        isTrue($path->isVirtual('alias:'));
        isTrue($path->isVirtual('alias:styles.css'));
        isTrue($path->isVirtual('alias:folder/styles.css'));
    }

    public function testIsNotVirtual()
    {
        $path = new Path();
        isFalse($path->isVirtual(__DIR__));
        isFalse($path->isVirtual(dirname(__DIR__)));
        isFalse($path->isVirtual('/folder/file.txt'));
    }

    public function testHasPrefix()
    {
        $path = new Path();
        $this->assertInternalType('string', $path->prefix(__DIR__));
        $this->assertInternalType('string', $path->prefix(dirname(__DIR__)));
        $this->assertInternalType('string', $path->prefix('P:\\\\Folder\\'));
    }

    public function testNoPrefix()
    {
        $path = new Path();
        isNull($path->prefix('folder/file.txt'));
        isNull($path->prefix('./folder/file.txt'));
        isNull($path->prefix('default:folder/file.txt'));
    }

    public function testParseVirtual()
    {
        $path = new Path();

        $path->register($this->_paths);
        $parse1 = $path->parse('default:text.txt');

        $path->register($this->_paths, 'package');
        $parse2 = $path->parse('package:folder/text.txt');

        $path->register($this->_paths, 'alias');
        $parse3 = $path->parse('my/folder', 'alias');

        $expectedPaths = array(
            $this->_root . DS . 'folder',
            $this->_root,
        );

        $expected1 = array('default', $expectedPaths, 'text.txt');
        $expected2 = array('package', $expectedPaths, 'folder/text.txt');
        $expected3 = array('alias', $expectedPaths, 'my/folder');

        isSame($expected1, $parse1);
        isSame($expected2, $parse2);
        isSame($expected3, $parse3);
    }

    public function testNormalize()
    {
        $path = new Path();

        isSame(FS::clean(__DIR__, '/'), $path->normalize(__DIR__));
        isSame('test/path/folder', $path->normalize('../test/path/folder/'));
        isSame('test/path/folder', $path->normalize('../../test/path/folder/'));
        isSame('test/path/folder', $path->normalize('..\..\test\path\folder\\'));
        isSame('test/path/folder', $path->normalize('..\../test///path/\/\folder/\\'));
    }

    public function testPathSuccess()
    {
        $path = new Path();
        $fs   = new Filesystem();

        $paths = array(
            $this->_root . DS . 'folder',
            $this->_root . DS . 'folder' . DS . 'folder',
        );

        list($dir1, $dir2) = $paths;

        $fs->mkdir($dir2);

        $_dir = $dir2 . DS . 'simple';
        $fs->mkdir($_dir);

        $f1 = $dir2 . DS . 'text.txt';
        $f2 = $dir2 . DS . 'file.pot';
        $f3 = $dir1 . DS . 'style.less';
        $f4 = $dir2 . DS . 'style.less';
        $f5 = $_dir . DS . 'file.txt';

        $fs->dumpFile($f1, '');
        $fs->dumpFile($f2, '');
        $fs->dumpFile($f3, '');
        $fs->dumpFile($f4, '');
        $fs->dumpFile($f5, '');

        $path->register($paths);

        isSame($path->normalize($f1), $path->path('default:text.txt'));
        isSame($path->normalize($f2), $path->path('default:file.pot'));

        isSame($path->normalize($dir2 . DS . 'style.less'), $path->path('default:/style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->path('default:\style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->path('default:\/style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->path('default:\\\style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->path('default:///style.less'));

        isSame($path->normalize($f5), $path->path('default:simple/file.txt'));
        isSame($path->normalize($f5), $path->path('default:simple\file.txt'));
        isSame($path->normalize($f5), $path->path('default:simple\\\\file.txt'));
        isSame($path->normalize($f5), $path->path('default:simple////file.txt'));
        isSame($path->normalize($f5), $path->path('default:simple' . DS . 'file.txt'));
        isSame($path->normalize($f5), $path->path('default:\\simple' . DS . 'file.txt'));
        isSame($path->normalize($f5), $path->path('default:\/simple' . DS . 'file.txt'));
        isNull($path->path('alias:/simple' . DS . 'file.txt'));

        $fs->remove($dir1);
    }
}
