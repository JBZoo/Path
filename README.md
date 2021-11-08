# JBZoo / Path

[![Coverage Status](https://coveralls.io/repos/JBZoo/Path/badge.svg)](https://coveralls.io/github/JBZoo/Path)    [![Psalm Coverage](https://shepherd.dev/github/JBZoo/Path/coverage.svg)](https://shepherd.dev/github/JBZoo/Path)    [![PHP Strict Types](https://img.shields.io/badge/strict__types-%3D1-brightgreen)](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict)    
[![Stable Version](https://poser.pugx.org/jbzoo/path/version)](https://packagist.org/packages/jbzoo/path)    [![Latest Unstable Version](https://poser.pugx.org/jbzoo/path/v/unstable)](https://packagist.org/packages/jbzoo/path)    [![Dependents](https://poser.pugx.org/jbzoo/path/dependents)](https://packagist.org/packages/jbzoo/path/dependents?order_by=downloads)    [![GitHub Issues](https://img.shields.io/github/issues/jbzoo/path)](https://github.com/JBZoo/Path/issues)    [![Total Downloads](https://poser.pugx.org/jbzoo/path/downloads)](https://packagist.org/packages/jbzoo/path/stats)    [![GitHub License](https://img.shields.io/github/license/jbzoo/path)](https://github.com/JBZoo/Path/blob/master/LICENSE)



Create memory based aliases for your project file system.


### Usage

```php
use JBZoo\Path\Path;

//  Get path instance.
$path = new Path();

//  Setup root directory.
$path->setRoot(__DIR__);

//  Add paths.
$path
    ->add(__DIR__ . '/styles/css', 'css')
    ->add(__DIR__ . '/simple/styles/css', 'css')
    //  Add array paths.
    ->add([
        __DIR__ . 'styles/folder/less',
        __DIR__ . 'theme/styles/less',
    ], 'less');

/**
 * Add paths by virtual.
 * If you already added at least one one way, you can use the virtual paths
 */
$path->add('less:assets/less');
$path->add('css:assets/less');

//  Get added path list by key.
var_dump($path->getPaths('css:'));
var_dump($path->getPaths('less:'));

/**
 * Get full path for the first file found, if file exits.
 */
echo $path->get('css:styles.css');           //  result: /jbzoo/styles/css/styles.css
echo $path->get('less:path/to/styles.less'); //  result: /jbzoo/styles/folder/less/path/to/styles.less

/**
 * Get url for the first file found, if file exits.
 * If - "C:/Server/jbzoo" is root dir we have...
 */
$path->url('css:styles.css');               //  http://my-site.com/styles/css/styles.css
$path->url('less:path/to/styles.less');     //  http://my-site.com/styles/css/folder/less/path/to/styles.less

echo '<link rel="stylesheet" href="' . $path->url('css:styles.css') . '">';

//  Clean path.
Path::clean('path\\to//simple\\folder');    //  result: 'path/to/simple/folder'
```

### Summary benchmark info (execution time)

See details [here](tests/phpbench/CompareWithRealpath.php)

subject | groups | its | revs | mean | stdev | rstdev | mem_real | diff
 --- | --- | --- | --- | --- | --- | --- | --- | --- 
benchBaseline |  | 3 | 10000 | 2.53μs | 0.11μs | 4.39% | 6,291,456b | 1.00x
benchNative |  | 3 | 10000 | 138.22μs | 0.46μs | 0.33% | 6,291,456b | 54.64x
benchJBZooPath |  | 3 | 10000 | 192.58μs | 0.87μs | 0.45% | 6,291,456b | 76.13x


## Unit tests and check code style
```sh
make update
make test-all
```


## License

MIT
