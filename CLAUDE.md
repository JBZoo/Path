# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

JBZoo Path is a PHP library for creating memory-based aliases for filesystem paths. It provides a virtual path system that allows mapping logical names to physical directories, enabling easier file access and URL generation.

### Core Architecture

- **Main Class**: `JBZoo\Path\Path` - The central class that manages path aliases and provides file access methods
- **Exception Handling**: `JBZoo\Path\Exception` - Custom exception class for the library
- **Namespace**: All classes are under `JBZoo\Path\` namespace
- **Dependencies**: Uses JBZoo Utils and Data libraries for utility functions

### Key Concepts

The library works by:
1. Setting a root directory
2. Adding path aliases (e.g., 'css' -> '/path/to/styles/css')
3. Resolving files using alias notation (e.g., 'css:styles.css')
4. Generating both filesystem paths and URLs

## Development Commands

### Installation/Updates
```bash
make update          # Install/update all dependencies
```

### Testing
```bash
make test           # Run PHPUnit tests
make test-all       # Run all tests including code style checks
make codestyle      # Run code style checks only
```

### Manual Testing
```bash
./vendor/bin/phpunit                    # Run all tests
./vendor/bin/phpunit tests/PathTest.php # Run specific test file
```

## Project Structure

```
src/
├── Path.php       # Main Path class with core functionality
└── Exception.php  # Custom exception class

tests/
├── PathTest.php              # Main test suite
├── PathPhpStormProxyTest.php # IDE integration tests
├── PathPackageTest.php       # Package validation tests
└── phpbench/                 # Performance benchmarks
```

## Development Notes

- **PHP Version**: Requires PHP 8.3+
- **Code Style**: Uses JBZoo codestyle standards (enforced via make codestyle)
- **Testing**: PHPUnit with coverage reporting to build/ directory
- **Dependencies**: Managed via Composer, uses JBZoo ecosystem libraries
- **Build System**: Makefile-based with JBZoo toolchain integration

The library includes extensive test coverage and performance benchmarks comparing against native PHP realpath() function.