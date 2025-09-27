# JBZoo / Path

[![CI](https://github.com/JBZoo/Path/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/JBZoo/Path/actions/workflows/main.yml?query=branch%3Amaster)    [![Coverage Status](https://coveralls.io/repos/github/JBZoo/Path/badge.svg?branch=master)](https://coveralls.io/github/JBZoo/Path?branch=master)    [![Psalm Coverage](https://shepherd.dev/github/JBZoo/Path/coverage.svg)](https://shepherd.dev/github/JBZoo/Path)    [![Psalm Level](https://shepherd.dev/github/JBZoo/Path/level.svg)](https://shepherd.dev/github/JBZoo/Path)    [![CodeFactor](https://www.codefactor.io/repository/github/jbzoo/path/badge)](https://www.codefactor.io/repository/github/jbzoo/path/issues)
[![Stable Version](https://poser.pugx.org/jbzoo/path/version)](https://packagist.org/packages/jbzoo/path/)    [![Total Downloads](https://poser.pugx.org/jbzoo/path/downloads)](https://packagist.org/packages/jbzoo/path/stats)    [![Dependents](https://poser.pugx.org/jbzoo/path/dependents)](https://packagist.org/packages/jbzoo/path/dependents?order_by=downloads)    [![GitHub License](https://img.shields.io/github/license/jbzoo/path)](https://github.com/JBZoo/Path/blob/master/LICENSE)

A PHP library for creating memory-based aliases for your project file system. This virtual path system allows you to map logical names to physical directories, making file access and URL generation much easier and more maintainable.

## Features

- 🚀 **Virtual Path Mapping**: Create logical aliases for filesystem paths
- 🔗 **URL Generation**: Automatically generate URLs from file paths
- 📁 **Multiple Path Support**: Add multiple directories under the same alias
- 🔍 **File Resolution**: Find files across multiple registered paths
- 🧹 **Path Normalization**: Clean and normalize file paths
- ⚡ **Performance Optimized**: Faster than native PHP realpath() in many scenarios

## Requirements

- PHP 8.2 or higher
- Composer for installation

## Installation

```bash
composer require jbzoo/path
```


## Quick Start

### Basic Usage

```php
<?php

use JBZoo\Path\Path;

// Create path instance
$path = new Path();

// Set root directory
$path->setRoot(__DIR__);

// Add path aliases
$path
    ->add(__DIR__ . '/assets/css', 'css')
    ->add(__DIR__ . '/assets/js', 'js')
    ->add([
        __DIR__ . '/themes/default/css',
        __DIR__ . '/themes/custom/css'
    ], 'theme-css');

// Find files using aliases
echo $path->get('css:main.css');        // Returns: /project/assets/css/main.css
echo $path->get('js:app.js');           // Returns: /project/assets/js/app.js

// Generate URLs (if root is web accessible)
echo $path->url('css:main.css');        // Returns: https://example.com/assets/css/main.css

// Clean and normalize paths
echo Path::clean('path\\to//file.txt'); // Returns: 'path/to/file.txt'
```

### Advanced Examples

```php
// Add virtual paths (extending existing aliases)
$path->add('css:vendor/bootstrap');
$path->add('css:vendor/fontawesome');

// Get all registered paths for an alias
$cssPaths = $path->getPaths('css:');
// Returns array of all CSS directories

// Check if file exists in any registered path
if ($path->get('css:custom.css')) {
    echo '<link rel="stylesheet" href="' . $path->url('css:custom.css') . '">';
}

// Multiple file types under one alias
$path->add([
    __DIR__ . '/public/images',
    __DIR__ . '/uploads/images',
    __DIR__ . '/cache/optimized'
], 'images');

// Will search in all image directories
$logoPath = $path->get('images:logo.png');
```

## Performance

JBZoo Path provides excellent performance compared to native PHP functions. See detailed benchmarks [here](tests/phpbench/CompareWithRealpath.php).

| Subject | Groups | Iterations | Revisions | Mean | StdDev | RStdDev | Memory | Performance |
|---------|--------|------------|-----------|------|--------|---------|--------|-------------|
| benchBaseline | | 3 | 10000 | 2.53μs | 0.11μs | 4.39% | 6,291,456b | 1.00x |
| benchNative | | 3 | 10000 | 138.22μs | 0.46μs | 0.33% | 6,291,456b | 54.64x |
| benchJBZooPath | | 3 | 10000 | 192.58μs | 0.87μs | 0.45% | 6,291,456b | 76.13x |

## Development

### Running Tests

```bash
# Install/update dependencies
make update

# Run all tests and code style checks
make test-all

# Run only PHPUnit tests
make test

# Run only code style checks
make codestyle
```

### Project Structure

```
src/
├── Path.php       # Main Path class
└── Exception.php  # Custom exception class

tests/
├── PathTest.php              # Main test suite
├── PathPhpStormProxyTest.php # IDE integration tests
├── PathPackageTest.php       # Package validation tests
└── phpbench/                 # Performance benchmarks
```


## License

MIT
