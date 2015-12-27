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

namespace JBZoo\Path;

use JBZoo\Utils\FS;
use JBZoo\Utils\Url;

/**
 * Class Path
 * @package JBZoo\Path
 */
class Path
{

    /**
     * Default package name.
     *
     * @var string
     */
    const DEFAULT_PACKAGE = 'default';

    /**
     * Prepend rule add paths.
     *
     * @var string
     */
    const PREPEND = 'prepend';

    /**
     * Append rule add paths.
     *
     * @var string
     */
    const APPEND = 'append';

    /**
     * Reset all registered paths.
     *
     * @var bool
     */
    const RESET = true;

    /**
     * Holds paths list.
     *
     * @var array
     */
    protected $_paths = array();

    /**
     * Hold root dir.
     *
     * @var string
     */
    protected $_root;

    /**
     * Holds object instance.
     *
     * @var array
     */
    protected static $_objects = array();

    /**
     * Get path instance.
     *
     * @param string $key
     * @return \JBZoo\Path\Path
     */
    public static function getInstance($key = 'default')
    {
        if (!isset(self::$_objects[$key])) {
            self::$_objects[$key] = new self($key);
        }

        return self::$_objects[$key];
    }

    /**
     * Remove instance.
     *
     * @param string $key
     */
    public static function removeInstance($key = 'default')
    {
        if (array_key_exists($key, self::$_objects)) {
            unset(self::$_objects[$key]);
        }
    }

    /**
     * Path constructor.
     *
     * @param string $key
     * @throws Exception
     */
    protected function __construct($key = 'default')
    {
        if (empty($key)) {
            throw new Exception('Invalid object key');
        }

        static::$_objects[$key] = $key;
    }

    /**
     * Setup root directory.
     *
     * @param string $dir
     * @throws Exception
     */
    public function setRoot($dir)
    {
        if (!is_dir($dir)) {
            throw new Exception(sprintf('Not found directory: %s', $dir));
        }

        if (!isset($this->_root)) {
            $this->_root = $dir;
        }
    }

    /**
     * Get instance keys.
     *
     * @return array
     */
    public function getInstanceKeys()
    {
        return array_keys(self::$_objects);
    }

    /**
     * Get root directory.
     *
     * @return mixed
     * @throws Exception
     */
    public function getRoot()
    {
        $this->_checkRoot();
        return $this->_root;
    }

    /**
     * Register package locations in file system.
     *
     * @param string|array $paths
     * @param string $package
     * @param string|bool $mode
     * @throws Exception
     */
    public function register($paths, $package = Path::DEFAULT_PACKAGE, $mode = Path::PREPEND)
    {
        $paths = (array) $paths;

        if (strlen($package) < 3) {
            throw new Exception('The minimum number of characters is 3');
        }

        $this->_reset($paths, $package, $mode);
        foreach ($paths as $path) {
            if (!isset($this->_paths[$package])) {
                $this->_paths[$package] = array();
            }

            if (in_array($path, $this->_paths[$package])) {
                break;
            }

            $this->_add($path, $package, $mode);
        }
    }

    /**
     * Get the absolute url to a file.
     *
     * @param string $source (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return null|string
     */
    public function url($source)
    {
        $details = explode('?', $source);
        $path    = $details[0];
        $path    = ($this->isVirtual($path)) ? $this->get($path) : FS::clean($path, '/');
        $path    = $this->relative($path, true);

        if (!empty($path)) {
            if (isset($details[1])) {
                $path .= '?' . $details[1];
            }

            return Url::current() . $path;
        }

        return null;
    }

    /**
     * Remove path from registered paths.
     *
     * @param $source (example: "default:file.txt")
     * @param string|array $key
     * @return bool
     */
    public function remove($source, $key)
    {
        $keys = (array) $key;
        list($package) = $this->parse($source);

        $return = false;
        if ($this->_isDeleted($package, $keys)) {
            foreach ($keys as $key) {
                $key = (int) $key;
                if (array_key_exists($key, $this->_paths[$package])) {
                    unset($this->_paths[$package][$key]);
                    $return = true;
                }
            }
        }

        return $return;
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return null|string
     */
    public function get($source)
    {
        list(, $paths, $path) = $this->parse($source);
        return $this->_find($paths, $path);
    }

    /**
     * Get all absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return mixed
     */
    public function getPaths($source)
    {
        list(, $paths) = $this->parse($source);
        return $paths;
    }

    /**
     * Normalize path.
     *
     * @param string $path ("C:\server\test.dev\file.txt")
     * @return string
     */
    public function normalize($path)
    {
        $tokens = array();
        $path   = FS::clean($path, '/');
        $prefix = $this->prefix($path);
        $path   = substr($path, strlen($prefix));
        $parts  = array_filter(explode('/', $path), 'strlen');

        foreach ($parts as $part) {
            if ('..' === $part) {
                array_pop($tokens);
            } elseif ('.' !== $part) {
                array_push($tokens, $part);
            }
        }

        return $prefix . implode('/', $tokens);
    }

    /**
     * Parse source string.
     *
     * @param string $source (example: "default:file.txt")
     * @param string $package
     * @return array
     */
    public function parse($source, $package = Path::DEFAULT_PACKAGE)
    {
        $path  = null;
        $parts = explode(':', $source, 2);
        $count = count($parts);

        if ($count == 1) {
            list($path) = $parts;
        } elseif ($count == 2) {
            list($package, $path) = $parts;
        }

        $path  = ltrim($path, "\\/");
        $paths = isset($this->_paths[$package]) ? $this->_paths[$package] : array();

        return array($package, $paths, $path);
    }

    /**
     * Check virtual or real path.
     *
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return bool
     */
    public function isVirtual($path)
    {
        $parts = explode(':', $path, 2);

        list($package) = $parts;
        if ($this->prefix($path) !== null && !array_key_exists($package, $this->_paths)) {
            return false;
        }

        return (count($parts) == 2) ? true : false;
    }

    /**
     * Get path prefix.
     *
     * @param string $path (example: "C:\server\test.dev\file.txt")
     * @return null
     */
    public function prefix($path)
    {
        $path = FS::clean($path, '/');
        return preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }

    /**
     * Get relative path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param bool $exitsFile
     * @return string
     * @throws Exception
     */
    public function relative($path, $exitsFile = false)
    {
        $this->_checkRoot();

        $root    = preg_quote(FS::clean($this->_root, '/'), '/');
        $path    = $this->_checkAddPath($path, '/');
        $subject = $path;
        $pattern = '/^' . $root . '/i';

        if ($exitsFile && !$this->isVirtual($path) && !file_exists($path)) {
            $subject = null;
        }

        return ltrim(preg_replace($pattern, '', $subject), '/');
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param string|array $paths
     * @param string $file
     * @return null|string
     */
    protected function _find($paths, $file)
    {
        $paths = (array) $paths;
        $file  = ltrim($file, "\\/");

        foreach ($paths as $path) {
            $fullPath = $this->normalize($path . '/' . $file);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Add path to hold.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $package
     * @param string $mode
     * @return void
     */
    protected function _add($path, $package, $mode)
    {
        $path = $this->_checkAddPath($path);
        if (!empty($path)) {
            if ($mode == self::PREPEND) {
                array_unshift($this->_paths[$package], $path);
            }

            if ($mode == self::APPEND) {
                array_push($this->_paths[$package], $path);
            }
        }
    }

    /**
     * Check added path.
     *
     * @param $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $dirSep
     * @return null|string
     */
    protected function _checkAddPath($path, $dirSep = DIRECTORY_SEPARATOR)
    {
        return ($this->isVirtual($path)) ? $this->get($path) : FS::clean($path, $dirSep);
    }

    /**
     * Check root directory.
     *
     * @throws Exception
     */
    protected function _checkRoot()
    {
        if ($this->_root == null) {
            throw new Exception(sprintf('Please, set the root directory'));
        }
    }

    /**
     * Reset added paths.
     *
     * @param string|array $paths (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $package
     * @param bool $mode
     */
    protected function _reset($paths, $package, $mode)
    {
        if ($mode === self::RESET) {
            $this->_paths[$package] = $paths;
            return;
        }
    }

    /**
     * Checking the possibility of removing the path.
     *
     * @param string $package
     * @param array $keys
     * @return bool
     */
    protected function _isDeleted($package, $keys)
    {
        if (isset($this->_paths[$package]) && is_array($this->_paths[$package]) && !empty($keys)) {
            return true;
        }

        return false;
    }
}
