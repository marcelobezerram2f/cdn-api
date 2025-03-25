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
        Schema::table('cdn_cnames', function (Blueprint $table) {
            $table->bigInteger('cdn_letsencrypt_acme_register_id')->nullable()->after('cname');
            $table->bigInteger('cdn_resource_id')->nullable()->after('cdn_letsencrypt_acme_register_id');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cdn_cname', function (Blueprint $table) {
            //
        });
    }
};
