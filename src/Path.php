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

declare(strict_types=1);

namespace JBZoo\Path;

use JBZoo\Utils\Arr;
use JBZoo\Utils\FS;
use JBZoo\Utils\Sys;
use JBZoo\Utils\Url;

use function JBZoo\Utils\int;

/**
 * Class Path
 * @package JBZoo\Path
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class Path
{
    // Minimal alias name length.
    public const MIN_ALIAS_LENGTH = 2;

    //Mod prepend rule add paths.
    public const MOD_PREPEND = 'prepend';

    // Mod append rule add paths.
    public const MOD_APPEND = 'append';

    // Reset all registered paths.
    public const MOD_RESET = 'reset';

    /**
     * Flag of result path (If true, is real path. If false, is relative path).
     *
     * @var bool
     */
    protected $isReal = true;

    /**
     * Holds paths list.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Root directory
     *
     * @var string|null
     */
    protected $root;

    /**
     * Path constructor.
     * @param string|null $root
     * @throws Exception
     */
    public function __construct(string $root = null)
    {
        $root = $root ?: Sys::getDocRoot();
        $this->setRoot($root);
    }

    /**
     * Register alias locations in file system.
     * Example:
     *      "default:file.txt" - if added at least one path and
     *      "C:\server\test.dev\fy-folder" or "C:\server\test.dev\fy-folder\..\..\"
     *
     * @param string       $alias
     * @param string|array $paths
     * @param string       $mode
     * @return $this
     *
     * @throws Exception
     */
    public function set(string $alias, $paths, string $mode = Path::MOD_PREPEND): self
    {
        $paths = (array)$paths;
        $alias = self::cleanAlias($alias);

        if (\strlen($alias) < self::MIN_ALIAS_LENGTH) {
            throw new Exception('The minimum number of characters is ' . self::MIN_ALIAS_LENGTH);
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

            $path = self::cleanPath($path);
            if ($path && !\in_array($path, $this->paths[$alias], true)) {
                if (\preg_match('/^' . \preg_quote($alias . ':', '') . '/i', $path)) {
                    throw new Exception("Added looped path \"{$path}\" to key \"{$alias}\"");
                }

                $this->addNewPath($path, $alias, $mode);
            }
        }

        return $this;
    }

    /**
     * Normalize and clean path.
     *
     * @param string $path ("C:\server\test.dev\file.txt")
     * @return string
     */
    public static function clean(string $path): string
    {
        $tokens = [];
        $cleanedPath = self::cleanPath($path);

        $prefix = (string)self::prefix($cleanedPath);
        $cleanedPath = (string)\substr($cleanedPath, \strlen($prefix));

        $parts = \array_filter(\explode('/', $cleanedPath), static function ($value) {
            return ($value);
        });

        foreach ($parts as $part) {
            if ('..' === $part) {
                \array_pop($tokens);
            } elseif ('.' !== $part) {
                $tokens[] = $part;
            }
        }

        return $prefix . \implode('/', $tokens);
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param string $source (example: "default:file.txt")
     * @return string|null
     * @throws Exception
     */
    public function get(string $source): ?string
    {
        $parsedSource = $this->parse($source);
        return self::find($parsedSource[1], $parsedSource[2]);
    }

    /**
     * Get absolute path to a file or a directory.
     *
     * @param string $source (example: "default:file.txt")
     * @return array|null
     * @throws Exception
     */
    public function glob(string $source): ?array
    {
        $parsedSource = $this->parse($source);
        return self::findViaGlob($parsedSource[1], $parsedSource[2]);
    }

    /**
     * Get all absolute path to a file or a directory.
     *
     * @param string $source (example: "default:file.txt")
     * @return array
     * @throws Exception
     */
    public function getPaths(string $source): array
    {
        $source = self::cleanSource($source);
        $parsedSource = $this->parse($source);
        return $parsedSource[1];
    }

    /**
     * Get root directory.
     * @return string|null
     */
    public function getRoot(): ?string
    {
        if (!$this->root) {
            throw new Exception('Please, set the root directory');
        }

        return $this->root;
    }

    /**
     * Setup real or relative path flag.
     *
     * @param bool $isReal
     * @return $this
     */
    public function setRealPathFlag(bool $isReal = true): self
    {
        $this->isReal = $isReal;
        return $this;
    }

    /**
     * Check virtual or real path.
     *
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @return bool
     */
    public function isVirtual(string $path): bool
    {
        $parts = \explode(':', $path, 2);

        [$alias] = $parts;
        $alias = self::cleanAlias($alias);
        if (!\array_key_exists($alias, $this->paths) && self::prefix($path) !== null) {
            return false;
        }

        $validNumberOfParts = 2;

        return \count($parts) === $validNumberOfParts;
    }

    /**
     * Get path prefix.
     *
     * @param string $path (example: "C:\server\test.dev\file.txt")
     * @return string|null
     */
    public static function prefix(string $path): ?string
    {
        $path = self::cleanPath($path);
        return \preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) ? $matches['prefix'] : null;
    }

    /**
     * Remove path from registered paths for source
     *
     * @param string       $fromSource (example: "default:file.txt")
     * @param string|array $paths
     * @return bool
     * @throws Exception
     */
    public function remove(string $fromSource, $paths): bool
    {
        $paths = (array)$paths;
        $fromSource = self::cleanSource($fromSource);
        [$alias] = $this->parse($fromSource);

        $return = false;

        foreach ($paths as $origPath) {
            $path = $this->cleanPathInternal(self::cleanPath($origPath));

            $key = \array_search($path, $this->paths[$alias], true);
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
     * @param string|null $newRootPath
     * @return $this
     *
     * @throws Exception
     */
    public function setRoot(?string $newRootPath): self
    {
        if (!$newRootPath || !\is_dir($newRootPath)) {
            throw new Exception("Not found directory: {$newRootPath}");
        }

        $this->root = self::cleanPath($newRootPath);

        return $this;
    }

    /**
     * Get url to a file.
     *
     * @param string $source (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     * @param bool   $isFullUrl
     * @return string|null
     * @throws Exception
     */
    public function url(string $source, bool $isFullUrl = true): ?string
    {
        $details = \explode('?', $source);
        if ($path = $this->cleanPathInternal($details[0] ?? '')) {
            $path = $this->getUrlPath($path, true);

            if (!empty($path)) {
                if (isset($details[1])) {
                    $path .= '?' . $details[1];
                }

                $path = '/' . $path;
                $root = Url::root();
                return $isFullUrl ? "{$root}{$path}" : $path;
            }
        }

        return null;
    }

    /**
     * Get relative path to file or directory
     *
     * @param string $source (example: "default:file.txt")
     * @return string|null
     * @throws Exception
     */
    public function rel(string $source): ?string
    {
        $fullpath = (string)$this->get($source);
        return FS::getRelative($fullpath, $this->root, '/');
    }

    /**
     * Get list of relative path to file or directory
     *
     * @param string $source (example: "default:*.txt")
     * @return array
     * @throws Exception
     */
    public function relGlob(string $source): array
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
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param string $alias
     * @param string $mode
     * @return $this
     */
    protected function addNewPath(string $path, string $alias, string $mode): self
    {
        if ($cleanPath = $this->cleanPathInternal($path)) {
            if ($mode === self::MOD_PREPEND) {
                \array_unshift($this->paths[$alias], $cleanPath);
            }

            if ($mode === self::MOD_APPEND) {
                $this->paths[$alias][] = $cleanPath;
            }
        }

        return $this;
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param string|array $paths
     * @param string       $file
     * @return string|null
     */
    protected static function find($paths, string $file): ?string
    {
        $paths = (array)$paths;
        $file = \ltrim($file, "\\/");

        foreach ($paths as $path) {
            $fullPath = self::clean($path . '/' . $file);

            if (\file_exists($fullPath) || \is_dir($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Find actual file or directory in the paths.
     *
     * @param array|string $paths
     * @param string       $file
     * @return array
     */
    protected static function findViaGlob($paths, string $file): array
    {
        $paths = (array)$paths;
        $file = \ltrim($file, "\\/");

        $path = Arr::first($paths);

        $fullPath = self::clean($path . '/' . $file);

        $paths = \glob($fullPath, \GLOB_BRACE);
        $paths = \array_filter((array)$paths);

        return $paths ?: [];
    }

    /**
     * Get add path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @return null|string
     */
    protected function cleanPathInternal(string $path): ?string
    {
        if ($this->isVirtual($path)) {
            return self::cleanPath($path);
        }

        if (self::hasCDBack($path)) {
            $realpath = self::cleanPath((string)\realpath($path));
            return $realpath ?: null;
        }

        return self::cleanPath($path);
    }

    /**
     * Get url path.
     *
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     * @param bool   $exitsFile
     * @return string|null
     * @throws Exception
     */
    protected function getUrlPath(string $path, bool $exitsFile = false): ?string
    {
        if (!$this->root) {
            throw new Exception('Please, set the root directory');
        }

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        if ($path = $this->cleanPathInternal($path)) {
            if ($this->isVirtual($path)) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $path = $this->get($path);
            }

            $subject = $path;
            $pattern = '/^' . \preg_quote($this->root, '/') . '/i';

            if ($path && $exitsFile && !$this->isVirtual($path) && !\file_exists($path)) {
                $subject = null;
            }

            return \ltrim((string)\preg_replace($pattern, '', (string)$subject), '/');
        }

        return null;
    }

    /**
     * Check has back current.
     *
     * @param string $path
     * @return int
     */
    protected static function hasCDBack(string $path): int
    {
        $path = self::cleanPath($path);
        return int(\preg_match('(/\.\.$|/\.\./$)', $path));
    }

    /**
     * Parse source string.
     *
     * @param string $source (example: "default:file.txt")
     * @return array
     * @throws Exception
     */
    protected function parse(string $source): array
    {
        [$alias, $path] = \explode(':', $source, 2);

        $path = \ltrim($path, "\\/");
        $paths = $this->resolvePaths($alias);

        return [$alias, $paths, $path];
    }

    /**
     * @param string $alias
     * @return array
     * @throws Exception
     */
    protected function resolvePaths(string $alias): array
    {
        if ($alias === 'root') {
            return [$this->getRoot()];
        }

        $alias = self::cleanAlias($alias);

        $paths = $this->paths[$alias] ?? [];

        $result = [];
        foreach ($paths as $originalPath) {
            if ($this->isVirtual($originalPath) && $realPath = $this->get($originalPath)) {
                $path = $realPath;
            } else {
                $path = $this->cleanPathInternal($originalPath);
            }

            $result[] = $this->getCurrentPath((string)$path);
        }

        $result = \array_filter($result); // remove empty
        $result = \array_values($result); // reset keys

        return $result;
    }

    /**
     * Get current resolve path.
     *
     * @param string $path
     * @return string|null
     */
    protected function getCurrentPath(string $path): ?string
    {
        return (string)($this->isReal ? \realpath($path) : $path) ?: null;
    }

    /**
     * @param string $alias
     * @return string
     */
    protected static function cleanAlias(string $alias): string
    {
        return (string)\preg_replace('/[^a-z0-9_\.-]/i', '', $alias);
    }

    /**
     * @param string $source
     * @return string
     */
    protected static function cleanSource(string $source): string
    {
        $source = self::cleanAlias($source);
        $source .= ':';

        return $source;
    }

    /**
     * Forced clean path with linux-like slashes
     *
     * @param string|null $path
     * @return string
     */
    protected static function cleanPath(?string $path): string
    {
        return FS::clean((string)$path, '/');
    }
}
