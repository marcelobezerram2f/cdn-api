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
        Schema::create('cdn_data_summarized_stream_server', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cdn_data_summarized_tenant_id');
            $table->date('summary_date');
            $table->string('stream_server');
            $table->bigInteger('bytes_transmitted');
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
        Schema::dropIfExists('cnd_data_summarized');
    }
};
