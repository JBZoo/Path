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
 * @author    Sergey Kalistratov <kalistratov.s.m@gmail.com>
 */

namespace JBZoo\PHPUnit;

use JBZoo\Path\Path;
use JBZoo\Utils\FS;
use JBZoo\Utils\Url;
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

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testInvalidInstance()
    {
        Path::getInstance(false);
        Path::getInstance(true);
        Path::getInstance('');
    }

    public function testInstance()
    {
        $fs      = new Filesystem();
        $default = Path::getInstance();
        $import  = Path::getInstance('import');
        $export  = Path::getInstance('export');

        $defaultDir = $this->_root . DS . 'simple-folder';
        $importDir  = $this->_root . DS . 'import';
        $exportDir  = $this->_root . DS . 'export';

        $fs->mkdir($defaultDir);
        $fs->mkdir($importDir);
        $fs->mkdir($exportDir);

        $default->register($defaultDir);
        $import->register($importDir);
        $export->register(array(
            $exportDir,
            $importDir,
        ));

        isSame(array($defaultDir), $default->getPaths('default:'));
        isSame(array($importDir), $import->getPaths('default:'));
        isSame(array($importDir, $exportDir), $export->getPaths('default:'));
        isSame(array('default', 'import', 'export'), $default->getInstanceKeys());

        $_SERVER['HTTP_HOST']   = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/';

        $fs->dumpFile($defaultDir . DS . 'file.txt', '');
        $fs->dumpFile($importDir . DS . 'simple.txt', '');
        $fs->dumpFile($exportDir . DS . 'my-file.txt', '');

        $default->setRoot($this->_root);
        $import->setRoot($this->_root);
        $export->setRoot($this->_root);

        $current = Url::current();
        isSame($current . 'simple-folder/file.txt', $default->url('default:file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url('default:\file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url('default:/file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url('default:////file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url('default:\\\\file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url($defaultDir . DS . 'file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url($defaultDir . '///file.txt'));
        isSame($current . 'simple-folder/file.txt', $default->url($defaultDir . '\\\\file.txt'));

        isSame($current . 'import/simple.txt', $import->url('default:simple.txt'));
        isSame($current . 'import/simple.txt', $import->url('default:\simple.txt'));
        isSame($current . 'import/simple.txt', $import->url('default:/simple.txt'));
        isSame($current . 'import/simple.txt', $import->url('default:////simple.txt'));
        isSame($current . 'import/simple.txt', $import->url('default:\\\\simple.txt'));
        isSame($current . 'import/simple.txt', $import->url($importDir . DS . 'simple.txt'));
        isSame($current . 'import/simple.txt', $import->url($importDir . '///simple.txt'));
        isSame($current . 'import/simple.txt', $import->url($importDir . '\\\\simple.txt'));

        isSame($current . 'export/my-file.txt', $export->url('default:my-file.txt'));
        isSame($current . 'export/my-file.txt', $export->url('default:\my-file.txt'));
        isSame($current . 'export/my-file.txt', $export->url('default:/my-file.txt'));
        isSame($current . 'import/simple.txt', $export->url('default:////simple.txt'));
        isSame($current . 'import/simple.txt', $export->url('default:\\\\simple.txt'));
        isSame($current . 'export/my-file.txt', $export->url($exportDir . DS . 'my-file.txt'));
        isSame($current . 'export/my-file.txt', $export->url($exportDir . '///my-file.txt'));
        isSame($current . 'export/my-file.txt', $export->url($exportDir . '\\\\my-file.txt'));

        $fs->remove(array($defaultDir, $importDir, $exportDir));

        Path::removeInstance();
        Path::removeInstance('import');
        Path::removeInstance('export');
    }

    public function testRegisterAppend()
    {
        $path = Path::getInstance();

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
        Path::removeInstance();
    }

    public function testRegisterPrepend()
    {
        $path  = Path::getInstance();
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
        Path::removeInstance();
    }

    public function testRegisterVirtual()
    {
        $path = Path::getInstance();
        $fs   = new Filesystem();

        $path->register('default:folder');
        isSame(array(), $path->getPaths('default:'));

        $path->register('alias:folder');
        isSame(array(), $path->getPaths('alias:'));

        $path->register($this->_root);
        isSame(array($this->_root), $path->getPaths('default:'));

        $path->register('default:virtual-folder');
        isSame(array($this->_root), $path->getPaths('default:'));

        $newFolder = $this->_root . DS . 'virtual-folder';
        $fs->mkdir($newFolder);

        $path->register('default:virtual-folder');
        isSame(array(
            FS::clean($this->_root . DS . 'virtual-folder', '/'),
            $this->_root,
        ), $path->getPaths('default:'));

        $fs->remove($newFolder);
        Path::removeInstance();
    }

    public function testRegisterReset()
    {
        $path    = Path::getInstance();
        $newPath = array(
            $this->_root . DS . 'new-folder'
        );

        $path->register($this->_paths);
        $path->register($newPath, Path::DEFAULT_PACKAGE, Path::RESET);

        isSame($newPath, $path->getPaths(Path::DEFAULT_PACKAGE));
        Path::removeInstance();
    }

    /**
     * @expectedException Exception
     */
    public function testRegisterMinLength()
    {
        $path    = Path::getInstance();
        $path->register($this->_root, '');
        $path->register($this->_root, 'a');
        $path->register($this->_root, 'ab');
        Path::removeInstance();
    }

    public function testEmptyPaths()
    {
        $path = Path::getInstance();
        $path->register($this->_paths);

        $packagePaths = $path->getPaths('alias:');
        isSame(array(), $packagePaths);
        Path::removeInstance();
    }

    public function testIsVirtual()
    {
        $path = Path::getInstance();
        isTrue($path->isVirtual('alias:'));
        isTrue($path->isVirtual('alias:styles.css'));
        isTrue($path->isVirtual('alias:folder/styles.css'));
        Path::removeInstance();
    }

    public function testIsNotVirtual()
    {
        $path = Path::getInstance();
        isFalse($path->isVirtual(__DIR__));
        isFalse($path->isVirtual(dirname(__DIR__)));
        isFalse($path->isVirtual('/folder/file.txt'));
        isFalse($path->isVirtual('alias:/styles.css'));
        isFalse($path->isVirtual('alias:\styles.css'));
        Path::removeInstance();
    }

    public function testHasPrefix()
    {
        $path = Path::getInstance();
        $this->assertInternalType('string', $path->prefix(__DIR__));
        $this->assertInternalType('string', $path->prefix(dirname(__DIR__)));
        $this->assertInternalType('string', $path->prefix('P:\\\\Folder\\'));
        Path::removeInstance();
    }

    public function testNoPrefix()
    {
        $path = Path::getInstance();
        isNull($path->prefix('folder/file.txt'));
        isNull($path->prefix('./folder/file.txt'));
        isNull($path->prefix('default:folder/file.txt'));
        Path::removeInstance();
    }

    public function testParseVirtual()
    {
        $path = Path::getInstance();

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
        Path::removeInstance();
    }

    public function testNormalize()
    {
        $path = Path::getInstance();

        isSame(FS::clean(__DIR__, '/'), $path->normalize(__DIR__));
        isSame('test/path/folder', $path->normalize('../test/path/folder/'));
        isSame('test/path/folder', $path->normalize('../../test/path/folder/'));
        isSame('test/path/folder', $path->normalize('..\..\test\path\folder\\'));
        isSame('test/path/folder', $path->normalize('..\../test///path/\/\folder/\\'));
        Path::removeInstance();
    }

    public function testPathSuccess()
    {
        $path = Path::getInstance();
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
        Path::removeInstance();
    }

    public function testCheckRemovePaths()
    {
        $path = Path::getInstance();

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
        Path::removeInstance();
    }

    public function testRemove()
    {
        $path = Path::getInstance();

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
        Path::removeInstance();
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testSetRootFailed()
    {
        $path = Path::getInstance();
        $path->setRoot(__DIR__ . DS . mt_rand());
        Path::removeInstance();
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testGetRootFailed()
    {
        $path = Path::getInstance();
        $path->getRoot();
        Path::removeInstance();
    }

    public function testSetRoot()
    {
        $path = Path::getInstance();
        $fs   = new Filesystem();
        $dir  = __DIR__ . DS . mt_rand();

        $path->setRoot(__DIR__);
        isSame(__DIR__, $path->getRoot());

        $fs->mkdir($dir);
        $path->setRoot($dir);
        isSame(__DIR__, $path->getRoot());
        $fs->remove($dir);
        Path::removeInstance();
    }

    public function testRelative()
    {
        $path = Path::getInstance();
        $fs   = new Filesystem();
        $path->setRoot(__DIR__);

        // Check absolute path to relative.
        isSame('file.txt', $path->relative(__DIR__ . '\/\file.txt'));
        isSame('file.txt', $path->relative(__DIR__ . '\\\\file.txt'));
        isSame('file.txt', $path->relative(__DIR__ . DS . 'file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder\\\\\\file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder\\\\//file.txt'));
        isSame('folder/file.txt', $path->relative(__DIR__ . DS . 'folder' . DS . 'file.txt'));

        isEmpty($path->relative(__DIR__ . '\/\file.txt', true));
        isEmpty($path->relative(__DIR__ . '\\\\file.txt', true));

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
        Path::removeInstance();
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testRelativeFail()
    {
        $path = Path::getInstance();
        $path->register($this->_paths);
        $path->relative('default:file.txt');
        $path->relative(__DIR__);
        Path::removeInstance();
    }

    public function testUrl()
    {
        $path = Path::getInstance();
        $fs   = new Filesystem();

        $_SERVER['HTTP_HOST']   = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/';

        $paths = array(
            $this->_root . DS . 'my-folder',
            $this->_root . DS . 'my-folder2' . DS . 'dir',
            $this->_root,
        );

        foreach ($paths as $key => $p) {
            $fs->mkdir($p);
            $fs->dumpFile($p . DS . 'file' . $key . '.txt', '');
        }

        $fs->dumpFile($this->_root . DS . 'my-folder2' . DS . 'my-file.txt', '');

        list($path1, $path2) = $paths;

        $path->setRoot($this->_root);
        $path->register($paths);

        $current = Url::current();

        $file1 = $current . 'my-folder2/dir/file1.txt';
        $file2 = $current . 'my-folder/file0.txt';
        $file3 = $current . 'my-folder2/my-file.txt';

        isSame($file1, $path->url('default:file1.txt'));
        isSame($file3, $path->url('default:my-folder2/my-file.txt'));
        isSame($file3, $path->url('default:my-folder2\\\\my-file.txt'));
        isSame($file3, $path->url('default:\my-folder2\my-file.txt'));

        isSame($file1, $path->url($path2 . DS . 'file1.txt'));
        isSame($file2, $path->url($path1 . DS . 'file0.txt'));
        isSame($file2, $path->url($path1 . '/file0.txt'));
        isSame($file3, $path->url($this->_root . '\my-folder2\my-file.txt'));
        isSame($file3, $path->url($this->_root . '/my-folder2////my-file.txt'));
        isSame($file3, $path->url($this->_root . DS . 'my-folder2' . DS . 'my-file.txt'));

        isSame($file2 . '?data=test&value=hello', $path->url($path1 . DS . 'file0.txt?data=test&value=hello'));

        isNull($path->url('default:file.txt'));
        isNull($path->url('alias:file.txt'));

        isNull($path->url($this->_root . DS . 'my-folder2' . DS . 'file.txt'));
        isNull($path->url($this->_root . 'my/' . DS . 'file.txt'));

        $fs->remove(array(
            $path1, $path2,
            $this->_root . DS . 'my-folder2',
            $this->_root . DS . 'file2.txt',
        ));

        Path::removeInstance();
    }
}
