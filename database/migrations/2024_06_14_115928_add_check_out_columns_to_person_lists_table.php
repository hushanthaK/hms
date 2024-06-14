<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCheckOutColumnsToPersonListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person_lists', function (Blueprint $table) {
            $table->string('check_in_date')->nullable();
            $table->string('check_out_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_lists', function (Blueprint $table) {
            $table->dropColumn(['check_in_date', 'check_out_date']);
        });
    }
}
