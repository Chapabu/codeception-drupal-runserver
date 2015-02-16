# Codeception Drupal Drush Server

## Note: This is currently under development, but should be ready shortly...it ain't ready til it's got tests ;-)

Codeception extension for starting and stopping a Drush server using PHP's built in webserver and the `drush runserver` command.

## Requirements

* Drush
* PHP 5.4 (the Drush server does support PHP 5.3, but I'm not likely to actively test this. Pull requests welcome if this is required :))

## Installation

Via Composer

``` bash
$ composer require chapabu/codeception-drupal-runserver --dev
```
## Usage

``` yaml
paths:
    tests: .
    log: _log
    data: _data
    helpers: _helpers
extensions:
    enabled:
        - Codeception\Extension\DrushRunserver
    config:
        Codeception\Extension\DrushRunserver:
            drushBinary: ../vendor/bin/drush
            hostname: 127.0.0.1
            port: 8080
            variables:
                site_name: My cool site
                theme_default: my_awesome_theme
                site_mail: admin@example.com
```

### Configuration options

#### drushBinary

``` yaml
drushBinary: ../vendor/bin/drush
```

The path to the Drush binary on your system (default: `drush` - as if it were installed globally).

#### hostname

``` yaml
hostname: 127.0.0.1
```

The address to bind to the server (default: `127.0.0.1`).

#### port

``` yaml
port: 8080
````

The port number to bind to the server (default: `8888`).

#### variables

``` yaml
variables:
    site_name: My cool site
    theme_default: my_awesome_theme
    site_mail: admin@example.com
```

A key-value array of variables to override in the`$conf` array for the running site.

## Testing

``` bash
$ codecept run
```

## Credits

Most of the code for this was lifted from https://github.com/tiger-seo/PhpBuiltinServer, but rejigged to use the `drush runserver` command. 

## License 

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
