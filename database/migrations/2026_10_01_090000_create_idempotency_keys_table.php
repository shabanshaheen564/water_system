<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('endpoint', 255);
            $table->string('request_hash', 64);
            $table->json('response');
            $table->unsignedSmallInteger('status_code');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};