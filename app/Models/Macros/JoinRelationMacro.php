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

class JoinRelationMacro
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
        foreach ($relationNameList as $r => $where) {
            $relationName = preg_replace('/(?:[\w_]*@)?([\w_]+)/', '$1', $r);
            $isLeftJoin = preg_match('/left@([\w_]+)/', $r);

            $relation = $m->{$relationName}();
            if ($relation instanceof HasMany) {
                $m = $this->joinHasMany($m->getTable(), $relation, $where, $isLeftJoin);
            } elseif ($relation instanceof HasOne) {
                $m = $this->joinHasOne($m->getTable(), $relation, $where, $isLeftJoin);
            } elseif ($relation instanceof BelongsTo) {
                $m = $this->joinBelongsTo($m->getTable(), $relation, $where, $isLeftJoin);
            } elseif ($relation instanceof BelongsToMany) {
                $m = $this->joinBelongsToMany($m->getTable(), $relation, $where, $isLeftJoin);
            } else {
                /**
                 * todo 他のリレーション型に対応する場合はここに追加
                 */
                throw new RuntimeException('relation dose not follow..');
            }

        }

        return $this->query;
    }

    /**
     * @param string $table
     * @param \Closure $closure
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

    protected function addWhere(string $table, ?string $pivot, ?callable $where): bool
    {
        if (!$this->query->getQuery()->joins) {
            return false;
        }

        foreach ($this->query->getQuery()->joins as $j) {
            if ($j->table === $table) {
                if (!is_null($where)) {
                    $where($this->query, $pivot);
                }
                return true;
            }
        }

        return false;
    }


    protected function joinBelongsTo($table, BelongsTo $belongsTo, ?callable $where, $isLeftJoin): Model
    {
        $m = $belongsTo->getModel();
        $relatedTable = $m->getTable();
        if ($this->addWhere($relatedTable, null, $where)) {
            return $m;
        }

        $parentKey = $belongsTo->getParentKey() ?? 'id';
        $foreignKey = $belongsTo->getForeignKeyName();

        $this->join($relatedTable, function ($join) use ($table, $parentKey, $relatedTable, $foreignKey, $m, $where) {
            $join
                ->on("{$table}.{$foreignKey}", '=', "{$relatedTable}.{$parentKey}");

            if (!is_null($where)) {
                $where($this->query, null);
            }
            foreach ($m->getGlobalScopes() as $s) {
                if ($s instanceof Scope) {
                    $s->apply($this->query, $m);
                } else {
                    $s($this->query);
                }
            }
        }, $isLeftJoin);

        return $m;
    }

    protected function joinHasMany($table, HasMany $hasMany, ?callable $where, $isLeftJoin): Model
    {
        $m = $hasMany->getModel();
        $relatedTable = $m->getTable();
        if ($this->addWhere($relatedTable, null, $where)) {
            return $m;
        }

        $localKey = $hasMany->getLocalKeyName();
        $foreignKey = $hasMany->getForeignKeyName();

        $this->join($relatedTable, function ($join) use ($table, $localKey, $relatedTable, $foreignKey, $m, $where) {
            $join
                ->on("{$table}.{$localKey}", '=', "{$relatedTable}.{$foreignKey}");
            if (!is_null($where)) {
                $where($this->query, null);
            }
            foreach ($m->getGlobalScopes() as $s) {
                if ($s instanceof Scope) {
                    $s->apply($this->query, $m);
                } else {
                    $s($this->query);
                }
            }
        }, $isLeftJoin);

        return $m;
    }


    protected function joinHasOne($table, HasOne $hasOne, ?callable $where, $isLeftJoin): Model
    {
        $m = $hasOne->getModel();
        $relatedTable = $m->getTable();
        if ($this->addWhere($relatedTable, null, $where)) {
            return $m;
        }

        $localKey = $hasOne->getLocalKeyName();
        $foreignKey = $hasOne->getForeignKeyName();

        $this->join($relatedTable, function ($join) use ($table, $localKey, $relatedTable, $foreignKey, $m, $where) {
            $join
                ->on("{$table}.{$localKey}", '=', "{$relatedTable}.{$foreignKey}");
            if (!is_null($where)) {
                $where($this->query, null);
            }
            foreach ($m->getGlobalScopes() as $s) {
                if ($s instanceof Scope) {
                    $s->apply($this->query, $m);
                } else {
                    $s($this->query);
                }
            }
        }, $isLeftJoin);

        return $m;
    }

    protected function joinBelongsToMany($table, BelongsToMany $belongsToMany, ?callable $where, $isLeftJoin): Model
    {
        $m = $belongsToMany->getRelated();

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


        if (!$this->addWhere($pivot['table'], null, null)) {
            $isPivotSoftDelete = $this->isSoftDeletePivot($belongsToMany);
            $this->join($pivot['table'], function ($join) use ($parent, $pivot, $isPivotSoftDelete) {
                $join->on("{$pivot['table']}.{$pivot['key']}", "{$parent['table']}.{$parent['key']}");
                if ($isPivotSoftDelete) {
                    $join->whereNull("{$pivot['table']}.deleted_at");
                }
            }, $isLeftJoin);
        }

        if (!$this->addWhere($related['table'], null, null)) {
            $this->join($related['table'], function ($join) use ($m, $related, $pivot, $where) {
                $join->on("{$related['table']}.{$related['key']}", "{$pivot['table']}.{$pivot['relatedKey']}");

                if (!is_null($where)) {
                    $where($join, $pivot['table']);
                }

                foreach ($m->getGlobalScopes() as $s) {
                    if ($s instanceof Scope) {
                        $s->apply($this->query, $m);
                    } else {
                        $s($this->query);
                    }
                }
            }, $isLeftJoin);
        }

        return $m;
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
