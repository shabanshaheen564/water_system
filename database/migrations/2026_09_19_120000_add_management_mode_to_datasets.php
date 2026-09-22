<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('management_mode', 30)
                ->default('official')
                ->after('dataset_type')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropIndex(['management_mode']);
            $table->dropColumn('management_mode');
        });
    }
};
