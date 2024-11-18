<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditNoteNotesTable extends Migration
{
    /**
     * Run the migrations. 
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credit_note_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('salesman_id')->nullable();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('item_uom_id')->nullable();
            $table->integer('qty')->nullable();
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->string('credit_note_number')->nullable();
            $table->timestamps();

            $table->foreign('credit_note_id')
                ->references('id')
                ->on('credit_notes');

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
        Schema::dropIfExists('credit_note_notes');
    }
}
