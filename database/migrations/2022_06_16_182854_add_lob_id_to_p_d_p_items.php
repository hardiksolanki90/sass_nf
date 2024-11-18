<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLobIdToPDPItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p_d_p_items', function (Blueprint $table) {
            $table->unsignedBigInteger('lob_id')
                ->nullable()
                ->after('price');

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
        Schema::table('p_d_p_items', function (Blueprint $table) {
            //
        });
    }
}
