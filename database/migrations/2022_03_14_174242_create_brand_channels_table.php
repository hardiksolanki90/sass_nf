<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBrandChannelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_channel_id');
            $table->unsignedBigInteger('brand_id');

            $table->foreign('user_channel_id')
                ->references('id')
                ->on('user_channels');

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands');

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
        Schema::dropIfExists('brand_channels');
    }
}
