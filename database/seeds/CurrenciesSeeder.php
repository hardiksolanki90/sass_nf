<?php

use App\Model\Currency;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currency = new Currency;
		$currency->organisation_id = 1;
		$currency->currency_master_id = 4;
		$currency->name = "United Arab Emirates Dirham";
		$currency->symbol = "AED";
		$currency->code = "AED";
		$currency->name_plural = "UAE dirhams";
		$currency->symbol_native = "د.إ.‏";
		$currency->decimal_digits = 2;
		$currency->rounding = 0;
		$currency->default_currency = 1;
		$currency->format = "1,234,567.89";
        $currency->save();
    }
}
