<?php

namespace App\Repository;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface EloquentRepositoryInterface.
 */
interface EloquentRepositoryInterface
{
    public function create(array $attributes): Model;

    /**
     * @param mixed $id
     *
     * @return Model
     */
    public function find($id): ?Model;
}
