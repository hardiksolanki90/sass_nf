<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePDPCustomerCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('p_d_p_customer_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('price_disco_promo_plan_id');
            $table->unsignedBigInteger('customer_category_id');

            $table->foreign('price_disco_promo_plan_id')->references('id')->on('price_disco_promo_plans')->onDelete('cascade');
            $table->foreign('customer_category_id')->references('id')->on('customer_categories')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('p_d_p_customer_categories');
    }
}
