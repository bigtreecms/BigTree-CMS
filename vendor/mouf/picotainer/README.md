Picotainer
==========
[![Latest Stable Version](https://poser.pugx.org/mouf/picotainer/v/stable.svg)](https://packagist.org/packages/mouf/picotainer)
[![Latest Unstable Version](https://poser.pugx.org/mouf/picotainer/v/unstable.svg)](https://packagist.org/packages/mouf/picotainer)
[![License](https://poser.pugx.org/mouf/picotainer/license.svg)](https://packagist.org/packages/mouf/picotainer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/picotainer/badges/quality-score.png?b=1.0)](https://scrutinizer-ci.com/g/thecodingmachine/picotainer/?branch=1.0)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/3ac43eac-dcec-496a-9e0f-5fe82f8b3824/mini.png)](https://insight.sensiolabs.com/projects/3ac43eac-dcec-496a-9e0f-5fe82f8b3824)
[![Build Status](https://travis-ci.org/thecodingmachine/picotainer.svg?branch=1.0)](https://travis-ci.org/thecodingmachine/picotainer)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/picotainer/badge.svg?branch=1.0)](https://coveralls.io/r/thecodingmachine/picotainer?branch=1.0)

This package contains a really minimalist dependency injection container (24 lines of code!) compatible with
[container-interop](https://github.com/container-interop/container-interop) (supports ContainerInterface and
delegate lookup feature).  It is also, therefore, compatible with
[PSR-11](https://github.com/php-fig/fig-standards/blob/master/proposed/container.md), the FIG container standard.

Picotainer is heavily influenced by the [Pimple DI container](http://pimple.sensiolabs.org/). Think about it
as a Pimple container with even less features, and ContainerInterop compatibility.

Installation
------------

Before using Picotainer in your project, add it to your `composer.json` file:

```
$ ./composer.phar require mouf/picotainer ~1.0
```


Storing entries in the container
--------------------------------

Creating a container is a matter of creating a `Picotainer` instance.
The `Picotainer` class takes 2 parameters:

- the list of entries, as an **array of anonymous functions**
- an optional [delegate-lookup container](https://github.com/container-interop/container-interop/blob/master/docs/Delegate-lookup.md)

```php
use Mouf\Picotainer\Picotainer;
use Psr\Container\ContainerInterface;

$container = new Picotainer([
	"myInstance"=>function(ContainerInterface $container) {
		return new MyInstance();
	},
	"myOtherInstance"=>function(ContainerInterface $container) {
		return new MyOtherInstance($container->get('myInstance'));
	}
	"myParameter"=>function(ContainerInterface $container) {
		return MY_CONSTANT;
	}
], $rootContainer);
```

The list of entries is an associative array.

- The key is the name of the entry in the container
- The value is an **anonymous function** that will return the entry

The entry can be anything (an object, a scalar value, a resource, etc...)

The **anonymous function** must accept one parameter: the container on which dependencies will be fetched.
The container is the "delegate-lookup container" if it was passed as the second argument of the constructor,
or the Picotainer instance itself if no delegate lookup container was passed.


Fetching entries from the container
-----------------------------------

Fetching entries from the container is as simple as calling the `get` method:

```php
$myInstance = $container->get('myInstance');
```

Why the need for this package?
------------------------------

This package is part of a long-term effort to bring [interoperability between DI containers](https://github.com/container-interop/container-interop). The ultimate goal is to
make sure that multiple containers can communicate together by sharing entries (one container might use an entry from another
container, etc...)
