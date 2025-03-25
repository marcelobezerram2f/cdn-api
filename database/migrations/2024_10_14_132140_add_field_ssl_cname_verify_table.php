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
        Schema::table('cdn_resources', function (Blueprint $table) {
            $table->boolean('cname_ssl_verify')->nullable()->after('cname_verify');
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
