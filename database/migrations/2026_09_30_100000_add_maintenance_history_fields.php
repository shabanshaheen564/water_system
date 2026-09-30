<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->timestamp('started_at')->nullable()->after('requested_at');
            $table->timestamp('waiting_at')->nullable()->after('started_at');
            $table->timestamp('cancelled_at')->nullable()->after('waiting_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['assigned_at','started_at','waiting_at','cancelled_at','cancellation_reason']);
        });
    }
};