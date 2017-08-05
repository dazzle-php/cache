# Dazzle Async Cache

[![Build Status](https://travis-ci.org/dazzle-php/cache.svg)](https://travis-ci.org/dazzle-php/cache)
[![Code Coverage](https://scrutinizer-ci.com/g/dazzle-php/cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dazzle-php/cache/?branch=master)
[![Code Quality](https://scrutinizer-ci.com/g/dazzle-php/cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dazzle-php/cache/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/dazzle-php/cache/v/stable)](https://packagist.org/packages/dazzle-php/cache) 
[![Latest Unstable Version](https://poser.pugx.org/dazzle-php/cache/v/unstable)](https://packagist.org/packages/dazzle-php/cache) 
[![License](https://poser.pugx.org/dazzle-php/cache/license)](https://packagist.org/packages/dazzle-php/cache/license)

> **Note:** This repository is part of [Dazzle Project](https://github.com/dazzle-php/dazzle) - the next-gen library for PHP. The project's purpose is to provide PHP developers with a set of complete tools to build functional async applications. Please, make sure you read the attached README carefully and it is guaranteed you will be surprised how easy to use and powerful it is. In the meantime, you might want to check out the rest of our async libraries in [Dazzle repository](https://github.com/dazzle-php) for the full extent of Dazzle experience.

<br>
<p align="center">
<img src="https://raw.githubusercontent.com/dazzle-php/dazzle/master/media/dazzle-x125.png" />
</p>

## Description

TODO

## Feature Highlights

Dazzle Cache features:

TODO

## Provided Example(s)

### Quickstart

This is simple example of setting and getting values from cache.

```
$loop  = new Loop();
$cache = new Cache($loop);

$cache->start()->then(function(CacheInterface $cache) {

    $chain = new Promise();
    $chain = $chain->then(function() use($cache) {
        return $cache->set('SOME_KEY', "DAZZLE IS AWESOME\n");
    });
    $chain = $chain->then(function() use($cache) {
        return $cache->get('SOME_KEY');
    });
    $chain = $chain->then(function($result) {
        printf("%s", $result);
        return $cache->end();
    });
    return $chain;
});

$loop->start();
```

### Additional

TODO

## Comparison

This section contains Dazzle vs React comparison many users requested. If you are wondering why this section has been created, see the author note at the end of it.

#### Performance

TODO

#### Details

TODO

#### Note from the author

> Few years ago, whenever I needed async tools in PHP, I was actively using other, hugely popular php library called React. Back then it was mind-blowing experience for me and I was astonished how easy it was to simulate async behaviour in PHP. I started to trust this aproach more and more and began to use it in more complicated projects. However, the bigger the project I was working on was, the more defects I was able to find. Its code, in my experience, suffered from uneven performance, leaking memory, the occasional bugs and what had upset me most - lacking interfaces which focused only on async side of things, ignoring functionality of its components as a whole. I started to write my own extensions for the library, including missing boilerplate and fixes needed. I wanted to share that with the community, created PRs with some of them, but they were never approved or rejected. React project was dead at that time, but in fact, I still needed those tools. That prompted me to create Dazzle Project. It was designed as modern, more reliable and more complete replacement for React library. Although I hold React library dear to my heart up to this day, I believe I was able to achieve that goal perfectly. Since the first day Dazzle was published I received many requests to include comparisons and benchmarks that proves the previous statement. That's why this section has been attached to the README. I hope the readers will be able to find all the necessary pieces of information they are looking for in it.

## Requirements

Dazzle Cache requires:

* PHP-5.6 or PHP-7.0+,
* UNIX or Windows OS.

## Installation

To install this library make sure you have [composer](https://getcomposer.org/) installed, then run following command:

```
$> composer require dazzle-php/cache
```

## Tests

Tests can be run via:

```
$> vendor/bin/phpunit -d memory_limit=1024M
```

## Versioning

Versioning of Dazzle libraries is being shared between all packages included in [Dazzle Project](https://github.com/dazzle-php/dazzle). That means the releases are being made concurrently for all of them. On one hand this might lead to "empty" releases for some packages at times, but don't worry. In the end it is far much easier for contributors to maintain and -- what's the most important -- much more straight-forward for users to understand the compatibility and inter-operability of the packages.

## Contributing

Thank you for considering contributing to this repository! 

- The contribution guide can be found in the [contribution tips](https://github.com/dazzle-php/cache/blob/master/CONTRIBUTING.md). 
- Open tickets can be found in [issues section](https://github.com/dazzle-php/cache/issues). 
- Current contributors are listed in [graphs section](https://github.com/dazzle-php/cache/graphs/contributors)
- To contact the author(s) see the information attached in [composer.json](https://github.com/dazzle-php/cache/blob/master/composer.json) file.

## License

Dazzle Cache is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

<hr>
<p align="center">
<i>"Everything is possible. The impossible just takes longer."</i> ― Dan Brown
</p>
