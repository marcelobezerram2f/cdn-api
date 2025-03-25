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
        Schema::table('cdn_resources', callback: function (Blueprint $table) {

            $table->string('cdn_origin_hostname')->nullable()->change();
            $table->string('cdn_origin_server_port')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cdn_resources', function (Blueprint $table) {
            //
        });
    }
};
