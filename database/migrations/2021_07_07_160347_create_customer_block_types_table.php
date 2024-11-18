<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerBlockTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_block_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('customer_id')->comment('Comes form customer_infos table');
            $table->unsignedBigInteger('customer_lob_id')->nullable()->comment('Comes form lob table');
            $table->enum('type', ["Credit Limit", "Return", "Sales", "Other", "Order"])->nullable();
            $table->boolean('is_block')->default(1);
            $table->boolean('is_lob')
                ->default(0);

            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_infos')
                ->onDelete('cascade');

            $table->foreign('customer_lob_id')
                ->references('id')
                ->on('customer_lobs');

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
        Schema::dropIfExists('customer_block_types');
    }
}
