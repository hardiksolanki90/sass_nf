<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryNoteIdToDeliveryDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_note_id')
                ->nullable()
                ->after('is_picking');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            //
        });
    }
}
