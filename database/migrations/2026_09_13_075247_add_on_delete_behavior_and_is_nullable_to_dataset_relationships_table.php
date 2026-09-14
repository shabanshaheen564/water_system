<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dataset_relationships', function (Blueprint $table) {
            $table->string('on_delete_behavior')->default('restrict'); // restrict, cascade, set_null
            $table->boolean('is_nullable')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dataset_relationships', function (Blueprint $table) {
            $table->dropColumn(['on_delete_behavior', 'is_nullable']);
        });
    }
};
