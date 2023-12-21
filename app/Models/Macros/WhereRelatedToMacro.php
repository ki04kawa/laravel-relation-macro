<?php

declare(strict_types=1);

namespace App\Models\Macros;

use http\Exception\RuntimeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

class WhereRelatedToMacro
{
    protected ?string $table = null;

    public function __construct(protected Builder $query)
    {
        $this->table = $query->getModel()->getTable();
    }

    public function __invoke(string|array $relationName): Builder
    {
        $relationNameList = $this->getRelations($relationName);
        $m = $this->query->getModel();

        $baseQuery = $this->query;

        foreach ($relationNameList as $relationName => $where) {
            $relation = $m->{$relationName}();
            if ($relation instanceof HasMany) {
                $baseQuery = $this->whereHasMany($baseQuery, $m->getTable(), $relation, $where);
            } elseif ($relation instanceof HasOne) {
                $baseQuery = $this->whereHasOne($baseQuery, $m->getTable(), $relation, $where);
            } elseif ($relation instanceof BelongsTo) {
                $baseQuery = $this->whereBelongsTo($baseQuery, $m->getTable(), $relation, $where);
            } elseif ($relation instanceof BelongsToMany) {
                $baseQuery = $this->whereBelongsToMany($baseQuery, $m->getTable(), $relation, $where);
            } else {
                /**
                 * todo 他のリレーション型に対応する場合はここに追加
                 */
                throw new RuntimeException('relation dose not follow..');
            }

            $m = $baseQuery->getModel();
        }

        return $this->query;
    }

    /**
     * @param string $table
     * @param \Closure $closure
     * @param bool $isLeftJoin
     * @return Builder
     */
    protected function join(string $table, \Closure $closure, $isLeftJoin = false): Builder
    {
        $query = $this->query;
        if ($isLeftJoin) {
            return $query->leftJoin($table, $closure);
        }

        return $query->join($table, $closure);
    }

    protected function getRelations(string|array $relationName): array
    {
        $list = is_array($relationName) ? $relationName : explode('.', $relationName);
        $relations = [];
        foreach ($list as $key => $r) {
            if (is_string($r) && is_int($key)) {
                $relations[$r] = null;
            } else {
                $relations[$key] = $r;
            }
        }

        return $relations;
    }

    protected function whereBelongsTo($baseQuery, $table, BelongsTo $belongsTo, ?callable $where)
    {
        $m = $belongsTo->getModel();
        $filterQuery = $m->newQuery();

        $relatedTable = $m->getTable();
        $parentKey = $belongsTo->getParentKey() ?? 'id';
        $foreignKey = $belongsTo->getForeignKeyName();

        if ($where) {
            $where($filterQuery, null);
        }

        $filterQuery->select([
            "{$relatedTable}.{$parentKey}",
        ]);

        $baseQuery->whereIn("{$table}.{$foreignKey}", $filterQuery);

        return $filterQuery;
    }

    protected function whereHasMany($baseQuery, string $table, HasMany $hasMany, ?callable $where)
    {
        $m = $hasMany->getModel();
        $filterQuery = $m->newQuery();

        $relatedTable = $m->getTable();
        $localKey = $hasMany->getLocalKeyName();
        $foreignKey = $hasMany->getForeignKeyName();


        if ($where) {
            $where($filterQuery, null);
        }
        $filterQuery->select([
            "{$relatedTable}.{$foreignKey}",
        ]);

        $baseQuery->whereIn("{$table}.{$localKey}", $filterQuery);

        return $filterQuery;

    }


    protected function whereHasOne($baseQuery, string $table, HasOne $hasOne, ?callable $where)
    {
        $m = $hasOne->getModel();
        $filterQuery = $m->newQuery();


        $relatedTable = $m->getTable();

        $localKey = $hasOne->getLocalKeyName();
        $foreignKey = $hasOne->getForeignKeyName();

        if ($where) {
            $where($filterQuery, null);
        }
        $filterQuery->select([
            "{$relatedTable}.{$foreignKey}",
        ]);

        $this->query->whereIn("{$table}.{$localKey}", $filterQuery);

        return $filterQuery;

    }

    protected function whereBelongsToMany($baseQuery, string $table, BelongsToMany $belongsToMany, ?callable $where)
    {

        $m = $belongsToMany->getRelated();
        $filterQuery = $m->newQuery();

        $parent = [
            'table' => $table,
            'key' => $belongsToMany->getParentKeyName(),
        ];

        $related = [
            'table' => $m->getTable(),
            'key' => $belongsToMany->getRelatedKeyName(),
        ];

        $pivot = [
            'table' => $belongsToMany->getTable(),
            'key' => $belongsToMany->getForeignPivotKeyName(),
            'relatedKey' => $belongsToMany->getRelatedPivotKeyName(),
        ];

        $isPivotSoftDelete = $this->isSoftDeletePivot($belongsToMany);
        $filterQuery->join($pivot['table'], function ($join) use ($where, $related, $pivot, $isPivotSoftDelete) {
            $join->on("{$pivot['table']}.{$pivot['relatedKey']}", "{$related['table']}.{$related['key']}");
            if ($where) {
                $where($join, "{$pivot['table']}");
            }
            if ($isPivotSoftDelete) {
                $join->whereNull("{$pivot['table']}.deleted_at");
            }
        });

        $filterQuery->select(["{$pivot['table']}.{$pivot['key']}"]);

        $baseQuery
            ->whereIn("{$parent['table']}.{$parent['key']}", $filterQuery);

        return $filterQuery;
    }

    protected function isSoftDeletePivot(BelongsToMany $belongsToMany): bool
    {
        $query = $belongsToMany->getQuery()->getQuery();
        $pivot = $belongsToMany->getTable();

        if (!$query->wheres) {
            return false;
        }

        foreach ($query->wheres as $w) {
            if (($w['column'] ?? null) === "{$pivot}.deleted_at") {
                if ((($w['type'] ?? null) === 'Null') && (($w['boolean'] ?? null) === 'and')) {
                    return true;
                }
            }
        }

        return false;
    }
}
