<?php
/**
 * JBZoo Path
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Path
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/Path"
 * @author     Sergey Kalistratov <kalistratov.s.m@gmail.com>
 */

namespace JBZoo\PHPUnit;

use JBZoo\Path\Exception;
use JBZoo\Path\Path;
use JBZoo\Utils\FS;
use JBZoo\Utils\Sys;
use JBZoo\Utils\Url;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PathTest
 *
 * @package JBZoo\PHPUnit
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class PathTest extends PHPUnit
{
    protected $root;

    protected $paths = [];

    /**
     * @param $paths
     * @return array
     */
    protected function clr($paths)
    {
        $return = [];
        $paths = (array)$paths;

        foreach ($paths as $key => $path) {
            $return[$key] = FS::clean($path, '/');
        }

        return $return;
    }

    /**
     * Normalize slashes and compare paths
     *
     * @param $expected
     * @param $actual
     */
    protected function is($expected, $actual)
    {
        isSame($this->clr($expected), $this->clr($actual));
    }

    protected function setUp(): void
    {
        $this->root = FS::clean(__DIR__ . '/test', '/');
        FS::rmdir($this->root);

        mkdir($this->root, 0777, true);

        $this->paths = [
            $this->root,
            $this->root . DS . 'folder',
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $filesystem = new Filesystem();
        $filesystem->remove($this->root);
    }

    public function testInvalidInstance()
    {
        $this->expectException(Exception::class);

        Path::getInstance('');
    }

    public function testCreateInstance()
    {
        isClass(Path::class, Path::getInstance());
        isClass(Path::class, new Path());
    }

    public function testGeneral()
    {
        $filesystem = new Filesystem();
        $default = Path::getInstance('default');
        $import = Path::getInstance('import');
        $export = Path::getInstance('export');

        $name1 = mt_rand();
        $name2 = mt_rand();
        $name3 = mt_rand();
        $defaultDir = $this->root . DS . $name1;
        $importDir = $this->root . DS . $name2;
        $exportDir = $this->root . DS . $name3;

        $filesystem->mkdir($defaultDir);
        $filesystem->mkdir($importDir);
        $filesystem->mkdir($exportDir);

        $default->set('defau/lt', $defaultDir);
        $import->set('Defau\\l//t', $importDir);
        $export->set('()de~~fau+!#$lt', [
            $exportDir,
            $importDir,
        ]);

        $this->is($defaultDir, $default->getPaths('default'));
        $this->is($importDir, $import->getPaths('Default'));

        $this->is([$importDir, $exportDir], $export->getPaths('default:'));

        isSame(['default', 'import', 'export'], $default->getInstanceKeys());

        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/page';

        $filesystem->dumpFile($defaultDir . DS . 'file.txt', '');
        $filesystem->dumpFile($importDir . DS . 'simple.txt', '');
        $filesystem->dumpFile($exportDir . DS . 'my-file.txt', '');

        $default->setRoot($this->root);
        $import->setRoot($this->root);
        $export->setRoot($this->root);

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

        $filesystem->remove([$defaultDir, $importDir, $exportDir]);
    }

    public function testAddLoopedPath()
    {
        $this->expectException(Exception::class);

        $path = Path::getInstance(__METHOD__);
        $path->set('default', 'default:');
    }

    public function testSetRootAlias()
    {
        $this->expectException(Exception::class);

        $path = new Path();
        $path->set('root', $this->root);
    }

    public function testResolveAnyPaths()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = [
            // Normal
            $this->root,
            $this->root . '/..',
            $this->root . '/../..',

            // Virt paths
            'somepath:',
            'somepath:..',
            'somepath:../..',
            'somepath:/../..',
            'somepath:/../test',

            // Undefined
            $this->root . '/' . mt_rand(),
            'somepath:test',
            'somepath:undefined',
            'undefined:',
            'undefined:undefined',
        ];

        $path->set('default', $paths, Path::MOD_APPEND);
        $path->set('somepath', $this->root);

        $expected = [
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
            realpath($this->root . '/../test'),
        ];

        $this->is($expected, $path->getPaths('default'));
    }

    public function testSetAppend()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = [
            $this->root,
            $this->root . '/..',
        ];

        $appendPath = $this->root . '/../..';

        $path->set('default', $paths, Path::MOD_APPEND);
        $path->set('default', $appendPath, Path::MOD_APPEND);

        $expected = [
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
        ];

        $this->is($expected, $path->getPaths('default'));
        $this->is($expected, $path->getPaths('default:'));
    }

    public function testSetPrepend()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = [
            $this->root,
            $this->root . '/..',
        ];

        $appendPath = $this->root . '/../..';

        $path->set('default', $paths);
        $path->set('default', $appendPath, Path::MOD_PREPEND);

        $expected = [
            realpath($appendPath),
            realpath($this->root . '/..'),
            realpath($this->root),
        ];

        $this->is($expected, $path->getPaths('default'));
        $this->is($expected, $path->getPaths('default:'));
    }

    public function testSetVirtual()
    {
        $path = Path::getInstance(__METHOD__);
        $fs = new Filesystem();

        $path->set('default', 'undefined:folder');
        isSame([], $path->getPaths('default'));

        $path->set('default', 'alias:folder');
        isSame([], $path->getPaths('alias'));

        $path->set('default', $this->root);
        $this->is($this->root, $path->getPaths('default'));

        $path->set('default', 'undefined:virtual-folder');
        $this->is($this->root, $path->getPaths('default'));

        $newFolder = $this->root . DS . 'virtual-folder';
        $fs->mkdir($newFolder);

        $path->set('default', 'undefined:virtual-folder');
        $this->is([
            $this->root,
        ], $path->getPaths('default:'));

        $fs->remove($newFolder);
    }

    public function testSetReset()
    {
        $path = Path::getInstance(__METHOD__);

        $newPath = $this->root . '/..';

        $path->set('default', $this->root . '/../..');
        $path->set('default', $newPath, Path::MOD_RESET);

        $this->is(realpath($newPath), $path->getPaths('default'));
    }

    public function testRegisterMinLength()
    {
        $this->expectException(Exception::class);

        $path = Path::getInstance(__METHOD__);
        $path->set('a', $this->root);
    }

    public function testRegisterEmptyKey()
    {
        $this->expectException(Exception::class);

        $path = Path::getInstance(__METHOD__);
        $path->set(false, $this->root);
    }

    public function testEmptyPaths()
    {
        $path = Path::getInstance(__METHOD__);
        $path->set('default', $this->paths);

        $packagePaths = $path->getPaths('alias:');
        isSame([], $packagePaths);
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
        $this->assertIsString($path->prefix(__DIR__));
        $this->assertIsString($path->prefix(dirname(__DIR__)));
        $this->assertIsString($path->prefix('P:\\\\Folder\\'));
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
        $filesystem = new Filesystem();

        $name = mt_rand();
        $paths = [
            $this->root . DS . $name,
            $this->root . DS . $name,
            $this->root . DS . $name . DS . 'folder',
        ];

        list($dir1, $dir2) = $paths;

        $filesystem->mkdir($dir2);

        $_dir = $dir2 . DS . 'simple';

        $filesystem->mkdir($_dir);

        $f1 = $dir2 . DS . 'text.txt';
        $f2 = $dir2 . DS . 'file.pot';
        $f3 = $dir1 . DS . 'style.less';
        $f4 = $dir2 . DS . 'style.less';
        $f5 = $_dir . DS . 'file.txt';

        $filesystem->dumpFile($f1, '');
        $filesystem->dumpFile($f2, '');
        $filesystem->dumpFile($f3, '');
        $filesystem->dumpFile($f4, '');
        $filesystem->dumpFile($f5, '');

        //  Symlink folder.
        $symOrigDir = $dir1 . DS . 'sym-dir-orig';
        $symLink = $dir1 . DS . 'symlink' . DS . 'folder';

        $filesystem->mkdir($symOrigDir);
        $filesystem->dumpFile($symOrigDir . DS . 'test-symlink.txt', '');
        $filesystem->symlink($symOrigDir, $symLink, true);

        $path->set('default', $paths);

        $this->is($f1, $path->get('default:text.txt'));
        $this->is($f2, $path->get('default:file.pot'));

        $this->is($dir2 . DS . 'style.less', $path->get('default:/style.less'));
        $this->is($dir2 . DS . 'style.less', $path->get('default:\style.less'));
        $this->is($dir2 . DS . 'style.less', $path->get('default:\/style.less'));
        $this->is($dir2 . DS . 'style.less', $path->get('default:\\\style.less'));
        $this->is($dir2 . DS . 'style.less', $path->get('default:///style.less'));

        $this->is($f5, $path->get('default:simple/file.txt'));
        $this->is($f5, $path->get('default:simple\file.txt'));
        $this->is($f5, $path->get('default:simple\\\\file.txt'));
        $this->is($f5, $path->get('default:simple////file.txt'));
        $this->is($f5, $path->get('default:simple' . DS . 'file.txt'));
        $this->is($f5, $path->get('default:\\simple' . DS . 'file.txt'));
        $this->is($f5, $path->get('default:\/simple' . DS . 'file.txt'));

        isNull($path->get('alias:/simple' . DS . 'file.txt'));

        $this->is(
            $symLink . DS . 'test-symlink.txt',
            $path->get('default:symlink/folder/test-symlink.txt')
        );

        $filesystem->remove($dir1);
    }

    public function testRemove()
    {
        $path = Path::getInstance(__METHOD__);

        $path->set('default', [
            $this->root,
            $this->root . '/..',
            $this->root . '/../..',
        ], Path::MOD_PREPEND);

        isTrue($path->remove('default', $this->root));

        $this->is([
            realpath($this->root . '/../..'),
            realpath($this->root . '/..'),
        ], $path->getPaths('default'));

        isFalse($path->remove('default', realpath($this->root . '/../../src')));
        isFalse($path->remove('default', $this->root . '/../../src'));

        $this->is([
            realpath($this->root . '/../..'),
            realpath($this->root . '/..'),
        ], $path->getPaths('default'));

        $removedPaths = [
            $this->root . '\\..\\..',
            $this->root . '////..',
        ];
        isTrue($path->remove('default', $removedPaths));

        $this->is([], $path->getPaths('default'));
    }

    public function testSetRootFailed()
    {
        $this->expectException(Exception::class);

        $path = Path::getInstance(__METHOD__);
        $path->setRoot($this->root . DS . mt_rand());
    }

    public function testSetRoot()
    {
        $path = Path::getInstance(__METHOD__);
        $fs = new Filesystem();
        $dir = $this->root . DS . mt_rand();

        $path->setRoot($this->root);
        $this->is($this->root, $path->getRoot());

        $fs->mkdir($dir);
        $path->setRoot($dir);
        $this->is($dir, $path->getRoot());
        $fs->remove($dir);
    }

    public function testShortUrl()
    {
        $path = Path::getInstance(__METHOD__);
        $fs = new Filesystem();

        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/build';

        $dir = $this->root . DS . 'short';
        $fs->dumpFile($dir . DS . 'file.txt', '');

        $path->setRoot($this->root);
        $path->set('default', $dir);

        $this->is('/short/file.txt', $path->url('default:file.txt', false));

        $fs->remove($dir);
    }

    public function testFullUrl()
    {
        $path = Path::getInstance(__METHOD__);
        $fs = new Filesystem();

        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/custom';

        $paths = [
            $this->root . DS . 'my-folder',
            $this->root . DS . 'my-folder2' . DS . 'dir',
            $this->root,
        ];

        foreach ($paths as $key => $p) {
            $fs->mkdir($p);
            $fs->dumpFile($p . DS . 'file' . $key . '.txt', '');
        }

        $fs->dumpFile($this->root . DS . 'my-folder2' . DS . 'my-file.txt', '');

        [$path1, $path2] = $paths;

        $path->setRoot($this->root);
        $path->set('default', $paths);

        $current = Url::root() . '/';

        $file1 = $current . 'my-folder2/dir/file1.txt';
        $file2 = $current . 'my-folder/file0.txt';
        $file3 = $current . 'my-folder2/my-file.txt';

        $this->is($file1, $path->url('default:file1.txt'));
        $this->is($file3, $path->url('default:my-folder2/my-file.txt'));
        $this->is($file3, $path->url('default:my-folder2\\\\my-file.txt'));
        $this->is($file3, $path->url('default:\my-folder2\my-file.txt'));

        $this->is($file1, $path->url($path2 . DS . 'file1.txt'));
        $this->is($file2, $path->url($path1 . DS . 'file0.txt'));
        $this->is($file2, $path->url($path1 . '/file0.txt'));
        $this->is($file3, $path->url($this->root . '\my-folder2\my-file.txt'));
        $this->is($file3, $path->url($this->root . '/my-folder2////my-file.txt'));
        $this->is($file3, $path->url($this->root . DS . 'my-folder2' . DS . 'my-file.txt'));

        $this->is($file2 . '?data=test&value=hello', $path->url($path1 . DS . 'file0.txt?data=test&value=hello'));

        isNull($path->url('default:file.txt'));
        isNull($path->url('alias:file.txt'));

        isNull($path->url($this->root . DS . 'my-folder2' . DS . 'file.txt'));
        isNull($path->url($this->root . 'my/' . DS . 'file.txt'));

        $fs->remove([
            $path1,
            $path2,
            $this->root . DS . 'my-folder2',
            $this->root . DS . 'file2.txt',
        ]);
    }

    public function testHasCDBack()
    {
        $path = Path::getInstance(__METHOD__);
        $paths = [
            $this->root,
            $this->root . '/..',
            $this->root . '/../../',
        ];
        $path->set('default', $paths);

        [$path1, $path2, $path3] = $paths;

        $expected = [
            realpath(FS::clean($path3)),
            realpath(FS::clean($path2)),
            FS::clean($path1, '/'),
        ];

        $this->is($expected, $path->getPaths('default'));
    }

    public function testDeprecated_add()
    {
        $path = Path::getInstance(__METHOD__);

        $paths = [
            $this->root,
            $this->root . '/..',
        ];

        $path->add($paths, 'default', Path::MOD_PREPEND);

        $paths2 = [
            $this->root . '/..',
            $this->root,
        ];
        $path->add($paths2, 'test', Path::MOD_APPEND);

        $expected = [
            realpath($this->root . '/..'),
            $this->root,
        ];

        $this->is($expected, $path->getPaths('default:'));
        $this->is($expected, $path->getPaths('test:'));
    }

    public function testGlob()
    {
        $path = new Path();

        $path->set('somepath', __DIR__ . '/..');
        $path->set('src', 'somepath:src');

        $paths = $path->glob('somepath:src/*.php');

        $this->is([
            PROJECT_ROOT . '/src/Exception.php',
            PROJECT_ROOT . '/src/Path.php',
        ], $paths);
    }

    public function testRelative()
    {
        $path = new Path();

        $path->setRoot(__DIR__ . '/..');
        $path->set('somepath', __DIR__ . '/..');
        $path->set('src', 'somepath:src');

        $actual = $path->rel('somepath:src/Path.php');
        $this->is('src/Path.php', $actual);
    }

    public function testRelativeGlob()
    {
        $path = new Path();

        $path->setRoot(__DIR__ . '/..');
        $path->set('somepath', __DIR__ . '/..');
        $path->set('src', 'somepath:src');

        $paths = $path->relGlob('somepath:src/*.php');

        $this->is([
            'src/Exception.php',
            'src/Path.php',
        ], $paths);
    }

    public function testPredefinedRoot()
    {
        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/page';

        // defult
        $sysRoot = Sys::getDocRoot();
        $path = new Path();
        $this->is($sysRoot, $path->getRoot());

        // custom
        $path = new Path(PROJECT_ROOT);
        $this->is(PROJECT_ROOT, $path->getRoot());
        $this->is(PROJECT_ROOT, $path->get('root:'));
        $this->is(PROJECT_SRC, $path->get('root:src'));
        isSame('src', $path->rel('root:src'));
        isSame('/src', $path->url('root:src', false));
        isSame('http://test.dev/src', $path->url('root:src', true));
    }

    public function testRootPreDefinedAlias()
    {
        $path = new Path(__DIR__);
        $curFile = basename(__FILE__);

        $this->is(__FILE__, $path->get('root:' . $curFile));
    }

    public function testPathByFlagIsReal()
    {
        $path = new Path(PROJECT_ROOT);

        $name = mt_rand();
        $symOrigDir = $this->root . DS . $name;
        $symLink = __DIR__ . '/link/';

        $fs = new Filesystem();
        $fs->mkdir($symOrigDir);

        $fs->dumpFile($symOrigDir . DS . 'file-1.txt', '');
        $fs->dumpFile($symLink . DS . 'file-2.txt', '');

        $fs->symlink($symLink, $symOrigDir . '/link', true);

        $path->set('by-flag', [$symLink, $symOrigDir]);

        isNotNull($path->get('by-flag:file-2.txt'));

        $path->setRealPathFlag(false);
        isNotNull($path->get('by-flag:file-2.txt'));

        $fs->remove([$symOrigDir, $symLink]);
    }
}
