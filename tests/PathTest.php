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
use JBZoo\Utils\FS;
use JBZoo\Path\Exception;
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

        $defaultPaths = $path->getPaths('default:');
        $testPaths    = $path->getPaths('test:');

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

        $package = $path->getPaths('default:');
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

        isSame($newPath, $path->getPaths($path::DEFAULT_PACKAGE));
    }

    public function testEmptyPaths()
    {
        $path = new Path();
        $path->register($this->_paths);

        $packagePaths = $path->getPaths('alias:');
        isSame(array(), $packagePaths);
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
        isFalse($path->isVirtual('alias:/styles.css'));
        isFalse($path->isVirtual('alias:\styles.css'));
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

        //  Symlink folder.
        $symOrigDir = $dir1 . DS . 'sym-dir-orig';
        $symLink    = $dir1 . DS . 'symlink' . DS . 'folder';

        $fs->mkdir($symOrigDir);
        $fs->dumpFile($symOrigDir . DS . 'test-symlink.txt', '');
        $fs->symlink($symOrigDir, $symLink, true);

        $path->register($paths);

        isSame($path->normalize($f1), $path->get('default:text.txt'));
        isSame($path->normalize($f2), $path->get('default:file.pot'));

        isSame($path->normalize($dir2 . DS . 'style.less'), $path->get('default:/style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->get('default:\style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->get('default:\/style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->get('default:\\\style.less'));
        isSame($path->normalize($dir2 . DS . 'style.less'), $path->get('default:///style.less'));

        isSame($path->normalize($f5), $path->get('default:simple/file.txt'));
        isSame($path->normalize($f5), $path->get('default:simple\file.txt'));
        isSame($path->normalize($f5), $path->get('default:simple\\\\file.txt'));
        isSame($path->normalize($f5), $path->get('default:simple////file.txt'));
        isSame($path->normalize($f5), $path->get('default:simple' . DS . 'file.txt'));
        isSame($path->normalize($f5), $path->get('default:\\simple' . DS . 'file.txt'));
        isSame($path->normalize($f5), $path->get('default:\/simple' . DS . 'file.txt'));
        isNull($path->get('alias:/simple' . DS . 'file.txt'));

        isSame(
            $path->normalize($symLink . DS . 'test-symlink.txt'),
            $path->get('default:symlink/folder/test-symlink.txt')
        );

        $fs->remove($dir1);
    }

    public function testCheckRemovePaths()
    {
        $path = new Path();

        $path->register(array(
            $this->_root,
            $this->_root . DS . 'folder',
            $this->_root . DS . 'folder-2',
            $this->_root . DS . 'folder-3',
            $this->_root . DS . 'folder-4',
            $this->_root . DS . 'folder-5',
            $this->_root . DS . 'folder-6',
        ));

        $path->remove('default:', array(1, 3, 5));
        isSame(array(
            0 => $this->_root . DS . 'folder-6',
            2 => $this->_root . DS . 'folder-4',
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        ), $path->getPaths('default'));

        $path->remove('default:', 0);
        isSame(array(
            2 => $this->_root . DS . 'folder-4',
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        ), $path->getPaths('default'));

        $path->remove('default:', '2');
        isSame(array(
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        ), $path->getPaths('default'));

        $path->remove('default:', array(4, '6'));
        isEmpty($path->getPaths('default'));
    }

    public function testRemove()
    {
        $path = new Path();

        $path->register(array(
            $this->_root,
            $this->_root . DS . 'folder',
            $this->_root . DS . 'folder-2',
            $this->_root . DS . 'folder-3',
            $this->_root . DS . 'folder-4',
        ));

        isTrue($path->remove('default:', 1));
        isTrue($path->remove('default:', '3'));
        isTrue($path->remove('default:', array(4.0)));
        isTrue($path->remove('default:', array(0, '2')));
        isFalse($path->remove('default:', array(2)));

        isFalse($path->remove('alias:', array(2)));
        isFalse($path->remove('alias:', array('5', 10, '123')));
    }

    /**
     * @expectedException Exception
     */
    public function testSetRootFailed()
    {
        $path = new Path();
        $path->setRoot(__DIR__ . DS . mt_rand());
    }

    /**
     * @expectedException Exception
     */
    public function testGetRootFailed()
    {
        $path = new Path();
        $path->getRoot();
    }

    public function testSetRoot()
    {
        $path = new Path();
        $fs   = new Filesystem();
        $dir  = __DIR__ . DS . mt_rand();

        $path->setRoot(__DIR__);
        isSame(__DIR__, $path->getRoot());

        $fs->mkdir($dir);
        $path->setRoot($dir);
        isSame(__DIR__, $path->getRoot());
        $fs->remove($dir);
    }

    public function testRelative()
    {
        $path = new Path();
        $fs   = new Filesystem();
        $path->setRoot(__DIR__);

        // Check absolute path to relative.
        isSame('file.txt', $path->relative(__DIR__ . '\/\file.txt'));
        isSame('file.txt', $path->relative(__DIR__ . '\\\\file.txt'));
        isSame('file.txt', $path->relative(__DIR__ . DS . 'file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder\\\\\\file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder\\\\//file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder' . DS . 'file.txt'));

        //  Check virtual path to relative.
        $paths = array(
            __DIR__ . DS . 'folder-1',
            __DIR__ . DS . 'folder-2',
            __DIR__ . DS . 'folder',
        );

        list($dir1, $dir2, $dir3) = $paths;

        $fs->dumpFile($dir1 . DS . 'file1.txt', '');
        $fs->dumpFile($dir2 . DS . 'file2.txt', '');
        $fs->dumpFile($dir3 . DS . 'hello' . DS . 'file3.txt', '');

        $path->register($paths);

        isSame('folder-1/file1.txt', $path->relative('default:file1.txt'));
        isSame('folder-1/file1.txt', $path->relative('default:file1.txt/'));
        isSame('folder-1/file1.txt', $path->relative('default:file1.txt\\'));
        isSame('folder-2/file2.txt', $path->relative('default:/file2.txt'));
        isSame('folder-2/file2.txt', $path->relative('default:\\/file2.txt'));
        isSame('folder/hello/file3.txt', $path->relative('default:hello/file3.txt'));
        isSame('folder/hello/file3.txt', $path->relative('default:/hello/file3.txt'));
        isSame('folder/hello/file3.txt', $path->relative('default:hello////file3.txt'));
        isSame('folder/hello/file3.txt', $path->relative('default:hello\\\\\\file3.txt/'));

        $fs->remove(array($dir1, $dir2, $dir3));
    }
}
