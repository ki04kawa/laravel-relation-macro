<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Item;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{
    public function top(Request $request)
    {
        $type = $request->query('type');

        $micro = 1000000;
        $count = 10;

        if ($type === 'has') {
            $start = hrtime(true);
            for ($i = 1; $i <= $count; $i++) {
                $items = Item::query()
                    ->whereHas('catalogs', function($query) use ($i) {
                        $query->where('catalogs.type', '=', $i);
                    })
                    ->whereHas('subItems', function ($query) use ($i) {
                        $query->where('sub_items.name', '=', 'natus@');
                    })
                    ->get();

            }
            $hasTime = (hrtime(true) - $start) / $micro;
        }

        if ($type === 'select') {
            $start = hrtime(true);
            for ($i = 1; $i <= $count; $i++) {
                $items = Item::query()
                    ->whereRelatedTo([
                        'catalogs' => function ($query) use ($i) {
                             $query->where('catalogs.type', $i);
                        },
                    ])
                    ->whereRelatedTo([
                        'subItems' => function ($query) use ($i) {
                            $query->where('sub_items.name', '=', 'natus@');
                        },
                    ])
                    ->get();
            }
            $selectTime = (hrtime(true) - $start) / $micro;
        }

        if ($type === 'join') {
            $start = hrtime(true);
            for ($i = 1; $i <= $count; $i++) {
                $items = Item::query()
                    ->joinRelation([
                        'catalogs' => function ($query) use ($i) {
                            $query->where('catalogs.type', $i);
                        },
                    ])
                    ->joinRelation([
                        'subItems' => function ($query) use ($i) {
                            $query->where('sub_items.name', '=', 'natus@');
                        },
                    ])
                    ->select('items.*')
                    ->groupBy('items.id')
                    ->get();

            }
            $joinTime = (hrtime(true) - $start) / $micro;
        }



        return view('top', [
            'hasTime' => $hasTime ?? null,
            'joinTime' => $joinTime ?? null,
            'selectTime' => $selectTime ?? null,
        ]);
    }
}
