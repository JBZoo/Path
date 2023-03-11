<?php

/**
 * JBZoo Toolbox - Path.
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @see        https://github.com/JBZoo/Path
 */

declare(strict_types=1);

namespace JBZoo\Path;

use JBZoo\Utils\Arr;
use JBZoo\Utils\FS;
use JBZoo\Utils\Sys;
use JBZoo\Utils\Url;

use function JBZoo\Utils\int;
use function JBZoo\Utils\isStrEmpty;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class Path
{
    public const MIN_ALIAS_LENGTH = 2;

    // Modifiers adding rules
    public const MOD_PREPEND = 'prepend';
    public const MOD_APPEND  = 'append';
    public const MOD_RESET   = 'reset';

    /** Flag of result path (If true, is real path. If false, is relative path) */
    private bool $isReal = true;

    /** Holds paths list. */
    private array $paths = [];

    /** Root directory. */
    private ?string $root;

    public function __construct(?string $root = null)
    {
        $root = isStrEmpty($root) ? Sys::getDocRoot() : $root;
        $this->setRoot($root);
    }

    /**
     * Register alias locations in file system.
     * Example:
     *      "default:file.txt" - if added at least one path and
     *      "C:\server\test.dev\fy-folder" or "C:\server\test.dev\fy-folder\..\..\".
     */
    public function set(string $alias, array|string $paths, string $mode = self::MOD_PREPEND): self
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
            if ($path !== '' && !\in_array($path, $this->paths[$alias], true)) {
                if (\preg_match('/^' . \preg_quote($alias . ':', '') . '/i', $path) > 0) {
                    throw new Exception("Added looped path \"{$path}\" to key \"{$alias}\"");
                }

                $this->addNewPath($path, $alias, $mode);
            }
        }

        return $this;
    }

    /**
     * Get absolute path to a file or a directory.
     * @param string $source (example: "default:file.txt")
     */
    public function get(string $source): ?string
    {
        $parsedSource = $this->parse($source);

        return self::find($parsedSource[1], $parsedSource[2]);
    }

    /**
     * Get absolute path to a file or a directory.
     * @param string $source (example: "default:file.txt")
     */
    public function glob(string $source): ?array
    {
        $parsedSource = $this->parse($source);

        return self::findViaGlob($parsedSource[1], $parsedSource[2]);
    }

    /**
     * Get all absolute path to a file or a directory.
     * @param string $source (example: "default:file.txt")
     */
    public function getPaths(string $source): array
    {
        $source       = self::cleanSource($source);
        $parsedSource = $this->parse($source);

        return $parsedSource[1];
    }

    /**
     * Get root directory.
     */
    public function getRoot(): ?string
    {
        if ($this->root === null) {
            throw new Exception('Please, set the root directory');
        }

        return $this->root;
    }

    /**
     * Setup real or relative path flag.
     */
    public function setRealPathFlag(bool $isReal = true): self
    {
        $this->isReal = $isReal;

        return $this;
    }

    /**
     * Check virtual or real path.
     * @param string $path (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     */
    public function isVirtual(string $path): bool
    {
        $parts = \explode(':', $path, 2);

        [$alias] = $parts;
        $alias   = self::cleanAlias($alias);
        if (!\array_key_exists($alias, $this->paths) && self::prefix($path) !== null) {
            return false;
        }

        $validNumberOfParts = 2;

        return \count($parts) === $validNumberOfParts;
    }

    /**
     * Remove path from registered paths for source.
     * @param string $fromSource (example: "default:file.txt")
     */
    public function remove(string $fromSource, array|string $paths): bool
    {
        $paths      = (array)$paths;
        $fromSource = self::cleanSource($fromSource);
        [$alias]    = $this->parse($fromSource);

        $return = false;

        foreach ($paths as $origPath) {
            $path = $this->cleanPathInternal(self::cleanPath($origPath));

            $key = \array_search($path, $this->paths[$alias], true);
            if ($key !== false) {
                unset($this->paths[$alias][$key]);
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Setup root directory.
     */
    public function setRoot(?string $newRootPath): self
    {
        if ($newRootPath === '' || $newRootPath === null) {
            throw new Exception("New root path is empty: {$newRootPath}");
        }

        if (!\is_dir($newRootPath)) {
            throw new Exception("Directory not found: {$newRootPath}");
        }

        $this->root = self::cleanPath($newRootPath);

        return $this;
    }

    /**
     * Get url to a file.
     * @param string $source (example: "default:file.txt" or "C:\server\test.dev\file.txt")
     */
    public function url(string $source, bool $isFullUrl = true): ?string
    {
        $details = \explode('?', $source);

        $path = $this->cleanPathInternal($details[0] ?? '');

        if ($path !== '' && $path !== null) {
            $urlPath = $this->getUrlPath($path, true);

            if ($urlPath !== '' && $urlPath !== null) {
                if (isset($details[1])) {
                    $urlPath .= '?' . $details[1];
                }

                $urlPath = '/' . $urlPath;
                $root    = Url::root();

                return $isFullUrl ? "{$root}{$urlPath}" : $urlPath;
            }
        }

        return null;
    }

    /**
     * Get relative path to file or directory.
     * @param string $source (example: "default:file.txt")
     */
    public function rel(string $source): ?string
    {
        $fullpath = (string)$this->get($source);

        return FS::getRelative($fullpath, $this->root, '/');
    }

    /**
     * Get list of relative path to file or directory.
     * @param string $source (example: "default:*.txt")
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
     * Normalize and clean path.
     * @param string $path ("C:\server\test.dev\file.txt")
     */
    public static function clean(string $path): string
    {
        $tokens      = [];
        $cleanedPath = self::cleanPath($path);

        $prefix      = (string)self::prefix($cleanedPath);
        $cleanedPath = \substr($cleanedPath, \strlen($prefix));

        $parts = \array_filter(\explode('/', $cleanedPath), static fn ($value) => $value);

        foreach ($parts as $part) {
            if ($part === '..') {
                \array_pop($tokens);
            } elseif ($part !== '.') {
                $tokens[] = $part;
            }
        }

        return $prefix . \implode('/', $tokens);
    }

    /**
     * Get path prefix.
     * @param string $path (example: "C:\server\test.dev\file.txt")
     */
    public static function prefix(string $path): ?string
    {
        $path = self::cleanPath($path);

        return \preg_match('|^(?P<prefix>([a-zA-Z]+:)?//?)|', $path, $matches) > 0
            ? $matches['prefix']
            : null;
    }

    /**
     * Add path to hold.
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     */
    private function addNewPath(string $path, string $alias, string $mode): self
    {
        $cleanPath = $this->cleanPathInternal($path);

        if ($cleanPath !== null && $cleanPath !== '') {
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
     * Get add path.
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     */
    private function cleanPathInternal(string $path): ?string
    {
        if ($this->isVirtual($path)) {
            return self::cleanPath($path);
        }

        if (self::hasCDBack($path) > 0) {
            $realpath = self::cleanPath((string)\realpath($path));

            return $realpath !== '' ? $realpath : null;
        }

        return self::cleanPath($path);
    }

    /**
     * Get url path.
     * @param string $path (example: "default:file.txt" or "C:/Server/public_html/index.php")
     */
    private function getUrlPath(string $path, bool $exitsFile = false): ?string
    {
        if ($this->root === null || $this->root === '') {
            throw new Exception('Please, setup the root directory');
        }

        $path = $this->cleanPathInternal($path);
        if ($path !== null && $path !== '') {
            if ($this->isVirtual($path)) {
                $path = $this->get($path);
            }

            $subject = $path;
            $pattern = '/^' . \preg_quote($this->root, '/') . '/i';

            if (
                $path !== null
                && $path !== ''
                && $exitsFile
                && !$this->isVirtual($path)
                && !\file_exists($path)
            ) {
                $subject = null;
            }

            return \ltrim((string)\preg_replace($pattern, '', (string)$subject), '/');
        }

        return null;
    }

    /**
     * Parse source string.
     * @param string $source (example: "default:file.txt")
     */
    private function parse(string $source): array
    {
        $sourceParts = \explode(':', $source, 2);

        $alias = $sourceParts[0] ?? '';
        $path  = $sourceParts[1] ?? '';

        $path  = \ltrim($path, '\\/');
        $paths = $this->resolvePaths($alias);

        return [$alias, $paths, $path];
    }

    private function resolvePaths(string $alias): array
    {
        if ($alias === 'root') {
            return [$this->getRoot()];
        }

        $alias = self::cleanAlias($alias);

        $paths = $this->paths[$alias] ?? [];

        $result = [];

        foreach ($paths as $originalPath) {
            $realPath = $this->get($originalPath);
            if ($realPath !== null && $realPath !== '' && $this->isVirtual($originalPath)) {
                $path = $realPath;
            } else {
                $path = $this->cleanPathInternal($originalPath);
            }

            $result[] = $this->getCurrentPath((string)$path);
        }

        // remove empty && reset keys
        return \array_values(\array_filter($result));
    }

    /**
     * Get current resolve path.
     */
    private function getCurrentPath(string $path): ?string
    {
        $realpath    = \realpath($path);
        $realpath    = $realpath !== false ? $realpath : null;
        $currentPath = (string)($this->isReal ? $realpath : $path);

        return $currentPath !== '' ? $currentPath : null;
    }

    /**
     * Find actual file or directory in the paths.
     */
    private static function find(array|string $paths, string $file): ?string
    {
        $paths = (array)$paths;
        $file  = \ltrim($file, '\\/');

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
     */
    private static function findViaGlob(array|string $paths, string $file): array
    {
        $paths = (array)$paths;
        $file  = \ltrim($file, '\\/');

        $path = Arr::first($paths);

        $fullPath = self::clean($path . '/' . $file);

        $paths = \glob($fullPath, \GLOB_BRACE);

        return \array_filter((array)$paths);
    }

    /**
     * Check has back current.
     */
    private static function hasCDBack(string $path): int
    {
        $path = self::cleanPath($path);

        return int(\preg_match('(/\.\.$|/\.\./$)', $path));
    }

    private static function cleanAlias(string $alias): string
    {
        return (string)\preg_replace('/[^a-z0-9_.-]/i', '', $alias);
    }

    private static function cleanSource(string $source): string
    {
        $source = self::cleanAlias($source);
        $source .= ':';

        return $source;
    }

    /**
     * Forced clean path with linux-like slashes.
     */
    private static function cleanPath(?string $path): string
    {
        return FS::clean((string)$path, '/');
    }
}
