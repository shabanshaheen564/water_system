<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS maintenance_requests_number_seq START 1');

        Schema::create('maintenance_dataset_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'dataset_id']);
            $table->index(['dataset_id', 'role_id']);
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('gis_feature_id')->nullable()->constrained('gis_features')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->string('status')->default('new');
            $table->text('problem_description');
            $table->text('fault_description')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->text('repair_result')->nullable();
            $table->text('repair_action')->nullable();
            $table->text('materials_used')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('gis_feature_id');
            $table->index('reported_by');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
            $table->index('requested_at');
        });

        Schema::create('asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gis_feature_id')->nullable()->constrained('gis_features')->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspection_at')->useCurrent();
            $table->string('result')->default('okay');
            $table->text('problem_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('gis_feature_id');
            $table->index('inspected_by');
            $table->index('inspection_at');
            $table->index('result');
        });

        Schema::create('maintenance_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('diagnosed_fault')->nullable();
            $table->text('repair_action')->nullable();
            $table->text('materials_used')->nullable();
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['maintenance_request_id', 'created_at']);
            $table->index('technician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_jobs');
        Schema::dropIfExists('asset_inspections');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('maintenance_dataset_role');
        DB::statement('DROP SEQUENCE IF EXISTS maintenance_requests_number_seq');
    }
};
