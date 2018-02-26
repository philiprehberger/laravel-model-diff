<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff\Facades;

use Illuminate\Support\Facades\Facade;
use PhilipRehberger\ModelDiff\DiffResult;
use PhilipRehberger\ModelDiff\ModelDiff as ModelDiffClass;

/**
 * @method static DiffResult compare(\Illuminate\Database\Eloquent\Model $before, \Illuminate\Database\Eloquent\Model $after)
 * @method static DiffResult fromDirty(\Illuminate\Database\Eloquent\Model $model)
 * @method static ModelDiffClass ignoring(array $attributes)
 *
 * @see ModelDiffClass
 */
class ModelDiff extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModelDiffClass::class;
    }
}
