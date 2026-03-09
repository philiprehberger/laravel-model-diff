<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff;

class DiffResult
{
    /**
     * @param  AttributeChange[]  $changes
     */
    public function __construct(
        private readonly array $changes,
    ) {}

    /**
     * Returns true when at least one attribute changed.
     */
    public function hasChanges(): bool
    {
        return count($this->changes) > 0;
    }

    /**
     * Returns the list of attribute names that changed.
     *
     * @return string[]
     */
    public function changedAttributes(): array
    {
        return array_map(
            fn (AttributeChange $change) => $change->attribute,
            $this->changes,
        );
    }

    /**
     * Returns all AttributeChange objects.
     *
     * @return AttributeChange[]
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Returns a plain array representation of all changes.
     *
     * @return array<int, array{attribute: string, old: mixed, new: mixed, label: string}>
     */
    public function toArray(): array
    {
        return array_map(
            fn (AttributeChange $change) => $change->toArray(),
            $this->changes,
        );
    }

    /**
     * Returns a human-readable array keyed by label rather than attribute name.
     * Scalar values are returned as-is; non-scalar values are JSON-encoded.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function toHumanReadable(): array
    {
        $result = [];

        foreach ($this->changes as $change) {
            $result[$change->label] = [
                'old' => $this->formatValue($change->old),
                'new' => $this->formatValue($change->new),
            ];
        }

        return $result;
    }

    private function formatValue(mixed $value): mixed
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
