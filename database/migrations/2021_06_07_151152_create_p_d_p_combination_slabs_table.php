<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePDPCombinationSlabsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('p_d_p_combination_slabs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('price_disco_promo_plan_id');
            $table->unsignedBigInteger('item_uom_id');
            $table->decimal('from_qty', 8, 2)->default('0.00');
            $table->decimal('to_qty', 8, 2)->default('0.00');
            $table->decimal('offer_qty', 8, 2)->default('0.00');

            $table->foreign('price_disco_promo_plan_id')
                ->references('id')
                ->on('price_disco_promo_plans')
                ->onDelete('cascade');

            $table->foreign('item_uom_id')
                ->references('id')
                ->on('item_uoms');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('p_d_p_combination_slabs');
    }
}
