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
 */
class PathTest extends PHPUnit
{
    /**
     * @var string
     */
    private $root = '';

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var Path
     */
    private $path;

    protected function setUp(): void
    {
        $this->root = realpath(__DIR__ . '/../build') . '/test';
        $this->fs = new Filesystem();
        $this->fs->remove($this->root);
        $this->fs->mkdir($this->root);

        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/page';

        $this->path = new Path($this->root);
    }

    #### Test cases ####################################################################################################

    public function testGeneral()
    {
        $default = new Path($this->root);
        $import = new Path($this->root);
        $export = new Path($this->root);

        $name1 = mt_rand();
        $name2 = mt_rand();
        $name3 = mt_rand();
        $defaultDir = "{$this->root}/{$name1}";
        $importDir = "{$this->root}/{$name2}";
        $exportDir = "{$this->root}/{$name3}";

        $this->fs->mkdir($defaultDir);
        $this->fs->mkdir($importDir);
        $this->fs->mkdir($exportDir);

        $default->set('defau/lt', $defaultDir);
        $import->set('Defau\\l//t', $importDir);
        $export->set('()de~~fau+!#$lt', [
            $exportDir,
            $importDir,
        ]);

        $this->is($defaultDir, $default->getPaths('default'));
        $this->is($importDir, $import->getPaths('Default'));

        $this->is([$importDir, $exportDir], $export->getPaths('default:'));

        $_SERVER['HTTP_HOST'] = 'test.dev';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/page';

        $this->fs->dumpFile("{$defaultDir}/file.txt", '');
        $this->fs->dumpFile("{$importDir}/simple.txt", '');
        $this->fs->dumpFile("{$exportDir}/my-file.txt", '');

        $default->setRoot($this->root);
        $import->setRoot($this->root);
        $export->setRoot($this->root);

        $current = Url::root();
        isSame("{$current}/{$name1}/file.txt", $default->url('default:file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url('default:\file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url('default:/file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url('default:////file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url('default:\\\\file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url($defaultDir . DS . 'file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url($defaultDir . '///file.txt'));
        isSame("{$current}/{$name1}/file.txt", $default->url($defaultDir . '\\\\file.txt'));

        isSame("{$current}/{$name2}/simple.txt", $import->url('Default:simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url('Default:\simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url('Default:/simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url('Default:////simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url('Default:\\\\simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url($importDir . DS . 'simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url($importDir . '///simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $import->url($importDir . '\\\\simple.txt'));

        isSame("{$current}/{$name3}/my-file.txt", $export->url('default:my-file.txt'));
        isSame("{$current}/{$name3}/my-file.txt", $export->url('default:\my-file.txt'));
        isSame("{$current}/{$name3}/my-file.txt", $export->url('default:/my-file.txt'));
        isSame("{$current}/{$name2}/simple.txt", $export->url('default:////simple.txt'));
        isSame("{$current}/{$name2}/simple.txt", $export->url('default:\\\\simple.txt'));
        isSame("{$current}/{$name3}/my-file.txt", $export->url($exportDir . DS . 'my-file.txt'));
        isSame("{$current}/{$name3}/my-file.txt", $export->url($exportDir . '///my-file.txt'));
        isSame("{$current}/{$name3}/my-file.txt", $export->url($exportDir . '\\\\my-file.txt'));

        isSame("{$current}/{$name3}/my-file.txt?ver=123", $export->url($exportDir . '\\\\my-file.txt?ver=123'));

        $this->fs->remove([$defaultDir, $importDir, $exportDir]);
    }

    public function testAddLoopedPath()
    {
        $this->expectException(Exception::class);

        $this->path->set('defaults', 'defaults:');
    }

    public function testSetRootAlias()
    {
        $this->expectException(Exception::class);

        $this->path->set('root', $this->root);
    }

    public function testResolveAnyPaths()
    {
        $paths = [
            // Normal
            $this->root,
            $this->root . '/..',
            $this->root . '/../..',

            // Virtual paths
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

        $this->path
            ->set('default', $paths, Path::MOD_APPEND)
            ->set('somepath', $this->root);

        $expected = [
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
            realpath($this->root . '/../test'),
        ];

        $this->is($expected, $this->path->getPaths('default'));
    }

    public function testSetAppend()
    {
        $paths = [
            $this->root,
            $this->root . '/..',
        ];

        $appendPath = $this->root . '/../..';

        $this->path->set('default', $paths, Path::MOD_APPEND);
        $this->path->set('default', $appendPath, Path::MOD_APPEND);

        $expected = [
            realpath($this->root),
            realpath($this->root . '/..'),
            realpath($this->root . '/../..'),
        ];

        $this->is($expected, $this->path->getPaths('default'));
        $this->is($expected, $this->path->getPaths('default:'));
    }

    public function testSetPrepend()
    {
        $paths = [
            $this->root,
            $this->root . '/..',
        ];

        $appendPath = $this->root . '/../..';

        $this->path->set('default', $paths);
        $this->path->set('default', $appendPath, Path::MOD_PREPEND);

        $expected = [
            realpath($appendPath),
            realpath($this->root . '/..'),
            realpath($this->root),
        ];

        $this->is($expected, $this->path->getPaths('default'));
        $this->is($expected, $this->path->getPaths('default:'));
    }

    public function testSetReset()
    {
        $newPath = $this->root . '/..';

        $this->path->set('default', $this->root . '/../..');
        $this->path->set('default', $newPath, Path::MOD_RESET);

        $this->is(realpath($newPath), $this->path->getPaths('default'));
    }

    public function testSetVirtual()
    {
        $this->path->set('default', 'undefined:folder');
        isSame([], $this->path->getPaths('default'));

        $this->path->set('default', 'alias:folder');
        isSame([], $this->path->getPaths('alias'));

        $this->path->set('default', $this->root);
        $this->is($this->root, $this->path->getPaths('default'));

        $this->path->set('default', 'undefined:virtual-folder');
        $this->is($this->root, $this->path->getPaths('default'));

        $newFolder = "{$this->root}/virtual-folder";
        $this->fs->mkdir($newFolder);

        $this->path->set('default', 'undefined:virtual-folder');
        $this->is([$this->root], $this->path->getPaths('default:'));
    }

    public function testEmptyPaths()
    {
        $this->path->set('default', [
            $this->root,
            "{$this->root}/folder",
        ]);

        $packagePaths = $this->path->getPaths('alias:');
        isSame([], $packagePaths);
    }

    public function testIsVirtual()
    {
        isTrue($this->path->isVirtual('alias:'));
        isTrue($this->path->isVirtual('alias:styles.css'));
        isTrue($this->path->isVirtual('alias:folder/styles.css'));
    }

    public function testRegisterMinLength()
    {
        $this->expectException(Exception::class);

        $this->path->set('a', $this->root);
    }

    public function testRegisterEmptyKey()
    {
        $this->expectException(Exception::class);

        $this->path->set(false, $this->root);
    }

    public function testIsNotVirtual()
    {
        isFalse($this->path->isVirtual(__DIR__));
        isFalse($this->path->isVirtual(dirname(__DIR__)));
        isFalse($this->path->isVirtual('/folder/file.txt'));
        isFalse($this->path->isVirtual('alias:/styles.css'));
        isFalse($this->path->isVirtual('alias:\styles.css'));
    }

    public function testHasPrefix()
    {
        $this->assertIsString($this->path->prefix(__DIR__));
        $this->assertIsString($this->path->prefix(dirname(__DIR__)));
        $this->assertIsString($this->path->prefix('P:\\\\Folder\\'));
    }

    public function testNoPrefix()
    {
        isNull($this->path->prefix('folder/file.txt'));
        isNull($this->path->prefix('./folder/file.txt'));
        isNull($this->path->prefix('default:folder/file.txt'));
    }

    public function testClean()
    {
        isSame(FS::clean(__DIR__, '/'), $this->path->clean(__DIR__));
        isSame('test/path/folder', $this->path->clean('../test/path/folder/'));
        isSame('test/path/folder', $this->path->clean('../../test/path/folder/'));
        isSame('test/path/folder', $this->path->clean('..\..\test\path\folder\\'));
        isSame('test/path/folder', $this->path->clean('..\../test///path/\/\folder/\\'));
    }

    public function testPathSuccess()
    {
        $name = mt_rand();
        $paths = [
            "{$this->root}/{$name}",
            "{$this->root}/{$name}",
            "{$this->root}/{$name}/folder",
        ];

        [$dir1, $dir2] = $paths;

        $this->fs->mkdir($dir2);

        $mainDir = "{$dir2}/simple";

        $this->fs->mkdir($mainDir);

        $file1 = "{$dir2}/text.txt";
        $file2 = "{$dir2}/file.pot";
        $file3 = "{$dir1}/style.less";
        $file4 = "{$dir2}/style.less";
        $file5 = "{$mainDir}/file.txt";

        $this->fs->dumpFile($file1, '1');
        $this->fs->dumpFile($file2, '2');
        $this->fs->dumpFile($file3, '3');
        $this->fs->dumpFile($file4, '4');
        $this->fs->dumpFile($file5, '5');

        //  Symlink folder.
        $symOrigDir = "{$dir1}/sym-dir-orig";
        $symLink = "{$dir1}/symlink/folder";

        $this->fs->mkdir($symOrigDir);
        $this->fs->dumpFile("{$symOrigDir}/test-symlink.txt", '10');
        $this->fs->symlink($symOrigDir, $symLink, true);

        $this->path->set('default', $paths);

        $this->is($file1, $this->path->get('default:text.txt'));
        $this->is($file2, $this->path->get('default:file.pot'));

        $this->is("{$dir2}/style.less", $this->path->get('default:/style.less'));
        $this->is("{$dir2}/style.less", $this->path->get('default:\style.less'));
        $this->is("{$dir2}/style.less", $this->path->get('default:\/style.less'));
        $this->is("{$dir2}/style.less", $this->path->get('default:\\\style.less'));
        $this->is("{$dir2}/style.less", $this->path->get('default:///style.less'));

        $this->is($file5, $this->path->get('default:simple/file.txt'));
        $this->is($file5, $this->path->get('default:simple\file.txt'));
        $this->is($file5, $this->path->get('default:simple\\\\file.txt'));
        $this->is($file5, $this->path->get('default:simple////file.txt'));
        $this->is($file5, $this->path->get('default:simple/file.txt'));
        $this->is($file5, $this->path->get('default:\\simple/file.txt'));
        $this->is($file5, $this->path->get('default:\/simple/file.txt'));

        isNull($this->path->get('alias:/simple/file.txt'));

        $this->is(
            "{$symLink}/test-symlink.txt",
            $this->path->get('default:symlink/folder/test-symlink.txt')
        );
    }

    public function testRemove()
    {
        $this->path->set('default', [
            $this->root,
            $this->root . '/..',
            $this->root . '/../..',
        ], Path::MOD_PREPEND);

        isTrue($this->path->remove('default', $this->root));

        $this->is([
            realpath($this->root . '/../..'),
            realpath($this->root . '/..'),
        ], $this->path->getPaths('default'));

        isFalse($this->path->remove('default', realpath("{$this->root}/../../src")));
        isFalse($this->path->remove('default', "{$this->root}/../../src"));

        $this->is([
            realpath($this->root . '/../..'),
            realpath($this->root . '/..'),
        ], $this->path->getPaths('default'));

        $removedPaths = [
            "{$this->root}\\..\\..",
            "{$this->root}////..",
        ];
        isTrue($this->path->remove('default', $removedPaths));

        $this->is([], $this->path->getPaths('default'));
    }

    public function testSetRootFailed()
    {
        $this->expectException(Exception::class);

        $this->path->setRoot("{$this->root}/" . mt_rand());
    }

    public function testSetRoot()
    {
        $dir = $this->root . DS . mt_rand();

        $this->path->setRoot($this->root);
        $this->is($this->root, $this->path->getRoot());

        $this->fs->mkdir($dir);
        $this->path->setRoot($dir);
        $this->is($dir, $this->path->getRoot());
    }

    public function testShortUrl()
    {
        $dir = "{$this->root}/short";
        $this->fs->dumpFile("{$dir}/file.txt", '123');

        $this->path->setRoot($this->root);
        $this->path->set('default', $dir);

        $this->is('/short/file.txt', $this->path->url('default:file.txt', false));
        isSame('http://test.dev/short/file.txt', $this->path->url('default:file.txt'));
    }

    public function testFullUrl()
    {
        $paths = [
            $this->root . DS . 'my-folder',
            $this->root . DS . 'my-folder2' . DS . 'dir',
            $this->root,
        ];

        foreach ($paths as $key => $p) {
            $this->fs->mkdir($p);
            $this->fs->dumpFile($p . DS . 'file' . $key . '.txt', '');
        }

        $this->fs->dumpFile($this->root . DS . 'my-folder2' . DS . 'my-file.txt', '');

        [$path1, $path2] = $paths;

        $this->path->setRoot($this->root);
        $this->path->set('default', $paths);

        $current = Url::root() . '/';

        $file1 = $current . 'my-folder2/dir/file1.txt';
        $file2 = $current . 'my-folder/file0.txt';
        $file3 = $current . 'my-folder2/my-file.txt';

        $this->is($file1, $this->path->url('default:file1.txt'));
        $this->is($file3, $this->path->url('default:my-folder2/my-file.txt'));
        $this->is($file3, $this->path->url('default:my-folder2\\\\my-file.txt'));
        $this->is($file3, $this->path->url('default:\my-folder2\my-file.txt'));

        $this->is($file1, $this->path->url($path2 . DS . 'file1.txt'));
        $this->is($file2, $this->path->url($path1 . DS . 'file0.txt'));
        $this->is($file2, $this->path->url($path1 . '/file0.txt'));
        $this->is($file3, $this->path->url($this->root . '\my-folder2\my-file.txt'));
        $this->is($file3, $this->path->url($this->root . '/my-folder2////my-file.txt'));
        $this->is($file3, $this->path->url($this->root . DS . 'my-folder2' . DS . 'my-file.txt'));

        $this->is($file2 . '?data=test&value=hello', $this->path->url($path1 . DS . 'file0.txt?data=test&value=hello'));

        isNull($this->path->url('default:file.txt'));
        isNull($this->path->url('alias:file.txt'));

        isNull($this->path->url($this->root . DS . 'my-folder2' . DS . 'file.txt'));
        isNull($this->path->url($this->root . 'my/' . DS . 'file.txt'));
    }

    public function testHasCDBack()
    {
        $paths = [
            $this->root,
            $this->root . '/..',
            $this->root . '/../../',
        ];
        $this->path->set('default', $paths);

        [$path1, $path2, $path3] = $paths;

        $expected = [
            realpath(FS::clean($path3)),
            realpath(FS::clean($path2)),
            FS::clean($path1, '/'),
        ];

        $this->is($expected, $this->path->getPaths('default'));
    }

    public function testGlob()
    {
        $this->path->set('somepath', __DIR__ . '/..');
        $this->path->set('src', 'somepath:src');

        $paths = $this->path->glob('somepath:src/*.php');

        $this->is([
            realpath(__DIR__ . '/../src/Exception.php'),
            realpath(__DIR__ . '/../src/Path.php'),
        ], $paths);
    }

    public function testRelative()
    {
        $this->path->setRoot(__DIR__ . '/..');
        $this->path->set('somepath', __DIR__ . '/..');
        $this->path->set('src', 'somepath:src');

        $actual = $this->path->rel('somepath:src/Path.php');
        $this->is('src/Path.php', $actual);
    }

    public function testRelativeGlob()
    {
        $this->path->setRoot(__DIR__ . '/..');
        $this->path->set('somepath', __DIR__ . '/..');
        $this->path->set('src', 'somepath:src');

        $paths = $this->path->relGlob('somepath:src/*.php');

        $this->is([
            'src/Exception.php',
            'src/Path.php',
        ], $paths);
    }

    public function testPredefinedRoot()
    {
        $path = new Path();

        $homeDir = Sys::getDocRoot();
        isNotEmpty($homeDir);

        $this->is($homeDir, $path->getRoot());
        $this->is(realpath('.'), $path->getRoot());

        $path2 = new Path(__DIR__ . '/..');
        isSame(__DIR__ . '/..', $path2->getRoot());
    }

    public function testPredefinedRootAndCustomPaths()
    {
        $root = realpath(__DIR__ . '/..');

        $path = new Path($root);
        $this->is($root, $path->getRoot());
        $this->is(realpath(__DIR__ . '/../src'), $path->get('root:src'));

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
        $path = new Path(__DIR__ . '/..');

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

    #### Tools #########################################################################################################

    /**
     * @param $paths
     * @return array
     */
    protected function cleanPath($paths)
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
        isSame($this->cleanPath($expected), $this->cleanPath($actual));
    }
}
