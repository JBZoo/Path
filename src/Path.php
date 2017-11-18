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

namespace JBZoo\Path;

use JBZoo\Utils\FS;
use JBZoo\Utils\Sys;
use JBZoo\Utils\Url;

/**
 * Class Path
 *
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
     * Flag of result path (If true, is real path. If false, is relative path).
     *
     * @var string
     */
    protected $isReal = true;

    /**
     * Holds paths list.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Root dir.
     *
     * @var string
     */
    protected $root;

    /**
     * Pull of instances
     *
     * @var array
     */
    protected static $objects = [];

    /**
     * Get path instance.
     *
     * @param string $key
     * @return Path
     *
     * @throws Exception
     */
    public static function getInstance($key = 'default'): Path
    {
        if (empty($key)) {
            throw new Exception('Invalid object key');
        }

        if (!isset(self::$objects[$key])) {
            self::$objects[$key] = new self();
        }

        return self::$objects[$key];
    }

    /**
     * Path constructor.
     *
     * @param string $root
     */
    public function __construct($root = null)
    {
        $root = $root ?: Sys::getDocRoot();
        $this->setRoot($root);
    }

    /**
     * Get instance keys.
     *
     * @return array
     */
    public static function getInstanceKeys(): array
    {
        return array_keys(self::$objects);
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
        $alias = $this->cleanAlias($alias);

        if (strlen($alias) < Path::MIN_ALIAS_LENGTH) {
            throw new Exception(sprintf('The minimum number of characters is %s', Path::MIN_ALIAS_LENGTH));
        }

        if ($alias === 'root') {
            throw new Exception('Alias "root" is predefined');
        }

        if ($mode === self::MOD_RESET) { // Reset mode
            $this->paths[$alias] = [];

            $mode = self::MOD_PREPEND; // Add new paths in Prepend mode
        }

        foreach ($paths as $path) {
            if (!isset($this->paths[$alias])) {
                $this->paths[$alias] = [];
            }

            $path = $this->cleanPath($path);
            if ($path && !in_array($path, $this->paths[$alias], true)) {
                if (preg_match('/^' . preg_quote($alias . ':', null) . '/i', $path)) {
                    throw new Exception(sprintf('Added looped path "%s" to key "%s"', $path, $alias));
                }

                $this->addNewPath($path, $alias, $mode);
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
        $tokens = [];
        $path = $this->cleanPath($path);
        $prefix = $this->prefix($path);
        $path = substr($path, strlen($prefix));
        $parts = array_filter(explode('/', $path), 'strlen');

        foreach ($parts as $part) {
            if ('..' === $part) {
                array_pop($tokens);
            } elseif ('.' !== $part) {
                $tokens[] = $part;
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
        list(, $paths, $path) = $this->parse($source);
        return $this->find($paths, $path);
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return array
     */
    public function glob($source)
    {
        list(, $paths, $path) = $this->parse($source);
        return $this->find($paths, $path, true);
    }

    /**
     * Get all absolute path to a file or a directory.
     *
     * @param $source (example: "default:file.txt")
     * @return mixed
     */
    public function getPaths($source)
    {
        $source = $this->cleanSource($source);
        list(, $paths) = $this->parse($source);

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
        $this->checkRoot();
        return $this->root;
    }

    /**
     * Setup real or relative path flag.
     *
     * @param bool $isReal
     * @return void
     */
    public function setRealPathFlag($isReal = true)
    {
        $this->isReal = (bool)$isReal;
    }

    /**
     * Check virtual or real path.
     *
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return bool
     */
    public function isVirtual($path): bool
    {
        $parts = explode(':', $path, 2);

        list($alias) = $parts;
        $alias = $this->cleanAlias($alias);
        if (!array_key_exists($alias, $this->paths) && $this->prefix($path) !== null) {
            return false;
        }

        return count($parts) === 2;
    }

    /**
     * Get path prefix.
     *
     * @param string $path (example: "C:\server\test.dev\file.txt")
     * @return null
     */
    public function prefix($path)
    {
        $path = $this->cleanPath($path);
        return preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }

    /**
     * Remove path from registered paths for source
     *
     * @param string       $fromSource (example: "default:file.txt")
     * @param string|array $paths
     * @return bool
     */
    public function remove($fromSource, $paths): bool
    {
        $paths = (array)$paths;
        $fromSource = $this->cleanSource($fromSource);
        list($alias) = $this->parse($fromSource);

        $return = false;

        foreach ($paths as $origPath) {
            $path = $this->_cleanPath($this->cleanPath($origPath));

            $key = array_search($path, $this->paths[$alias], true);
            if (false !== $key) {
                unset($this->paths[$alias][$key]);
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

        $this->root = $this->cleanPath($dir);
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
        $path = $this->getUrlPath($path, true);

        if (!empty($path)) {
            if (isset($details[1])) {
                $path .= '?' . $details[1];
            }

            $path = '/' . $path;
            return $full ? Url::root() . $path : $path;
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
        return FS::getRelative($fullpath, $this->root, '/');
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
            $list[$key] = FS::getRelative($item, $this->root, '/');
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
    protected function addNewPath($path, $alias, $mode)
    {
        if ($cleanPath = $this->_cleanPath($path)) {
            if ($mode === self::MOD_PREPEND) {
                array_unshift($this->paths[$alias], $cleanPath);
            }

            if ($mode === self::MOD_APPEND) {
                $this->paths[$alias][] = $cleanPath;
            }
        }
    }

    /**
     * Check root directory.
     *
     * @throws Exception
     */
    protected function checkRoot()
    {
        if ($this->root === null) {
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
    protected function find($paths, $file, $isGlob = false)
    {
        $paths = (array)$paths;
        $file = ltrim($file, "\\/");

        foreach ($paths as $path) {
            $fullPath = $this->clean($path . '/' . $file);

            if ($isGlob) {
                $paths = glob($fullPath, GLOB_BRACE);
                $paths = array_filter((array)$paths);
                return $paths ?: [];
            }

            if (file_exists($fullPath) || is_dir($fullPath)) {
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
            return $this->cleanPath($path);
        }

        if ($this->hasCDBack($path)) {
            $realpath = $this->cleanPath(realpath($path));
            return $realpath ?: null;
        }

        return $this->cleanPath($path);
    }

    /**
     * Get url path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param bool   $exitsFile
     * @return string
     * @throws Exception
     */
    protected function getUrlPath($path, $exitsFile = false): string
    {
        $this->checkRoot();

        $path = $this->_cleanPath($path);
        if ($this->isVirtual($path)) {
            $path = $this->get($path);
        }

        $subject = $path;
        $pattern = '/^' . preg_quote($this->root, '/') . '/i';

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
    protected function hasCDBack($path): int
    {
        $path = $this->cleanPath($path);
        return preg_match('(/\.\.$|/\.\./$)', $path);
    }

    /**
     * Parse source string.
     *
     * @param string $source (example: "default:file.txt")
     * @return array
     */
    protected function parse($source): array
    {
        $path = null;
        list($alias, $path) = explode(':', $source, 2);

        $path = ltrim($path, "\\/");
        $paths = $this->resolvePaths($alias);

        return [$alias, $paths, $path];
    }

    /**
     * @param string $alias
     * @return array
     */
    protected function resolvePaths($alias): array
    {
        if ($alias === 'root') {
            return [$this->getRoot()];
        }

        $alias = $this->cleanAlias($alias);

        $paths = $this->paths[$alias] ?? [];

        $result = [];
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

            $result[] = $this->getCurrentPath($path);
        }

        $result = array_filter($result); // remove empty
        $result = array_values($result); // reset keys

        return $result;
    }

    /**
     * Get current resolve path.
     *
     * @param string $path
     * @return string|null
     */
    protected function getCurrentPath($path)
    {
        return $this->isReal ? realpath($path) : $path;
    }

    /**
     * @param $alias
     * @return string
     */
    protected function cleanAlias($alias): string
    {
        $alias = preg_replace('/[^a-z0-9_\.-]/i', '', $alias);
        return $alias;
    }

    /**
     * @param string $source
     * @return string
     */
    protected function cleanSource($source): string
    {
        $source = $this->cleanAlias($source);
        $source .= ':';

        return $source;
    }

    /**
     * Forced clean path with linux-like sleshes
     *
     * @param string $path
     * @return string
     */
    protected function cleanPath($path): string
    {
        return FS::clean($path, '/');
    }
}
