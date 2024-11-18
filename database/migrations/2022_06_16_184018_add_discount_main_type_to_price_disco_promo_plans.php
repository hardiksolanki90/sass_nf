<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountMainTypeToPriceDiscoPromoPlans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('price_disco_promo_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('lob_id')
                ->nullable()
                ->after('combination_plan_key_id');

            $table->boolean('is_key_combination')
                ->default(0)
                ->after('offer_item_type');

            $table->boolean('discount_main_type')
                ->default(0)
                ->after('discount_apply_on')
                ->comment('0:header, 1:item');

            $table->foreign('lob_id')
                ->references('id')
                ->on('lobs');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('price_disco_promo_plans', function (Blueprint $table) {
            //
        });
    }
}
