<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Macros\JoinRelationMacro;

class JoinRelationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Builder::macro('joinRelation', function ($relationMethod) {
            /**
             * BuilderのメソッドにjoinRelationをインジェクション
             *
             *  thisはBuilderから参照する
             *  @var Builder $this
             */
            return (new JoinRelationMacro($this))($relationMethod);
        });
    }
}
