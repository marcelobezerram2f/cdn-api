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
        Schema::table('cdn_letsencrypt_acme_registries', function (Blueprint $table) {
            $table->string('cname_validation_p2')->after('subdomain')->nullable();
            $table->string('cname_validation_p1')->after('subdomain')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cdn_letsencrypt_acme_registries', function (Blueprint $table) {
            //
        });
    }
};
