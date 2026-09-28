<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('dataset_records', function (Blueprint $table) {
            $table->index(['dataset_id', 'identifier_value'], 'dataset_records_dataset_identifier_idx');
        });

        \Illuminate\Support\Facades\DB::statement(
            'CREATE INDEX IF NOT EXISTS dataset_records_values_gin_idx ON dataset_records USING GIN (values)'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS dataset_records_values_gin_idx'
        );

        Schema::table('dataset_records', function (Blueprint $table) {
            $table->dropIndex('dataset_records_dataset_identifier_idx');
        });
    }
};
