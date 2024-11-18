<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRfGenViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rf_gen_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->date('GLDate')->comment('Delivery Created_at')->nullable();
            $table->unsignedBigInteger('item_id')->comment('Items table')->nullable();
            $table->string('ITM_CODE', 80)->comment('Item Code')->nullable();
            $table->string('ITM_NAME')->comment('Item Name')->nullable();
            $table->date('TranDate')->comment('Order Date')->nullable();
            $table->string('Order_Number', 80)->comment('Order Number')->nullable();
            $table->string('MCU_CODE', 80)->comment('Storage Location Code')->nullable();
            $table->unsignedBigInteger('LOAD_NUMBER')->comment('Delivery Load Number')->nullable();
            $table->decimal('DemandPUOM', 18, 2)->comment('if primary qty and item Lower unit is same then add order qty otherwise 0')->nullable();
            $table->decimal('DemandSUOM', 18, 2)->comment('if secondry qty and item Lower unit is same then add order qty otherwise 0')->nullable();
            $table->decimal('PrevretSUom', 18, 2)->nullable();
            $table->decimal('PrevretPUom', 18, 2)->nullable();
            $table->enum('OrderPicked', ['Yes', 'No'])
                ->default('No')
                ->comment('Once rfGen picked order status will changed')->nullable();
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
        Schema::dropIfExists('rf_gen_views');
    }
}
