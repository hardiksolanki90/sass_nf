<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanogramPostAfterImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('planogram_post_after_images')) {
            Schema::create('planogram_post_after_images', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('planogram_post_id');
                $table->text('image_string');
                $table->timestamps();
                $table->foreign('planogram_post_id')->references('id')->on('planogram_posts')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('planogram_post_after_images');
    }
}
