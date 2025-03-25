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
            $table->longText('certificate')->nullable()->after('subdomain');
            $table->longText('private_key')->nullable()->after('certificate');
            $table->longText('intermediate_certificate')->nullable()->after('private_key');
            $table->longText('csr')->nullable()->after('intermediate_certificate');

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
