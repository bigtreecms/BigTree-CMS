# pQuery

[![Build Status](https://img.shields.io/travis/tburry/pquery.svg?style=flat)](https://travis-ci.org/tburry/pquery)
[![Coverage](https://img.shields.io/scrutinizer/coverage/g/tburry/pquery.svg?style=flat)](https://scrutinizer-ci.com/g/tburry/pquery/)
[![Latest Stable Version](http://img.shields.io/packagist/v/tburry/pquery.svg?style=flat)](https://packagist.org/packages/tburry/pquery)

pQuery is a jQuery like html dom parser written php. It is a fork of the [ganon dom parser](https://code.google.com/p/ganon/).

## Basic usage

To get started using pQuery do the following.

1. Require the pQuery library into your project using [composer](http://getcomposer.org/doc/01-basic-usage.md#the-require-key).
2. Parse a snippet of html using `pQuery::parseStr()` or `pQuery::parseFile()` to return a document object model (DOM).
3. Run jQuery like functions on the DOM.

## Example

The following example parses an html string and does some manipulation on it.

```php
$html = '<div class="container">
  <div class="inner verb">Hello</div>
  <div class="inner adj">Cruel</div>
  <div class="inner obj">World</div>
</div>';

$dom = pQuery::parseStr($html);

$dom->query('.inner')
    ->tagName('span');

$dom->query('.adj')
    ->html('Beautiful')
    ->tagName('i');

echo $dom->html();
```

## Differences between pQuery and ganon

pQuery is a fork of the [ganon php processor](https://code.google.com/p/ganon/). Most of the functionality is identical to ganon with the following exceptions.

* pQuery is a composer package.
* pQuery renames ganon's classes and puts them into a namespace.
* pQuery is used only with objects rather than functions so that it can be autoloaded.
* pQuery Adds the `IQuery` interface and the `pQuery` object that define the jQuery-like interface for querying the dom.
* pQuery implements more of jQuery's methods. See the `IQuery` interface for a list of methods.
* pQuery supports adding tags to the dom using the `<div class="something"></div>` notation rather than just `div`.