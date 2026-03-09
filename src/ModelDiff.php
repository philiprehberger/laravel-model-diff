<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhilipRehberger\ModelDiff\Concerns\HasDiffLabels;
use UnitEnum;

class ModelDiff
{
    /**
     * @var string[]
     */
    private array $ignoredAttributes;

    private string $dateFormat;

    public function __construct()
    {
        $this->ignoredAttributes = config(
            'model-diff.ignored_attributes',
            ['created_at', 'updated_at', 'id'],
        );

        $this->dateFormat = config('model-diff.date_format', 'M j, Y g:i A');
    }

    /**
     * Compare two snapshots of the same model and return a DiffResult
     * describing every attribute that changed between them.
     */
    public function compare(Model $before, Model $after): DiffResult
    {
        $beforeAttributes = $before->getAttributes();
        $afterAttributes = $after->getAttributes();

        $allKeys = array_unique(
            array_merge(array_keys($beforeAttributes), array_keys($afterAttributes))
        );

        $changes = [];

        foreach ($allKeys as $attribute) {
            if ($this->isIgnored($attribute)) {
                continue;
            }

            $oldRaw = $beforeAttributes[$attribute] ?? null;
            $newRaw = $afterAttributes[$attribute] ?? null;

            // Use cast-aware values for comparison
            $oldValue = $this->resolveValue($before, $attribute, $oldRaw);
            $newValue = $this->resolveValue($after, $attribute, $newRaw);

            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $changes[] = new AttributeChange(
                attribute: $attribute,
                old: $oldValue,
                new: $newValue,
                label: $this->getLabelForAttribute($before, $attribute),
            );
        }

        return new DiffResult($changes);
    }

    /**
     * Compare the current dirty state of an unsaved model against its original
     * database values and return a DiffResult.
     */
    public function fromDirty(Model $model): DiffResult
    {
        if (! $model->isDirty()) {
            return new DiffResult([]);
        }

        $dirtyAttributes = $model->getDirty();
        $original = $model->getOriginal();

        $changes = [];

        foreach (array_keys($dirtyAttributes) as $attribute) {
            if ($this->isIgnored($attribute)) {
                continue;
            }

            $oldRaw = $original[$attribute] ?? null;
            $newRaw = $dirtyAttributes[$attribute];

            // Build a temporary clone with original values to resolve cast
            $oldValue = $this->resolveValueFromRaw($model, $attribute, $oldRaw);
            $newValue = $this->resolveValueFromRaw($model, $attribute, $newRaw);

            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $changes[] = new AttributeChange(
                attribute: $attribute,
                old: $oldValue,
                new: $newValue,
                label: $this->getLabelForAttribute($model, $attribute),
            );
        }

        return new DiffResult($changes);
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    /**
     * Return a new instance with additional ignored attributes merged in.
     *
     * @param  string[]  $attributes
     */
    public function ignoring(array $attributes): static
    {
        $clone = clone $this;
        $clone->ignoredAttributes = array_unique(
            array_merge($this->ignoredAttributes, $attributes)
        );

        return $clone;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function isIgnored(string $attribute): bool
    {
        return in_array($attribute, $this->ignoredAttributes, true);
    }

    /**
     * Resolve the cast-aware value for an attribute using a fully-loaded model.
     */
    private function resolveValue(Model $model, string $attribute, mixed $rawValue): mixed
    {
        // If the model has a cast, use the cast accessor
        if ($model->hasCast($attribute)) {
            return $this->normalizeCastValue($model->getAttribute($attribute));
        }

        // Check if this is a date attribute
        if (in_array($attribute, $model->getDates(), true)) {
            return $this->normalizeDateValue($rawValue);
        }

        return $rawValue;
    }

    /**
     * Resolve a cast-aware value from a raw scalar, using the model's cast
     * definitions without actually loading the model's current state.
     *
     * This is used by fromDirty() where we must compare arbitrary raw values.
     */
    private function resolveValueFromRaw(Model $model, string $attribute, mixed $rawValue): mixed
    {
        if ($rawValue === null) {
            return null;
        }

        if ($model->hasCast($attribute)) {
            $castType = $this->getCastType($model, $attribute);

            return match (true) {
                $this->isDateCast($castType) => $this->normalizeDateValue($rawValue),
                $this->isJsonCast($castType) => $this->normalizeJsonValue($rawValue),
                $this->isBoolCast($castType) => (bool) $rawValue,
                $this->isIntCast($castType) => (int) $rawValue,
                $this->isFloatCast($castType) => (float) $rawValue,
                $this->isEnumCast($castType) => $this->normalizeEnumValue($rawValue, $castType),
                default => $rawValue,
            };
        }

        if (in_array($attribute, $model->getDates(), true)) {
            return $this->normalizeDateValue($rawValue);
        }

        return $rawValue;
    }

    /**
     * Normalize a value that has already been cast by Eloquent.
     */
    private function normalizeCastValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof CarbonInterface || $value instanceof DateTimeInterface => $this->normalizeDateValue($value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            is_array($value) => $value,
            is_bool($value) => $value,
            default => $value,
        };
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? $value
                : Carbon::parse($value);

            return $carbon->format($this->dateFormat);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    /** @param  class-string<UnitEnum>  $castType */
    private function normalizeEnumValue(mixed $value, string $castType): mixed
    {
        if (! enum_exists($castType)) {
            return $value;
        }

        try {
            if (is_subclass_of($castType, BackedEnum::class)) {
                $enum = $castType::tryFrom($value);

                return $enum instanceof BackedEnum ? $enum->value : $value;
            }
        } catch (\Throwable) {
            // fall through
        }

        return $value;
    }

    private function getCastType(Model $model, string $attribute): string
    {
        $casts = $model->getCasts();

        return strtolower($casts[$attribute] ?? '');
    }

    private function isDateCast(string $castType): bool
    {
        return in_array($castType, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true)
            || str_starts_with($castType, 'date:')
            || str_starts_with($castType, 'datetime:')
            || str_starts_with($castType, 'immutable_date:')
            || str_starts_with($castType, 'immutable_datetime:');
    }

    private function isJsonCast(string $castType): bool
    {
        return in_array($castType, ['array', 'json', 'object', 'collection'], true);
    }

    private function isBoolCast(string $castType): bool
    {
        return in_array($castType, ['bool', 'boolean'], true);
    }

    private function isIntCast(string $castType): bool
    {
        return in_array($castType, ['int', 'integer'], true);
    }

    private function isFloatCast(string $castType): bool
    {
        return in_array($castType, ['float', 'double', 'real', 'decimal'], true)
            || str_starts_with($castType, 'decimal:');
    }

    private function isEnumCast(string $castType): bool
    {
        return class_exists($castType) && is_subclass_of($castType, UnitEnum::class);
    }

    /**
     * Semantically compare two values, handling arrays/JSON by content.
     */
    private function valuesAreEqual(mixed $a, mixed $b): bool
    {
        // Both null
        if ($a === null && $b === null) {
            return true;
        }

        // Array/JSON comparison by content
        if (is_array($a) && is_array($b)) {
            return $this->arraysAreEqual($a, $b);
        }

        // Boolean normalization
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return $a === $b;
    }

    /**
     * Recursively compare two arrays by value (order-insensitive for associative arrays).
     *
     * @param  array<mixed>  $a
     * @param  array<mixed>  $b
     */
    private function arraysAreEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        // Check if both are sequential (list) arrays
        if (array_is_list($a) && array_is_list($b)) {
            foreach ($a as $i => $val) {
                $bVal = $b[$i];
                if (is_array($val) && is_array($bVal)) {
                    if (! $this->arraysAreEqual($val, $bVal)) {
                        return false;
                    }
                } elseif ($val !== $bVal) {
                    return false;
                }
            }

            return true;
        }

        // Associative array — compare by key regardless of insertion order
        foreach ($a as $key => $val) {
            if (! array_key_exists($key, $b)) {
                return false;
            }

            $bVal = $b[$key];

            if (is_array($val) && is_array($bVal)) {
                if (! $this->arraysAreEqual($val, $bVal)) {
                    return false;
                }
            } elseif ($val !== $bVal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the human-readable label for a given attribute.
     * Uses the HasDiffLabels trait when present on the model; otherwise
     * humanizes the attribute name automatically.
     */
    private function getLabelForAttribute(Model $model, string $attribute): string
    {
        if (in_array(HasDiffLabels::class, class_uses_recursive($model), true)) {
            /** @var \PhilipRehberger\ModelDiff\Concerns\HasDiffLabels $model */
            return $model->getDiffLabel($attribute);
        }

        return Str::of($attribute)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
