<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('salesman_id')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('item_uom_id')->nullable();
            $table->decimal('qty', 8,2)->nullable();
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->boolean('is_cancel')->default(0);
            $table->string('delivery_note_number')->nullable();
            $table->timestamps();

            $table->foreign('delivery_id')
                ->references('id')
                ->on('deliveries');

            $table->foreign('reason_id')
                ->references('id')
                ->on('reason_types');

            $table->foreign('item_id')
                ->references('id')
                ->on('items');

            $table->foreign('item_uom_id')
                ->references('id')
                ->on('item_uoms');

            $table->foreign('salesman_id')
                ->references('id')
                ->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_notes');
    }
}
