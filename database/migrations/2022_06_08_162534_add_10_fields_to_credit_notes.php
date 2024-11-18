<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Add10FieldsToCreditNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('merchandiser_image_1')->after('reason')->nullable();
            $table->string('merchandiser_image_2')->after('merchandiser_image_1')->nullable();
            $table->string('merchandiser_image_3')->after('merchandiser_image_2')->nullable();
            $table->string('merchandiser_image_4')->after('merchandiser_image_3')->nullable();
            $table->enum('merchandiser_status',['Pending','Approved','Null'])
                  ->after('merchandiser_image_4')->nullable();

            $table->string('delivery_driver_image_1')->after('merchandiser_status')->nullable();
            $table->string('delivery_driver_image_2')->after('delivery_driver_image_1')->nullable();
            $table->string('delivery_driver_image_3')->after('delivery_driver_image_2')->nullable();
            $table->string('delivery_driver_image_4')->after('delivery_driver_image_3')->nullable();
            $table->enum('delivery_driver_status',['Pending','Approved','Null'])
                  ->after('delivery_driver_image_4')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            //
        });
    }
}
