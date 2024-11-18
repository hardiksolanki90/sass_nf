<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLopToCustomerInfos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_infos', function (Blueprint $table) {
            $table->bigInteger('country_id')
                ->after('region_id')
                ->nullable();
            $table->decimal('amount')
                ->after('bill_to_payer')
                ->default('0.00');
            $table->date('expired_date')
                ->after('status')
                ->nullable();
            $table->string('radius')
                ->after('expired_date')
                ->nullable();
            $table->boolean('is_lob')
                ->after('radius')
                ->nullable();
            $table->string('lop')
                ->after('is_lob')
                ->nullable();
            $table->integer('source')
                ->after('lop')
                ->nullable();
            $table->date('due_on')
                ->after('source')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_infos', function (Blueprint $table) {
            //
        });
    }
}
