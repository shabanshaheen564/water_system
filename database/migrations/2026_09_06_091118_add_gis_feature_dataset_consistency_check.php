<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create trigger function to enforce dataset_id consistency
        DB::statement("
            CREATE OR REPLACE FUNCTION enforce_gis_feature_dataset_consistency()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.dataset_id != (
                    SELECT dataset_id FROM dataset_records WHERE id = NEW.dataset_record_id
                ) THEN
                    RAISE EXCEPTION 'gis_features.dataset_id must match dataset_records.dataset_id';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Attach trigger to gis_features table - separate statements
        DB::statement("DROP TRIGGER IF EXISTS gis_feature_dataset_consistency ON gis_features;");
        DB::statement("
            CREATE TRIGGER gis_feature_dataset_consistency
            BEFORE INSERT OR UPDATE ON gis_features
            FOR EACH ROW
            EXECUTE FUNCTION enforce_gis_feature_dataset_consistency();
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS gis_feature_dataset_consistency ON gis_features;");
        DB::statement("DROP FUNCTION IF EXISTS enforce_gis_feature_dataset_consistency();");
    }
};