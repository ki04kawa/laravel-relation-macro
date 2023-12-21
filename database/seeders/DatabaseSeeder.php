<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use App\Models\UserGroup;
use App\Models\User;
use App\Models\Item;
use App\Models\Catalog;
use App\Models\SubItem;

/**
 * 参考：
 *  Laravel8以降のModelFactoryをまとめなおしたもの。
 *      https://zenn.dev/fagai/articles/1ad4a85695c4f9
 */
class DatabaseSeeder extends Seeder
{
    const GROUP_COUNT = 1;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $groupCount = self::GROUP_COUNT;

        $this->command->info('start : ' . CarbonImmutable::now());

        //$groups = UserGroup::factory(10)->create();
        $users = User::factory(10)
//            ->hasAttached($groups)
//            ->hasAttached($items)
            ->create();


        for ($i = 1; $i <= $groupCount; $i++) {
            $this->command->info("{$i}/{$groupCount} ****");

            $bar = $this->command->getOutput()->createProgressBar(1000);

            $catalogs = Catalog::factory(1000)->create();

//            foreach ($users as $user) {
                for ($j = 1; $j <= 1000; $j++) {
                    $items = Item::factory()
                        // ->hasAttached($user)
                        ->hasAttached($catalogs)
                        ->has(SubItem::factory())
                        ->create();
                    $bar->advance();
                }
//            }
            $bar->finish();
            $this->command->info('');

        }

        $this->command->info('end : ' . CarbonImmutable::now());
    }
}
