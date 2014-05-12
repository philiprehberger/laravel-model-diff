<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff\Concerns;

use Illuminate\Support\Str;

trait HasDiffLabels
{
    /**
     * Map of attribute names to human-readable labels.
     * Override this in your model to provide custom labels.
     *
     * Example:
     *   protected array $diffLabels = [
     *       'company_name' => 'Company Name',
     *       'is_active'    => 'Active Status',
     *   ];
     *
     * @var array<string, string>
     */
    // Note: intentionally not declaring $diffLabels here so that models that
    // define their own $diffLabels property (with values) are not in conflict
    // with this trait. The getDiffLabel() method handles the missing-property
    // case by returning an empty array when the property does not exist.

    /**
     * Return the human-readable label for the given attribute.
     * Falls back to a humanized form of the attribute name when no label is
     * defined (e.g. "company_name" → "Company Name").
     */
    public function getDiffLabel(string $attribute): string
    {
        $labels = property_exists($this, 'diffLabels') ? $this->diffLabels : [];

        if (isset($labels[$attribute])) {
            return $labels[$attribute];
        }

        return Str::of($attribute)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
