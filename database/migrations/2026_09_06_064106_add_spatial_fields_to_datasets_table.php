<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->boolean('is_spatial')->default(false)->after('is_active');
            $table->string('geometry_type')->nullable()->after('is_spatial');
            $table->integer('srid')->nullable()->after('geometry_type');
        });
    }

    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropColumn(['is_spatial', 'geometry_type', 'srid']);
        });
    }
};