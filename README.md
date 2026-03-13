# Laravel Model Diff

[![Tests](https://github.com/philiprehberger/laravel-model-diff/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-model-diff/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/laravel-model-diff.svg)](https://packagist.org/packages/philiprehberger/laravel-model-diff)
[![Total Downloads](https://img.shields.io/packagist/dt/philiprehberger/laravel-model-diff.svg)](https://packagist.org/packages/philiprehberger/laravel-model-diff)
[![PHP Version Require](https://img.shields.io/packagist/php-v/philiprehberger/laravel-model-diff.svg)](https://packagist.org/packages/philiprehberger/laravel-model-diff)
[![License](https://img.shields.io/github/license/philiprehberger/laravel-model-diff)](LICENSE)

Track and display structured differences between Eloquent model versions with human-readable labels.

`laravel-model-diff` gives you a clean, cast-aware diff between two snapshots of the same model (or between a model's dirty state and its last-saved values). It handles dates, JSON/arrays, booleans, backed enums, and numeric types correctly, and lets you attach human-readable labels to any attribute.

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP        | ^8.2    |
| Laravel    | ^11.0 \| ^12.0 |

---

## Installation

Install via Composer:

```bash
composer require philiprehberger/laravel-model-diff
```

The service provider and facade are registered automatically via Laravel package auto-discovery.

### Publishing the config

```bash
php artisan vendor:publish --tag=model-diff-config
```

This creates `config/model-diff.php` in your application.

---

## Configuration

```php
// config/model-diff.php

return [

    /*
     | Attributes excluded from every diff comparison.
     */
    'ignored_attributes' => [
        'created_at',
        'updated_at',
        'id',
    ],

    /*
     | Format string used when rendering date/datetime values in
     | DiffResult::toHumanReadable().
     */
    'date_format' => 'M j, Y g:i A',

];
```

---

## Basic Usage

### Comparing two model instances

Pass two instances of the same model — a "before" snapshot and an "after" snapshot — to `ModelDiff::compare()`:

```php
use PhilipRehberger\ModelDiff\Facades\ModelDiff;

$before = User::find(42);
// ... some time passes, the record is updated ...
$after = User::find(42);

$result = ModelDiff::compare($before, $after);

if ($result->hasChanges()) {
    // ['name', 'email']
    $result->changedAttributes();

    // Array of AttributeChange objects
    $result->getChanges();

    // Plain arrays
    $result->toArray();

    // Keyed by human-readable label
    $result->toHumanReadable();
}
```

### Comparing an unsaved dirty model

Use `ModelDiff::fromDirty()` to inspect changes on a model that has not yet been saved:

```php
$user = User::find(42);
$user->name  = 'New Name';
$user->email = 'new@example.com';

// Do NOT call save() — inspect the dirty state
$result = ModelDiff::fromDirty($user);

$result->changedAttributes(); // ['name', 'email']
```

### Excluding extra attributes at call-site

```php
$result = ModelDiff::ignoring(['internal_notes', 'cache_key'])
    ->compare($before, $after);
```

---

## Human-Readable Labels

### Using the HasDiffLabels trait

Add the `HasDiffLabels` trait to any model and define a `$diffLabels` map:

```php
use PhilipRehberger\ModelDiff\Concerns\HasDiffLabels;

class Client extends Model
{
    use HasDiffLabels;

    protected array $diffLabels = [
        'company_name' => 'Company Name',
        'is_active'    => 'Active Status',
        'arr_monthly'  => 'Monthly ARR',
    ];
}
```

Attributes without an explicit entry are automatically humanized:
`billing_address` becomes `Billing Address`.

### Retrieving a label directly

```php
$client = new Client();
$client->getDiffLabel('company_name'); // "Company Name"
$client->getDiffLabel('phone_number'); // "Phone Number"
```

---

## DiffResult API

| Method | Return type | Description |
|--------|-------------|-------------|
| `hasChanges()` | `bool` | `true` when at least one attribute changed |
| `changedAttributes()` | `string[]` | Names of changed attributes |
| `getChanges()` | `AttributeChange[]` | All change objects |
| `toArray()` | `array` | Plain array — one entry per change |
| `toHumanReadable()` | `array` | Keyed by label; values formatted for display |

### toArray() output

```php
[
    [
        'attribute' => 'name',
        'old'       => 'Alice',
        'new'       => 'Bob',
        'label'     => 'Full Name',
    ],
    // ...
]
```

### toHumanReadable() output

```php
[
    'Full Name' => [
        'old' => 'Alice',
        'new' => 'Bob',
    ],
    'Published At' => [
        'old' => 'Jan 1, 2024 9:00 AM',
        'new' => 'Jun 20, 2025 2:30 PM',
    ],
    // ...
]
```

---

## AttributeChange API

| Property | Type | Description |
|----------|------|-------------|
| `$attribute` | `string` | Raw attribute name |
| `$old` | `mixed` | Normalized old value |
| `$new` | `mixed` | Normalized new value |
| `$label` | `string` | Human-readable label |

```php
foreach ($result->getChanges() as $change) {
    echo "{$change->label}: {$change->old} → {$change->new}";
}
```

---

## Cast-Aware Comparison

The package normalizes values before comparing them, so you never get false positives from type mismatches:

| Cast type | Normalization |
|-----------|---------------|
| `date`, `datetime`, `immutable_date/datetime` | Parsed to Carbon and formatted with `date_format` config |
| `timestamp` | Parsed to Carbon and formatted with `date_format` config |
| `array`, `json`, `object`, `collection` | Decoded and compared by content, not by serialized string |
| `boolean`, `bool` | Strict `(bool)` cast before comparison |
| `integer`, `int` | Strict `(int)` cast |
| `float`, `double`, `real` | Strict `(float)` cast |
| `decimal:N` | Strict `(float)` cast |
| Backed enum (`SomeEnum::class`) | Compared by `->value`; stored as scalar in `AttributeChange` |
| Unit enum (`SomeEnum::class` without backing) | Compared by `->name`; stored as string in `AttributeChange` |

> **Note:** Associative arrays are compared order-insensitively — `['a' => 1, 'b' => 2]` equals `['b' => 2, 'a' => 1]`. Sequential (list) arrays are compared in order.

---

## Using the Facade

The `ModelDiff` facade is registered automatically:

```php
use PhilipRehberger\ModelDiff\Facades\ModelDiff;

$result = ModelDiff::compare($before, $after);
$result = ModelDiff::fromDirty($model);
$result = ModelDiff::ignoring(['token'])->compare($before, $after);
```

---

## Using the Class Directly

If you prefer not to use the facade, resolve the class from the container or instantiate it directly:

```php
use PhilipRehberger\ModelDiff\ModelDiff;

// Via DI
public function __construct(private ModelDiff $diff) {}

// Directly
$diff = new ModelDiff();
$result = $diff->compare($before, $after);
```

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Code style:

```bash
vendor/bin/pint
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
