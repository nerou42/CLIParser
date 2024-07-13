# CLI Parser

[![License](https://poser.pugx.org/nerou/cli-parser/license)](https://packagist.org/packages/nerou/cli-parser)
[![PHP Version Require](https://poser.pugx.org/nerou/cli-parser/require/php)](https://packagist.org/packages/nerou/cli-parser)
[![Version](https://poser.pugx.org/nerou/cli-parser/version)](https://packagist.org/packages/nerou/cli-parser)
[![Psalm Type Coverage](https://shepherd.dev/github/nerou42/CLIParser/coverage.svg)](https://packagist.org/packages/nerou/cli-parser)

This project is build around a slightly modified version of [this fairly old comment on php.net](https://www.php.net/manual/en/features.commandline.php#83843).

## This is for you, if...

...you are creating CLI applications with PHP, that take some command line arguments, options, commands and/or flags.

## Install

Note: This library requires PHP 8.0+!

Use composer to install this library:

`composer require nerou/cli-parser`

There are no dependencies.

## Usage

### Wording

**Command** is just some value, e.g. `myscript.php hello`

**Option** starts with `--` and can have a value, e.g. `myscript.php --foo` or `myscript.php --foo=bar` or `myscript.php --foo bar`

**Flag** starts with `-` and is a short form of an option, e.g. `myscript.php -f` or `myscript.php -f bar`

**Argument** is everything following a standalone `--`

### Examples

Minimal example with options `--foo` and `--bar` as well as the flag `-f` which is a short form of `--foo`:

```php
if(PHP_SAPI !== 'cli' || !isset($_SERVER['argv'])){
  exit(1);          // exit if not run via CLI
}

$cliArgs = new CLIParser($_SERVER['argv']);
$cliArgs->setAllowedOptions(['foo', 'bar']);    // list of supported options
$cliArgs->setAllowedFlags(['f' => 'foo']);      // maps flags to options
$cliArgs->setStrictMode(true);                  // parse() will return `false` if there are options/flags that are not allowed
if(!$cliArgs->parse()){
  printUsage();     // show them how to use this script
  exit(1);
}
```

Use value validation (see [PHP filters](https://www.php.net/manual/en/filter.filters.php)):

```php
$cliArgs->setAllowedOptions([
    'foo' => [
        'filter' => FILTER_VALIDATE_FLOAT,
        'flags' => FILTER_FLAG_ALLOW_THOUSAND,
        'options' => [
            'min_range' => 0
        ]
    ], 
    'bar' => []     // defaults to `['filter' => FILTER_DEFAULT]`
]);
```

## Limitations and concerns

- using a single option multiple times is not supported, e.g. `phpcpd src --exclude src/foo --exclude src/bar`

## License

This library is licensed under the MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
