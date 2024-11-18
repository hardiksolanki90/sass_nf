<?php

use App\Model\Item;
use Illuminate\Database\Seeder;

class ItemChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $items = Item::get();
        foreach ($items as $item) {
            $id = rand(1, 97);
            if (!empty($id)) {
                $item->channel_id = $id;
                $item->save();
            }
        }
    }
}
