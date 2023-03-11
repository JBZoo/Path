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

use JBZoo\Path\Path;
use JBZoo\Utils\FS;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @BeforeMethods({"init"})
 * @Revs(10000)
 * @Iterations(3)
 */
class CompareWithRealpath
{
    private string $root;

    private Filesystem $fs;

    public function init(): void
    {
        $this->fs   = new Filesystem();
        $this->root = FS::clean(__DIR__ . '/test', '/');

        $this->fs->remove($this->root);
    }

    public function benchBaseline(): bool|string
    {
        return \realpath($this->root . '/' . \mt_rand());
    }

    public function benchNative(): bool|string
    {
        $newDir = $this->root . \mt_rand();
        $this->fs->mkdir($newDir);

        // start
        $result = \realpath($newDir);
        // end

        $this->fs->remove($result);

        return $result;
    }

    public function benchJBZooPath(): ?string
    {
        $newDir = $this->root . \mt_rand();
        $this->fs->mkdir($newDir);

        // start
        $virtualPath = new Path();
        $virtualPath->set('default', $newDir);
        $result = $virtualPath->get('default:');
        // end

        $this->fs->remove($result);

        return $result;
    }
}
