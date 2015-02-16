# Codeception Drupal Drush Server

Codeception extension for starting and stopping a Drush server using PHP's built in webserver and the `drush runserver` command.

## Requirements

* Drush
* PHP 5.4 (the Drush server does support PHP 5.3, but I'm not likely to actively test this. Pull requests welcome if this is required :))

## Installation

Via Composer

``` bash
$ composer require chapabu/codeception-drupal-runserver --dev
```

## Testing

``` bash
$ codecept run
```

## Credits

Most of the code for this was lifted from https://github.com/tiger-seo/PhpBuiltinServer, but rejigged to use the `drush runserver` command. 

## License 

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
