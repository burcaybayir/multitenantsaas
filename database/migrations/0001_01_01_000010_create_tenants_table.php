<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // pending -> provisioning -> active | failed (see App\Enums\TenantStatus)
            $table->string('status', 20)->index();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
            // Package-internal attributes (encrypted DB credentials, etc.).
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
