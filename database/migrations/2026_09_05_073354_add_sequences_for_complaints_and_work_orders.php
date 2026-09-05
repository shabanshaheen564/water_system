<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS complaints_number_seq START 1');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS work_orders_number_seq START 1');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS work_orders_number_seq');
        DB::statement('DROP SEQUENCE IF EXISTS complaints_number_seq');
    }
};