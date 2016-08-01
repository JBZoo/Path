# JBZoo Path  [![Build Status](https://travis-ci.org/JBZoo/Path.svg?branch=master)](https://travis-ci.org/JBZoo/Path)      [![Coverage Status](https://coveralls.io/repos/JBZoo/Path/badge.svg?branch=master&service=github)](https://coveralls.io/github/JBZoo/Path?branch=master)

#### Virtual file system

[![License](https://poser.pugx.org/JBZoo/Path/license)](https://packagist.org/packages/JBZoo/Path)
[![Latest Stable Version](https://poser.pugx.org/JBZoo/Path/v/stable)](https://packagist.org/packages/JBZoo/Path) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/JBZoo/Path/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/JBZoo/Path/?branch=master)

### How to use

```php
require_once './vendor/autoload.php'; // composer autoload.php

//  Get needed classes.
use JBZoo\Path\Path;

//  Get path instance.
$path = Path::getInstance('default');

//  Setup root directory.
$path->setRoot(__DIR__);

//  Add paths.
$path->add(__DIR__ . '/styles/css', 'css');
$path->add(__DIR__ . '/simple/styles/css', 'css');

//  Add array paths.
$path->add(array(
    __DIR__ . 'styles/folder/less',
    __DIR__ . 'theme/styles/less',
), 'less');

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
echo $path->get('css:styles.css');           //  result: C:/Server/jbzoo/styles/css/styles.css
echo $path->get('less:path/to/styles.less'); //  result: C:/Server/jbzoo/styles/folder/less/path/to/styles.less

/**
 * Get url for the first file found, if file exits.
 * If - "C:/Server/jbzoo" is root dir we have...
 */
$path->url('css:styles.css');           //  http://my-site.com/styles/css/styles.css
$path->url('less:path/to/styles.less')  //  http://my-site.com/styles/css/folder/less/path/to/styles.less

echo '<link rel="stylesheet" href="' . $path->url('css:styles.css') . '">';

//  Clean path.
$path->clean('C:\server/folder\\\file.txt'); // result: 'C:/server/folder/file.txt'
$path->clean('path\\to//simple\\folder')    //  result: 'path/to/simple/folder'
```


## Unit tests and check code style
```sh
make
make test-all
```


## License

MIT
