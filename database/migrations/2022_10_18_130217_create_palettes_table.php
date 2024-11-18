<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePalettesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('palettes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('organisation_id');
            $table->date('date');
            $table->unsignedBigInteger('salesman_id')->comment('Comes from users');
            $table->unsignedBigInteger('item_id')->comment('Comes from items');
            $table->decimal('qty', 8, 2)->default(0);
            $table->enum('type', ['add', 'return']);
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')
                ->on('organisations');

            $table->foreign('salesman_id')->references('id')
                ->on('users');

            $table->foreign('item_id')->references('id')
                ->on('items');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('palettes');
    }
}
