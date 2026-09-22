<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dataset_records', function ($table) {
            $table->index(['dataset_id', 'created_at'], 'dataset_records_dataset_created_idx');
            $table->index('identifier_value', 'dataset_records_identifier_idx');
        });

        Schema::table('gis_features', function ($table) {
            $table->index(['dataset_id', 'created_at'], 'gis_features_dataset_created_idx');
        });

        DB::statement('CREATE INDEX IF NOT EXISTS gis_features_geometry_gist_idx ON gis_features USING GIST (geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS dataset_records_values_gin_idx ON dataset_records USING GIN (values)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gis_features_geometry_gist_idx');
        DB::statement('DROP INDEX IF EXISTS dataset_records_values_gin_idx');

        Schema::table('gis_features', function ($table) {
            $table->dropIndex('gis_features_dataset_created_idx');
        });

        Schema::table('dataset_records', function ($table) {
            $table->dropIndex('dataset_records_dataset_created_idx');
            $table->dropIndex('dataset_records_identifier_idx');
        });
    }
};
