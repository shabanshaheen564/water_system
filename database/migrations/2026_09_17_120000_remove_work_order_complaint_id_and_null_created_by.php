<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['complaint_id']);
            $table->dropForeign(['complaint_id']);
            $table->dropColumn('complaint_id');
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->index('complaint_id');
        });

        DB::statement("
            UPDATE work_orders wo
            SET complaint_id = rel.complaint_id
            FROM (
                SELECT DISTINCT ON (work_order_id) work_order_id, complaint_id
                FROM complaint_work_order
                ORDER BY work_order_id, id
            ) rel
            WHERE rel.work_order_id = wo.id
        ");
    }
};
