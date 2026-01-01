# Env Resolver

[![Automated Testing](https://github.com/Aeliot-Tm/env-resolver/actions/workflows/automated_testing.yml/badge.svg?branch=main)](https://github.com/Aeliot-Tm/env-resolver/actions/workflows/automated_testing.yml?query=branch%3Amain)
[![Security Audit](https://github.com/Aeliot-Tm/env-resolver/actions/workflows/security-audit.yaml/badge.svg?branch=main)](https://github.com/Aeliot-Tm/env-resolver/actions/workflows/security-audit.yaml?query=branch%3Amain)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A powerful PHP library for resolving environment variables, constants, files, and other data sources using a flexible chain of modifiers. Inspired by Symfony's env processors but works standalone without framework dependencies.

## Features

- **Chain modifiers** - Combine multiple transformations: `json:base64:env:MY_VAR`
- **Nested placeholders** - Support for `%env(file:%env(PATH_VAR)%)%`
- **Escaping** - Use `%%` to output literal `%` characters
- **Multiple data sources** - Environment variables, constants, files, PHP includes
- **Type casting** - Convert to int, float, bool, string, JSON, CSV
- **Encoding/Decoding** - Base64, URL encoding, query string parsing
- **Extensible** - Custom post-processors for resolved values

## Installation

```bash
composer require aeliot/env-resolver
```

## Requirements

- PHP 8.2 or higher

## Quick Start

### String Processing with Placeholders

```php
use Aeliot\EnvResolver\Service\StringProcessor;

$processor = new StringProcessor();

$_ENV['HOST'] = 'localhost';
$_ENV['PORT'] = '5432';

$config = 'postgres://%env(HOST)%:%env(PORT)%/database';
$result = $processor->process($config);
// Result: 'postgres://localhost:5432/database'
```


It allows to process entire config:

```php
use Aeliot\EnvResolver\Service\StringProcessor;

$preparedConfig = (new StringProcessor())->process(file_get_contents('/path/to/config.yaml'));
// Result: all instructions '%env(MY_ENV_VAR)%' resolved to <my_env_var_value> 
```

## Modifiers

Modifiers are chained using `:` separator and processed from right to left.

### Data Sources

| Modifier | Description | Example |
|----------|-------------|---------|
| `env` | Environment variable (default) | `env:MY_VAR` or just `MY_VAR` |
| `const` | PHP constant | `const:MY_CONST` |
| `file` | File contents | `file:/path/to/file.txt` |
| `require` | PHP file return value | `require:/path/to/config.php` |
| `direct` | Direct/literal value | `direct:my_value` |

### Type Casting

| Modifier | Description | Example |
|----------|-------------|---------|
| `int` | Cast to integer | `int:MY_VAR` |
| `float` | Cast to float | `float:MY_VAR` |
| `bool` | Cast to boolean | `bool:MY_VAR` |
| `not` | Negate boolean | `not:bool:MY_VAR` |
| `string` | Cast to string | `string:float:MY_VAR` |

### Encoding/Decoding

| Modifier | Description | Example |
|----------|-------------|---------|
| `base64` | Base64 decode | `base64:MY_VAR` |
| `json` | JSON decode | `json:MY_VAR` |
| `query_string` | Parse query string | `query_string:MY_VAR` |
| `strcsv` | Parse CSV string | `strcsv:MY_VAR` |
| `url` | Parse URL into components | `url:MY_VAR` |
| `urlencode` | URL encode | `urlencode:MY_VAR` |

### String Operations

| Modifier | Description | Example |
|----------|-------------|---------|
| `trim` | Trim whitespace | `trim:MY_VAR` |

### Complex Types

| Modifier | Description | Example |
|----------|-------------|---------|
| `key` | Get array key | `key:username:json:MY_VAR` |
| `enum` | Convert to BackedEnum | `enum:App\Status:MY_VAR` |

## Examples


### Basic Usage

```php
use Aeliot\EnvResolver\Service\Resolver;

$resolver = new Resolver();

// Simple environment variable
$_ENV['DATABASE_URL'] = 'mysql://localhost/mydb';
$value = $resolver->resolve('DATABASE_URL');
// Result: 'mysql://localhost/mydb'

// Explicit env modifier
$value = $resolver->resolve('env:DATABASE_URL');
// Result: 'mysql://localhost/mydb'
```

### Chained Modifiers

```php
// Base64 encoded JSON in environment variable
$_ENV['CONFIG'] = 'eyJkYiI6Im15c3FsIiwicG9ydCI6MzMwNn0='; // {"db":"mysql","port":3306}

$resolver = new Resolver();

// Decode base64, then parse JSON, then get 'port' key, then cast to int
$port = $resolver->resolve('int:key:port:json:base64:CONFIG');
// Result: 3306 (integer)
```

### Nested Placeholders

```php
$_ENV['CONFIG_FILE_PATH'] = '/etc/myapp/database.conf';

$processor = new StringProcessor();

// Inner placeholder resolves first, then outer uses the result
$config = '%env(file:%env(CONFIG_FILE_PATH)%)%';
$result = $processor->process($config);
// Result: contents of /etc/myapp/database.conf
```

### Escaping Percent Signs

```php
$processor = new StringProcessor();

$_ENV['DISCOUNT'] = '25';

$result = $processor->process('Get %env(DISCOUNT)%%% off!');
// Result: 'Get 25% off!'

// Double %% becomes single %
$result = $processor->process('100%% complete');
// Result: '100% complete'

// Escape entire placeholder
$result = $processor->process('Use %%env(VAR)%% syntax');
// Result: 'Use %env(VAR)% syntax'
```

### Custom Post-Processor

```php
$processor = new StringProcessor();

$_ENV['POSITION_CONFIG'] = 'eyJwYXRoIjoiL3BhdGgvdG8vZmlsZSIsImxpbmUiOiAxMjd9';

// Default: non-scalar values are JSON encoded
$result = $processor->process('%env(json:base64:POSITION_CONFIG)%');
// Result: '{"path":"/path/to/file","line": 127}'

// Custom: join array elements
$result = $processor->process(
    '%env(json:base64:direct:POSITION_CONFIG)%',
    static function (mixed $value, string $heap): string {
        if ('json:base64:direct:POSITION_CONFIG' === $heap){
            $value = implode(', ', $value);
        }
        return \is_string($value) ? $value : json_encode($value, \JSON_THROW_ON_ERROR);
    }
);
// Result: '/path/to/file:127'
```

Similarly, you may return tilda (`~`) for `null`-values when process YAML.

### Reading from Files

```php
$resolver = new Resolver();

// Read file contents
$content = $resolver->resolve('file:/etc/ssl/certs/ca.pem');

// Read file path from env, then read file
$_ENV['CERT_PATH'] = '/etc/ssl/certs/ca.pem';
$content = $resolver->resolve('file:env:CERT_PATH');

// Require PHP file that returns a value
$config = $resolver->resolve('require:/path/to/config.php');
```

### URL Parsing

```php
$_ENV['DATABASE_URL'] = 'mysql://user:pass@localhost:3306/mydb?charset=utf8';

$resolver = new Resolver();

$params = $resolver->resolve('url:DATABASE_URL');
// Result: [
//     'scheme' => 'mysql',
//     'host' => 'localhost',
//     'port' => 3306,
//     'user' => 'user',
//     'pass' => 'pass',
//     'path' => 'mydb',
//     'query' => 'charset=utf8',
//     'fragment' => null,
// ]

// Get specific part
$host = $resolver->resolve('key:host:url:DATABASE_URL');
// Result: 'localhost'
```

### Working with Enums

```php
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

$_ENV['USER_STATUS'] = 'active';

$resolver = new Resolver();
$status = $resolver->resolve('enum:Status:USER_STATUS');
// Result: Status::ACTIVE
```

## Error Handling

The library throws typed exceptions for different error conditions:

| Exception | Condition |
|-----------|-----------|
| `EnvFoundException` | Environment variable or constant not found |
| `FileNotFoundException` | File does not exist or is not readable |
| `InvalidValueException` | Value cannot be processed (invalid JSON, etc.) |
| `InvalidNameException` | Invalid variable/constant name |
| `InvalidEnumException` | Class is not a BackedEnum |
| `NotSupportedEnumCaseException` | Enum case does not exist |
| `InvalidHeapException` | Invalid modifier chain syntax |
| `RuntimeException` | General runtime errors |

All exceptions implement `EnvResolverExceptionInterface`.

## Contributing

Check code quality before the creating of PR.

```bash
# All checks
composer check-all

# Individual checks
composer cs-fixer-check  # Code style
composer phpstan         # Static analysis
composer require-check   # Dependency check
composer unused          # Unused dependencies

# Run with Docker
docker compose run --rm php-cli php tools/phpunit.phar -c .scripts/phpunit/phpunit.xml
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
