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
     * @var string
     */
    const DEFAULT_PACKAGE = 'default';

    /**
     * Prepend rule add paths.
     * @var string
     */
    const PREPEND = 'prepend';

    /**
     * Append rule add paths.
     * @var string
     */
    const APPEND = 'append';

    /**
     * Reset all registered paths.
     * @var bool
     */
    const RESET = true;

    /**
     * Holds paths list.
     * @var array
     */
    protected $_paths = array();

    /**
     * Register package locations in file system.
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

            if ($mode == self::PREPEND) {
                array_unshift($this->_paths[$package], $path);
            }

            if ($mode == self::APPEND) {
                array_push($this->_paths[$package], $path);
            }
        }
    }

    /**
     * Parse source path.
     * @param $source
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
     * Get paths by package name.
     * @param string $package
     * @return null
     */
    public function getPaths($package = Path::DEFAULT_PACKAGE)
    {
        if (isset($this->_paths[$package])) {
            return $this->_paths[$package];
        }

        return null;
    }

    /**
     * Check virtual or real path.
     * @param $path
     * @return bool
     */
    public function isVirtual($path)
    {
        if ($this->prefix($path)) {
            return false;
        }

        $parts = explode(':', $path, 2);
        return (count($parts) == 2) ? true : false;
    }

    /**
     * Get path prefix.
     * @param $path
     * @return null
     */
    public function prefix($path)
    {
        $path = FS::clean($path, '/');
        return preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }
}
