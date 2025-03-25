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
            $table->integer('attempt_install')->nullable()->after('published');
            $table->dateTime('last_attempt')->nullable()->after('attempt_install');

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
