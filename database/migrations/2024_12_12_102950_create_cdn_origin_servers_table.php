<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cdn_origin_servers', function (Blueprint $table) {
            $table->id();
            $table->string('cdn_origin_hostname');
            $table->string('cdn_origin_protocol');
            $table->string('cdn_origin_server_port');
            $table->bigInteger('cdn_origin_group_id');
            $table->string('type')->nullable();
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
        Schema::dropIfExists('cdn_origin_servers');
    }
};
