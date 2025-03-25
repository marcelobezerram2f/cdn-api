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
            $table->string('username')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('fulldomain')->nullable()->change();
            $table->string('subdomain')->nullable()->change();
            $table->string('company')->nullable()->after('subdomain');
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
