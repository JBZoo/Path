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
        $this->_root = FS::clean(__DIR__, '/');

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
        $default = Path::getInstance('default');
        $import  = Path::getInstance('import');
        $export  = Path::getInstance('export');

        $name1 = mt_rand();
        $name2 = mt_rand();
        $name3 = mt_rand();
        $defaultDir = $this->_root . DS . $name1;
        $importDir  = $this->_root . DS . $name2;
        $exportDir  = $this->_root . DS . $name3;

        $fs->mkdir($defaultDir);
        $fs->mkdir($importDir);
        $fs->mkdir($exportDir);

        $default->add($defaultDir, 'defau/lt');
        $import->add($importDir, 'Defau\\l//t');
        $export->add(array(
            $exportDir,
            $importDir,
        ), '()de~~fau+!#$lt');

        isSame($this->_clearPaths($defaultDir), $default->getPaths('default:'));
        isSame($this->_clearPaths($importDir), $import->getPaths('Default:'));

        isSame($this->_clearPaths(array($importDir, $exportDir)), $export->getPaths('default:'));

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
        isSame($current . $name1 . '/file.txt', $default->url('default:file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url('default:\file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url('default:/file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url('default:////file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url('default:\\\\file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url($defaultDir . DS . 'file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url($defaultDir . '///file.txt'));
        isSame($current . $name1 . '/file.txt', $default->url($defaultDir . '\\\\file.txt'));

        isSame($current . $name2 . '/simple.txt', $import->url('Default:simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url('Default:\simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url('Default:/simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url('Default:////simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url('Default:\\\\simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url($importDir . DS . 'simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url($importDir . '///simple.txt'));
        isSame($current . $name2 . '/simple.txt', $import->url($importDir . '\\\\simple.txt'));

        isSame($current . $name3 . '/my-file.txt', $export->url('default:my-file.txt'));
        isSame($current . $name3 . '/my-file.txt', $export->url('default:\my-file.txt'));
        isSame($current . $name3 . '/my-file.txt', $export->url('default:/my-file.txt'));
        isSame($current . $name2 . '/simple.txt', $export->url('default:////simple.txt'));
        isSame($current . $name2 . '/simple.txt', $export->url('default:\\\\simple.txt'));
        isSame($current . $name3 . '/my-file.txt', $export->url($exportDir . DS . 'my-file.txt'));
        isSame($current . $name3 . '/my-file.txt', $export->url($exportDir . '///my-file.txt'));
        isSame($current . $name3 . '/my-file.txt', $export->url($exportDir . '\\\\my-file.txt'));

        $fs->remove(array($defaultDir, $importDir, $exportDir));
    }

    public function testAddAppend()
    {
        $path = Path::getInstance(__METHOD__);

        $name = mt_rand();
        $paths = array(
            $this->_root,
            $this->_root . DS . $name,
            $this->_root . DS . $name,
        );

        $path->add($paths);

        $paths2 = array(
            $this->_root,
            $this->_root . DS . $name
        );
        $path->add($paths2, 'test');

        $expected = array(
            $this->_root . DS . $name,
            $this->_root,
        );

        $defaultPaths = $path->getPaths('default:');
        $testPaths    = $path->getPaths('test:');

        isSame($this->_clearPaths($expected), $testPaths);
        isSame($this->_clearPaths($expected), $defaultPaths);
    }

    public function testAddPrepend()
    {
        $name1 = mt_rand();
        $name2 = mt_rand();

        $path  = Path::getInstance(__METHOD__);
        $paths = array(
            $this->_root . DS . $name1,
            $this->_root,
        );

        $appendPath = $this->_root . DS . $name2;

        $path->add($paths);
        $path->add($appendPath, Path::DEFAULT_ALIAS, Path::MOD_APPEND);

        array_push($paths, $appendPath);

        $expected = array(
            $this->_root,
            $this->_root . DS . $name1,
            $appendPath,
        );

        $package = $path->getPaths('default:');
        isSame($this->_clearPaths($expected), $package);
    }

    public function testAddVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();

        $path->add('default:folder');
        isSame(array(), $path->getPaths('default:'));

        $path->add('alias:folder');
        isSame(array(), $path->getPaths('alias:'));

        $path->add($this->_root);
        isSame($this->_clearPaths($this->_root), $path->getPaths('default:'));

        $path->add('default:virtual-folder');
        isSame($this->_clearPaths($this->_root), $path->getPaths('default:'));

        $newFolder = $this->_root . DS . 'virtual-folder';
        $fs->mkdir($newFolder);

        $path->add('default:virtual-folder');
        isSame($this->_clearPaths(array(
            $this->_root . DS . 'virtual-folder',
            $this->_root,
        )), $path->getPaths('default:'));

        $fs->remove($newFolder);
    }

    public function testAddReset()
    {
        $path = Path::getInstance(__METHOD__);

        $name    = mt_rand();
        $newPath = array(
            $this->_root . DS . $name
        );

        $path->add($this->_root . DS . $name . DS . 'simple');
        $path->add($newPath, Path::DEFAULT_ALIAS, Path::MOD_RESET);

        isSame($this->_clearPaths($newPath), $path->getPaths(Path::DEFAULT_ALIAS));
    }

    /**
     * @expectedException Exception
     */
    public function testRegisterMinLength()
    {
        $path    = Path::getInstance(__METHOD__);
        $path->add($this->_root, '');
        $path->add($this->_root, 'a');
        $path->add($this->_root, 'ab');
    }

    public function testEmptyPaths()
    {
        $path = Path::getInstance(__METHOD__);
        $path->add($this->_paths);

        $packagePaths = $path->getPaths('alias:');
        isSame(array(), $packagePaths);
    }

    public function testIsVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        isTrue($path->isVirtual('alias:'));
        isTrue($path->isVirtual('alias:styles.css'));
        isTrue($path->isVirtual('alias:folder/styles.css'));
    }

    public function testIsNotVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        isFalse($path->isVirtual(__DIR__));
        isFalse($path->isVirtual(dirname(__DIR__)));
        isFalse($path->isVirtual('/folder/file.txt'));
        isFalse($path->isVirtual('alias:/styles.css'));
        isFalse($path->isVirtual('alias:\styles.css'));
    }

    public function testHasPrefix()
    {
        $path = Path::getInstance(__METHOD__);
        $this->assertInternalType('string', $path->prefix(__DIR__));
        $this->assertInternalType('string', $path->prefix(dirname(__DIR__)));
        $this->assertInternalType('string', $path->prefix('P:\\\\Folder\\'));
    }

    public function testNoPrefix()
    {
        $path = Path::getInstance(__METHOD__);
        isNull($path->prefix('folder/file.txt'));
        isNull($path->prefix('./folder/file.txt'));
        isNull($path->prefix('default:folder/file.txt'));
    }

    public function testClean()
    {
        $path = Path::getInstance(__METHOD__);

        isSame(FS::clean(__DIR__, '/'), $path->clean(__DIR__));
        isSame('test/path/folder', $path->clean('../test/path/folder/'));
        isSame('test/path/folder', $path->clean('../../test/path/folder/'));
        isSame('test/path/folder', $path->clean('..\..\test\path\folder\\'));
        isSame('test/path/folder', $path->clean('..\../test///path/\/\folder/\\'));
    }

    public function testPathSuccess()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();

        $name  = mt_rand();
        $paths = array(
            $this->_root . DS . $name,
            $this->_root . DS . $name,
            $this->_root . DS . $name . DS . 'folder',
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

        $path->add($paths);

        isSame($path->clean($f1), $path->get('default:text.txt'));
        isSame($path->clean($f2), $path->get('default:file.pot'));

        isSame($path->clean($dir2 . DS . 'style.less'), $path->get('default:/style.less'));
        isSame($path->clean($dir2 . DS . 'style.less'), $path->get('default:\style.less'));
        isSame($path->clean($dir2 . DS . 'style.less'), $path->get('default:\/style.less'));
        isSame($path->clean($dir2 . DS . 'style.less'), $path->get('default:\\\style.less'));
        isSame($path->clean($dir2 . DS . 'style.less'), $path->get('default:///style.less'));

        isSame($path->clean($f5), $path->get('default:simple/file.txt'));
        isSame($path->clean($f5), $path->get('default:simple\file.txt'));
        isSame($path->clean($f5), $path->get('default:simple\\\\file.txt'));
        isSame($path->clean($f5), $path->get('default:simple////file.txt'));
        isSame($path->clean($f5), $path->get('default:simple' . DS . 'file.txt'));
        isSame($path->clean($f5), $path->get('default:\\simple' . DS . 'file.txt'));
        isSame($path->clean($f5), $path->get('default:\/simple' . DS . 'file.txt'));
        isNull($path->get('alias:/simple' . DS . 'file.txt'));

        isSame(
            $path->clean($symLink . DS . 'test-symlink.txt'),
            $path->get('default:symlink/folder/test-symlink.txt')
        );

        $fs->remove($dir1);
    }

    public function testCheckRemovePaths()
    {
        $path = Path::getInstance(__METHOD__);

        $path->add(array(
            $this->_root,
            $this->_root . DS . 'folder',
            $this->_root . DS . 'folder-2',
            $this->_root . DS . 'folder-3',
            $this->_root . DS . 'folder-4',
            $this->_root . DS . 'folder-5',
            $this->_root . DS . 'folder-6',
        ));

        $path->remove('default:', array(1, 3, 5));
        isSame($this->_clearPaths(array(
            0 => $this->_root . DS . 'folder-6',
            2 => $this->_root . DS . 'folder-4',
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        )), $path->getPaths('default'));

        $path->remove('default:', 0);
        isSame($this->_clearPaths(array(
            2 => $this->_root . DS . 'folder-4',
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        )), $path->getPaths('default'));

        $path->remove('default:', '2');
        isSame($this->_clearPaths(array(
            4 => $this->_root . DS . 'folder-2',
            6 => $this->_root,
        )), $path->getPaths('default'));

        $path->remove('default:', array(4, '6'));
        isEmpty($path->getPaths('default'));
    }

    public function testRemove()
    {
        $path = Path::getInstance(__METHOD__);

        $path->add(array(
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
     * @expectedException \JBZoo\Path\Exception
     */
    public function testSetRootFailed()
    {
        $path = Path::getInstance(__METHOD__);
        $path->setRoot(__DIR__ . DS . mt_rand());
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testGetRootFailed()
    {
        $path = Path::getInstance(__METHOD__);
        $path->getRoot();
    }

    public function testSetRoot()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();
        $dir  = $this->_root . DS . mt_rand();

        $path->setRoot($this->_root);
        isSame($this->_root, $path->getRoot());

        $fs->mkdir($dir);
        $path->setRoot($dir);
        isSame($this->_root, $path->getRoot());
        $fs->remove($dir);
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testNotSetRoot()
    {
        $path = Path::getInstance(__METHOD__);
        $path->add($this->_paths);
        $path->url('default:file.txt');
        $path->url(__DIR__);
    }

    public function testUrl()
    {
        $path = Path::getInstance(__METHOD__);
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
        $path->add($paths);

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
    }

    public function testHasCDBack()
    {
        $path = Path::getInstance(__METHOD__);
        $paths = array(
            $this->_root,
            $this->_root . '/..',
            $this->_root . '/../../',
        );
        $path->add($paths);

        list($path1, $path2, $path3) = $paths;

        $expected = array(
            realpath(FS::clean($path3)),
            realpath(FS::clean($path2)),
            FS::clean($path1, '/'),
        );

        isSame($expected, $path->getPaths('default:'));
    }

    protected function _clearPaths($paths)
    {
        $return = array();
        $paths  = (array) $paths;

        foreach ($paths as $key => $path) {
            $return[$key] = FS::clean($path, '/');
        }

        return $return;
    }
}
