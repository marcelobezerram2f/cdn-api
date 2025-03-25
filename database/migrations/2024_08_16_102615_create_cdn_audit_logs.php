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
        Schema::create('cdn_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('account');
            $table->string('action');
            $table->string('type');
            $table->string('message');
            $table->longText('payload');
            $table->longText('response')->nullable();
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
        Schema::dropIfExists('cdn_transaction_logs');
    }
};
