<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('processing_notes')->nullable()->after('description');
            $table->text('solution')->nullable()->after('processing_notes');
            $table->foreignId('processed_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('resolved_at');
            $table->index('processed_by');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropIndex(['processed_by']);
            $table->dropColumn(['processing_notes', 'solution', 'processed_by', 'processed_at']);
        });
    }
};
