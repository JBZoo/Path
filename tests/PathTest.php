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
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class PathTest extends PHPUnit
{
    protected $_root;

    protected $_paths = array();

    /**
     * @param $paths
     * @return array
     */
    protected function _clr($paths)
    {
        $return = array();
        $paths  = (array)$paths;

        foreach ($paths as $key => $path) {
            $return[$key] = FS::clean($path, '/');
        }

        return $return;
    }

    /**
     * Normilize slashes and compare paths
     *
     * @param $expected
     * @param $actual
     */
    protected function _is($expected, $actual)
    {
        isSame($this->_clr($expected), $this->_clr($actual));
    }

    public function setUp()
    {
        $this->_root = FS::clean(__DIR__ . '/test', '/');
        FS::rmdir($this->_root);

        mkdir($this->_root, 0777, true);

        $this->_paths = array(
            $this->_root,
            $this->_root . DS . 'folder',
        );
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testInvalidInstance()
    {
        Path::getInstance('');
    }

    public function testCreateInstance()
    {
        isClass('\JBZoo\Path\Path', Path::getInstance());
        isClass('\JBZoo\Path\Path', new Path());
    }

    public function testGeneral()
    {
        $fs      = new Filesystem();
        $default = Path::getInstance('default');
        $import  = Path::getInstance('import');
        $export  = Path::getInstance('export');

        $name1      = mt_rand();
        $name2      = mt_rand();
        $name3      = mt_rand();
        $defaultDir = $this->_root . DS . $name1;
        $importDir  = $this->_root . DS . $name2;
        $exportDir  = $this->_root . DS . $name3;

        $fs->mkdir($defaultDir);
        $fs->mkdir($importDir);
        $fs->mkdir($exportDir);

        $default->set('defau/lt', $defaultDir);
        $import->set('Defau\\l//t', $importDir);
        $export->set('()de~~fau+!#$lt', array(
            $exportDir,
            $importDir,
        ));

        $this->_is($defaultDir, $default->getPaths('default'));
        $this->_is($importDir, $import->getPaths('Default'));

        $this->_is(array($importDir, $exportDir), $export->getPaths('default:'));

        isSame(array('default', 'import', 'export'), $default->getInstanceKeys());

        $_SERVER['HTTP_HOST']   = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/page';

        $fs->dumpFile($defaultDir . DS . 'file.txt', '');
        $fs->dumpFile($importDir . DS . 'simple.txt', '');
        $fs->dumpFile($exportDir . DS . 'my-file.txt', '');

        $default->setRoot($this->_root);
        $import->setRoot($this->_root);
        $export->setRoot($this->_root);

        $current = Url::root() . '/';
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

        isSame($current . $name3 . '/my-file.txt?ver=123', $export->url($exportDir . '\\\\my-file.txt?ver=123'));

        $fs->remove(array($defaultDir, $importDir, $exportDir));
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testAddLoopedPath()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('default', 'default:');
    }

    public function testResolveAnyPaths()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = array(
            // Normal
            $this->_root,
            $this->_root . '/..',
            $this->_root . '/../..',

            // Virt paths
            'root:',
            'root:..',
            'root:../..',
            'root:/../..',
            'root:/../test',

            // Undefined
            $this->_root . '/' . mt_rand(),
            'root:test',
            'root:undefined',
            'undefined:',
            'undefined:undefined',
        );

        $path->set('default', $paths, Path::MOD_APPEND);
        $path->set('root', $this->_root);

        $expected = array(
            realpath($this->_root),
            realpath($this->_root . '/..'),
            realpath($this->_root . '/../..'),
            realpath($this->_root),
            realpath($this->_root . '/..'),
            realpath($this->_root . '/../..'),
            realpath($this->_root . '/../test'),
        );

        $this->_is($expected, $path->getPaths('default'));
    }

    public function testSetAppend()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = array(
            $this->_root,
            $this->_root . '/..',
        );

        $appendPath = $this->_root . '/../..';

        $path->set('default', $paths, Path::MOD_APPEND);
        $path->set('default', $appendPath, Path::MOD_APPEND);

        $expected = array(
            realpath($this->_root),
            realpath($this->_root . '/..'),
            realpath($this->_root . '/../..'),
        );

        $this->_is($expected, $path->getPaths('default'));
        $this->_is($expected, $path->getPaths('default:'));
    }

    public function testSetPrepend()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = array(
            $this->_root,
            $this->_root . '/..',
        );

        $appendPath = $this->_root . '/../..';

        $path->set('default', $paths);
        $path->set('default', $appendPath, Path::MOD_PREPEND);

        $expected = array(
            realpath($appendPath),
            realpath($this->_root . '/..'),
            realpath($this->_root),
        );

        $this->_is($expected, $path->getPaths('default'));
        $this->_is($expected, $path->getPaths('default:'));
    }

    public function testSetVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();

        $path->set('default', 'undefined:folder');
        isSame(array(), $path->getPaths('default'));

        $path->set('default', 'alias:folder');
        isSame(array(), $path->getPaths('alias'));

        $path->set('default', $this->_root);
        $this->_is($this->_root, $path->getPaths('default'));

        $path->set('default', 'undefined:virtual-folder');
        $this->_is($this->_root, $path->getPaths('default'));

        $newFolder = $this->_root . DS . 'virtual-folder';
        $fs->mkdir($newFolder);

        $path->set('default', 'undefined:virtual-folder');
        $this->_is(array(
            $this->_root,
        ), $path->getPaths('default:'));

        $fs->remove($newFolder);
    }

    public function testSetReset()
    {
        $path = Path::getInstance(__METHOD__);

        $newPath = $this->_root . '/..';

        $path->set('default', $this->_root . '/../..');
        $path->set('default', $newPath, Path::MOD_RESET);

        $this->_is(realpath($newPath), $path->getPaths('default'));
    }

    /**
     * @expectedException Exception
     */
    public function testRegisterMinLength()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('a', $this->_root);
    }

    /**
     * @expectedException Exception
     */
    public function testRegisterEmptyKey()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set(false, $this->_root);
    }

    public function testEmptyPaths()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('default', $this->_paths);

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

        $path->set('default', $paths);

        $this->_is($f1, $path->get('default:text.txt'));
        $this->_is($f2, $path->get('default:file.pot'));

        $this->_is($dir2 . DS . 'style.less', $path->get('default:/style.less'));
        $this->_is($dir2 . DS . 'style.less', $path->get('default:\style.less'));
        $this->_is($dir2 . DS . 'style.less', $path->get('default:\/style.less'));
        $this->_is($dir2 . DS . 'style.less', $path->get('default:\\\style.less'));
        $this->_is($dir2 . DS . 'style.less', $path->get('default:///style.less'));

        $this->_is($f5, $path->get('default:simple/file.txt'));
        $this->_is($f5, $path->get('default:simple\file.txt'));
        $this->_is($f5, $path->get('default:simple\\\\file.txt'));
        $this->_is($f5, $path->get('default:simple////file.txt'));
        $this->_is($f5, $path->get('default:simple' . DS . 'file.txt'));
        $this->_is($f5, $path->get('default:\\simple' . DS . 'file.txt'));
        $this->_is($f5, $path->get('default:\/simple' . DS . 'file.txt'));

        isNull($path->get('alias:/simple' . DS . 'file.txt'));

        $this->_is(
            $symLink . DS . 'test-symlink.txt',
            $path->get('default:symlink/folder/test-symlink.txt')
        );

        $fs->remove($dir1);
    }

    public function testRemove()
    {
        $path = Path::getInstance(__METHOD__);

        $path->set('default', array(
            $this->_root,
            $this->_root . '/..',
            $this->_root . '/../..',
        ), Path::MOD_PREPEND);

        isTrue($path->remove('default', $this->_root));

        $this->_is(array(
            realpath($this->_root . '/../..'),
            realpath($this->_root . '/..'),
        ), $path->getPaths('default'));

        isFalse($path->remove('default', realpath($this->_root . '/../../src')));
        isFalse($path->remove('default', $this->_root . '/../../src'));

        $this->_is(array(
            realpath($this->_root . '/../..'),
            realpath($this->_root . '/..'),
        ), $path->getPaths('default'));

        $removedPaths = array(
            $this->_root . '\\..\\..',
            $this->_root . '////..',
        );
        isTrue($path->remove('default', $removedPaths));

        $this->_is(array(), $path->getPaths('default'));
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testSetRootFailed()
    {
        $path = Path::getInstance(__METHOD__);
        $path->setRoot($this->_root . DS . mt_rand());
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
        $this->_is($this->_root, $path->getRoot());

        $fs->mkdir($dir);
        $path->setRoot($dir);
        $this->_is($this->_root, $path->getRoot());
        $fs->remove($dir);
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testNotSetRoot()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('default', $this->_paths);
        $path->url(__DIR__);
    }

    /**
     * @expectedException \JBZoo\Path\Exception
     */
    public function testNotSetRootVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('default', $this->_paths);
        $path->url('default:file.txt');
    }

    public function testShortUrl()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();

        $_SERVER['HTTP_HOST']   = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/build';

        $dir = $this->_root . DS . 'short';
        $fs->dumpFile($dir . DS . 'file.txt', '');

        $path->setRoot($this->_root);
        $path->set('default', $dir);

        $this->_is('/short/file.txt', $path->url('default:file.txt', false));

        $fs->remove($dir);
    }

    public function testFullUrl()
    {
        $path = Path::getInstance(__METHOD__);
        $fs   = new Filesystem();

        $_SERVER['HTTP_HOST']   = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/custom';

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
        $path->set('default', $paths);

        $current = Url::root() . '/';

        $file1 = $current . 'my-folder2/dir/file1.txt';
        $file2 = $current . 'my-folder/file0.txt';
        $file3 = $current . 'my-folder2/my-file.txt';

        $this->_is($file1, $path->url('default:file1.txt'));
        $this->_is($file3, $path->url('default:my-folder2/my-file.txt'));
        $this->_is($file3, $path->url('default:my-folder2\\\\my-file.txt'));
        $this->_is($file3, $path->url('default:\my-folder2\my-file.txt'));

        $this->_is($file1, $path->url($path2 . DS . 'file1.txt'));
        $this->_is($file2, $path->url($path1 . DS . 'file0.txt'));
        $this->_is($file2, $path->url($path1 . '/file0.txt'));
        $this->_is($file3, $path->url($this->_root . '\my-folder2\my-file.txt'));
        $this->_is($file3, $path->url($this->_root . '/my-folder2////my-file.txt'));
        $this->_is($file3, $path->url($this->_root . DS . 'my-folder2' . DS . 'my-file.txt'));

        $this->_is($file2 . '?data=test&value=hello', $path->url($path1 . DS . 'file0.txt?data=test&value=hello'));

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
        $path  = Path::getInstance(__METHOD__);
        $paths = array(
            $this->_root,
            $this->_root . '/..',
            $this->_root . '/../../',
        );
        $path->set('default', $paths);

        list($path1, $path2, $path3) = $paths;

        $expected = array(
            realpath(FS::clean($path3)),
            realpath(FS::clean($path2)),
            FS::clean($path1, '/'),
        );

        $this->_is($expected, $path->getPaths('default'));
    }

    public function testDeprecated_add()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = array(
            $this->_root,
            $this->_root . '/..',
        );

        $path->add($paths, 'default', Path::MOD_PREPEND);

        $paths2 = array(
            $this->_root . '/..',
            $this->_root,
        );
        $path->add($paths2, 'test', Path::MOD_APPEND);

        $expected = array(
            realpath($this->_root . '/..'),
            $this->_root,
        );

        $this->_is($expected, $path->getPaths('default:'));
        $this->_is($expected, $path->getPaths('test:'));
    }

    public function testGlob()
    {
        $path = new Path();

        $path->set('root', __DIR__ . '/..');
        $path->set('src', 'root:src');

        $paths = $path->glob('root:src/*.php');

        $this->_is(array(
            PROJECT_ROOT . '/src/Exception.php',
            PROJECT_ROOT . '/src/Path.php',
        ), $paths);
    }

    public function testRelative()
    {
        $path = new Path();

        $path->setRoot(__DIR__ . '/..');
        $path->set('root', __DIR__ . '/..');
        $path->set('src', 'root:src');

        $actual = $path->rel('root:src/Path.php');
        $this->_is('src/Path.php', $actual);
    }

    public function testRelativeGlob()
    {
        $path = new Path();

        $path->setRoot(__DIR__ . '/..');
        $path->set('root', __DIR__ . '/..');
        $path->set('src', 'root:src');

        $paths = $path->relGlob('root:src/*.php');

        $this->_is(array(
            'src/Exception.php',
            'src/Path.php',
        ), $paths);
    }
}
