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

/**
 * Class PathTest
 * @package JBZoo\PHPUnit
 */
class PathTest extends PHPUnit
{

    protected $_ds;
    protected $_root;
    protected $_paths = array();

    public function setup()
    {
        $this->_root = __DIR__;
        $this->_ds   = DIRECTORY_SEPARATOR;

        $this->_paths = array(
            $this->_root,
            $this->_root . $this->_ds . 'folder'
        );
    }

    public function testRegisterAppend()
    {
        $path  = new Path();
        $paths = $this->_paths;

        $path->register($paths);
        $path->register($paths, 'test');

        $expected = array(
            $this->_root . $this->_ds . 'folder',
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

        $appendPath = $this->_root . $this->_ds . 'append';

        $path->register($paths);
        $path->register($appendPath, Path::DEFAULT_PACKAGE, Path::APPEND);

        array_push($paths, $appendPath);

        $expected = array(
            $this->_root . $this->_ds . 'folder',
            $this->_root,
            $appendPath,
        );

        $package = $path->getPaths();
        isSame($expected, $package);
    }

    public function testRegisterReset()
    {
        $path  = new Path();
        $paths = $this->_paths;

        $newPath = array(
            $this->_root . $this->_ds . 'new-folder'
        );

        $path->register($paths);
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
        $path  = new Path();
        $paths = $this->_paths;

        $path->register($paths);
        $parse1 = $path->parse('default:text.txt');

        $path->register($paths, 'package');
        $parse2 = $path->parse('package:folder/text.txt');

        $path->register($paths, 'alias');
        $parse3 = $path->parse('my/folder', 'alias');

        $expectedPaths = array(
            $this->_root . $this->_ds . 'folder',
            $this->_root,
        );

        $expected1 = array('default', $expectedPaths, 'text.txt');
        $expected2 = array('package', $expectedPaths, 'folder/text.txt');
        $expected3 = array('alias', $expectedPaths, 'my/folder');

        isSame($expected1, $parse1);
        isSame($expected2, $parse2);
        isSame($expected3, $parse3);
    }
}
