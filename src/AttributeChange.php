<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff;

class AttributeChange
{
    public function __construct(
        public readonly string $attribute,
        public readonly mixed $old,
        public readonly mixed $new,
        public readonly string $label,
    ) {}

    /**
     * Return a plain array representation of this change.
     *
     * @return array{attribute: string, old: mixed, new: mixed, label: string}
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'old' => $this->old,
            'new' => $this->new,
            'label' => $this->label,
        ];
    }
}
