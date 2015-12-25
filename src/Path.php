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

namespace JBZoo\Path;

use JBZoo\Utils\FS;

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
     * @var
     */
    protected $_root;

    /**
     * Setup root directory.
     *
     * @param $dir
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
     * Get root directory.
     *
     * @return mixed
     * @throws Exception
     */
    public function getRoot()
    {
        if (!isset($this->_root)) {
            throw new Exception(sprintf('The root directory has not been set'));
        }

        return $this->_root;
    }

    /**
     * Register package locations in file system.
     *
     * @param $paths
     * @param string $package
     * @param string $mode
     */
    public function register($paths, $package = Path::DEFAULT_PACKAGE, $mode = Path::PREPEND)
    {
        $paths = (array) $paths;

        if ($mode === self::RESET) {
            $this->_paths[$package] = $paths;
            return;
        }

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
     * Remove path from registered paths.
     *
     * @param $source (example: "default:file.txt")
     * @param $key
     * @return bool
     */
    public function remove($source, $key)
    {
        $keys = (array) $key;
        list($package) = $this->parse($source);

        $return = false;
        if (isset($this->_paths[$package]) && is_array($this->_paths[$package]) && !empty($keys)) {
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
     * @param $path
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
     * @param $source (example: "default:file.txt")
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
     * @param $path
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
     * @param $path
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
     * @param $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @return string
     */
    public function relative($path)
    {
        $root    = preg_quote(FS::clean($this->_root, '/'), '/');
        $subject = FS::clean($path, '/');
        $pattern = '/^' . $root . '/i';

        if ($this->isVirtual($path)) {
            $path    = FS::clean($this->get($path), '/');
            $subject = $path;
        }

        return ltrim(preg_replace($pattern, '', $subject), '/');
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param $paths
     * @param $file
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
     * @param $path
     * @param $package
     * @param $mode
     * @return void
     */
    protected function _add($path, $package, $mode)
    {
        if ($mode == self::PREPEND) {
            array_unshift($this->_paths[$package], $path);
        }

        if ($mode == self::APPEND) {
            array_push($this->_paths[$package], $path);
        }
    }
}
