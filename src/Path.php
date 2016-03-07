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
     * Minimal alias name length.
     *
     * @var string
     */
    const MIN_ALIAS_LENGTH = 2;

    /**
     * Mod prepend rule add paths.
     *
     * @var string
     */
    const MOD_PREPEND = 'prepend';

    /**
     * Mod append rule add paths.
     *
     * @var string
     */
    const MOD_APPEND = 'append';

    /**
     * Reset all registered paths.
     *
     * @var string
     */
    const MOD_RESET = 'reset';

    /**
     * Holds paths list.
     *
     * @var array
     */
    protected $_paths = array();

    /**
     * Root dir.
     *
     * @var string
     */
    protected $_root;

    /**
     * Pull of instances
     *
     * @var array
     */
    protected static $_objects = array();

    /**
     * Get path instance.
     *
     * @param string $key
     * @return Path
     *
     * @throws Exception
     */
    public static function getInstance($key = 'default')
    {
        if (empty($key)) {
            throw new Exception('Invalid object key');
        }

        if (!isset(self::$_objects[$key])) {
            self::$_objects[$key] = new self();
        }

        return self::$_objects[$key];
    }

    /**
     * Get instance keys.
     *
     * @return array
     */
    public static function getInstanceKeys()
    {
        return array_keys(self::$_objects);
    }

    /**
     * Register alias locations in file system.
     * Example:
     *      "default:file.txt" - if added at least one path and
     *      "C:\server\test.dev\fy-folder" or "C:\server\test.dev\fy-folder\..\..\"
     *
     * @param string       $alias
     * @param string|array $paths
     * @param string|bool  $mode
     *
     * @throws Exception
     */
    public function set($alias, $paths, $mode = Path::MOD_PREPEND)
    {
        $paths = (array)$paths;
        $alias = $this->_cleanAlias($alias);

        if (strlen($alias) < Path::MIN_ALIAS_LENGTH) {
            throw new Exception(sprintf('The minimum number of characters is %s', Path::MIN_ALIAS_LENGTH));
        }

        if ($mode === self::MOD_RESET) { // Reset mode
            $this->_paths[$alias] = array();

            $mode = self::MOD_PREPEND; // Add new paths in Prepend mode
        }

        foreach ($paths as $path) {

            if (!isset($this->_paths[$alias])) {
                $this->_paths[$alias] = array();
            }

            $path = $this->_clean($path);
            if ($path && !in_array($path, $this->_paths[$alias], true)) {

                if (preg_match('/^' . preg_quote($alias . ':') . '/i', $path)) {
                    throw new Exception(sprintf('Added looped path "%s" to key "%s"', $path, $alias));
                }

                $this->_addNewPath($path, $alias, $mode);
            }
        }
    }

    /**
     * Old version of set() method
     *
     * @param string|array $paths
     * @param string       $alias
     * @param string       $mode
     * @throws Exception
     *
     * @deprecated
     */
    public function add($paths, $alias = 'default', $mode = Path::MOD_PREPEND)
    {
        $this->set($alias, $paths, $mode);
    }

    /**
     * Normalize and clean path.
     *
     * @param string $path ("C:\server\test.dev\file.txt")
     * @return string
     */
    public function clean($path)
    {
        $tokens = array();
        $path   = $this->_clean($path);
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
     * Get absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return null|string
     */
    public function get($source)
    {
        list(, $paths, $path) = $this->_parse($source);
        return $this->_find($paths, $path);
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return array
     */
    public function glob($source)
    {
        list(, $paths, $path) = $this->_parse($source);
        return $this->_find($paths, $path, true);
    }

    /**
     * Get all absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return mixed
     */
    public function getPaths($source)
    {
        $source = $this->_cleanSource($source);
        list(, $paths) = $this->_parse($source);

        return $paths;
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
     * Check virtual or real path.
     *
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return bool
     */
    public function isVirtual($path)
    {
        $parts = explode(':', $path, 2);

        list($alias) = $parts;
        $alias = $this->_cleanAlias($alias);
        if ($this->prefix($path) !== null && !array_key_exists($alias, $this->_paths)) {
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
        $path = $this->_clean($path);
        return preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }

    /**
     * Remove path from registered paths for source
     *
     * @param string       $fromSource (example: "default:file.txt")
     * @param string|array $paths
     * @return bool
     */
    public function remove($fromSource, $paths)
    {
        $paths      = (array)$paths;
        $fromSource = $this->_cleanSource($fromSource);
        list($alias) = $this->_parse($fromSource);

        $return = false;

        foreach ($paths as $origPath) {

            $path = $this->_cleanPath($this->_clean($origPath));

            $key = array_search($path, $this->_paths[$alias], true);
            if (false !== $key) {
                unset($this->_paths[$alias][$key]);
                $return = true;
            }
        }

        return $return;
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

        $this->_root = $this->_clean($dir);
    }

    /**
     * Get url to a file.
     *
     * @param string    $source (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @param bool|true $full
     * @return null|string
     */
    public function url($source, $full = true)
    {
        $details = explode('?', $source);

        $path = $details[0];
        $path = $this->_cleanPath($path);
        $path = $this->_getUrlPath($path, true);

        if (!empty($path)) {
            if (isset($details[1])) {
                $path .= '?' . $details[1];
            }

            $path = '/' . $path;
            return ($full) ? Url::root() . $path : $path;
        }

        return null;
    }

    /**
     * Get relative path to file or directory
     *
     * @param string $source (example: "default:file.txt")
     * @return null|string
     */
    public function rel($source)
    {
        $fullpath = (string)$this->get($source);
        return FS::getRelative($fullpath, $this->_root, '/');
    }

    /**
     * Get list of relative path to file or directory
     *
     * @param string $source (example: "default:*.txt")
     * @return null|string
     */
    public function relGlob($source)
    {
        $list = (array)$this->glob($source);
        foreach ($list as $key => $item) {
            $list[$key] = FS::getRelative($item, $this->_root, '/');
        }

        return $list;
    }

    /**
     * Add path to hold.
     *
     * @param string|array $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string       $alias
     * @param string|bool  $mode
     * @return void
     */
    protected function _addNewPath($path, $alias, $mode)
    {
        if ($cleanPath = $this->_cleanPath($path)) {

            if ($mode == self::MOD_PREPEND) {
                array_unshift($this->_paths[$alias], $cleanPath);
            }

            if ($mode == self::MOD_APPEND) {
                array_push($this->_paths[$alias], $cleanPath);
            }
        }
    }

    /**
     * Check root directory.
     *
     * @throws Exception
     */
    protected function _checkRoot()
    {
        if ($this->_root === null) {
            throw new Exception(sprintf('Please, set the root directory'));
        }
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param string|array $paths
     * @param string       $file
     * @param bool         $isGlob
     * @return null|string|array
     */
    protected function _find($paths, $file, $isGlob = false)
    {
        $paths = (array)$paths;
        $file  = ltrim($file, "\\/");

        foreach ($paths as $path) {

            $fullPath = $this->clean($path . '/' . $file);

            if ($isGlob) {
                $paths = glob($fullPath);
                $paths = array_filter((array)$paths);
                return $paths ?: array();

            } elseif (file_exists($fullPath) || is_dir($fullPath)) {
                return $fullPath;
            }

        }

        return null;
    }

    /**
     * Get add path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $path
     * @return null|string
     */
    protected function _cleanPath($path)
    {
        if ($this->isVirtual($path)) {
            return $this->_clean($path);
        }

        if ($this->_hasCDBack($path)) {
            $realpath = $this->_clean(realpath($path));
            return $realpath ?: null;
        }

        return $this->_clean($path);
    }

    /**
     * Get url path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param bool   $exitsFile
     * @return string
     * @throws Exception
     */
    protected function _getUrlPath($path, $exitsFile = false)
    {
        $this->_checkRoot();

        $path = $this->_cleanPath($path);
        if ($this->isVirtual($path)) {
            $path = $this->get($path);
        }

        $subject = $path;
        $pattern = '/^' . preg_quote($this->_root, '/') . '/i';

        if ($exitsFile && !$this->isVirtual($path) && !file_exists($path)) {
            $subject = null;
        }

        return ltrim(preg_replace($pattern, '', $subject), '/');
    }

    /**
     * Check has back current.
     *
     * @param $path
     * @return int
     */
    protected function _hasCDBack($path)
    {
        $path = $this->_clean($path);
        return preg_match('(/\.\.$|/\.\./$)', $path);
    }

    /**
     * Parse source string.
     *
     * @param string $source (example: "default:file.txt")
     * @return array
     */
    protected function _parse($source)
    {
        $path = null;
        list($alias, $path) = explode(':', $source, 2);

        $path  = ltrim($path, "\\/");
        $paths = $this->_resolvePaths($alias);

        return array($alias, $paths, $path);
    }

    /**
     * @param string $alias
     * @return array
     */
    protected function _resolvePaths($alias)
    {
        $alias = $this->_cleanAlias($alias);

        $paths = isset($this->_paths[$alias]) ? $this->_paths[$alias] : array();

        $result = array();
        foreach ($paths as $originalPath) {
            if ($this->isVirtual($originalPath)) {

                if ($realPath = $this->get($originalPath)) {
                    $path = $realPath;
                } else {
                    $path = $this->_cleanPath($originalPath);
                }

            } else {
                $path = $this->_cleanPath($originalPath);
            }

            $result[] = realpath($path);
        }

        $result = array_filter($result); // remove empty
        $result = array_values($result); // reset keys

        return $result;
    }

    /**
     * @param $alias
     * @return mixed|string
     */
    protected function _cleanAlias($alias)
    {
        $alias = preg_replace('/[^a-z0-9_\.-]/i', '', $alias);
        return $alias;
    }

    /**
     * @param string $source
     * @return mixed|string
     */
    protected function _cleanSource($source)
    {
        $source = $this->_cleanAlias($source);
        $source .= ':';

        return $source;
    }

    /**
     * Forced clean path with linux-like sleshes
     *
     * @param string $path
     * @return string
     */
    protected function _clean($path)
    {
        return FS::clean($path, '/');
    }
}
