<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Macros\WhereRelatedToMacro;

class WhereRelatedToServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Builder::macro('whereRelatedTo', function ($relationMethod) {
            /**
             * BuilderのメソッドにwhereRelatedToMacroをインジェクション
             *
             *  thisはBuilderから参照する
             *  @var Builder $this
             */
            return (new WhereRelatedToMacro($this))($relationMethod);
        });
    }
}
