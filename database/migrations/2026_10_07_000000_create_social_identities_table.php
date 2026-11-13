<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        $connection = config('social-login.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public function up(): void
    {
        $userModel = config('social-login.user_model') ?? config('auth.providers.users.model');

        Schema::connection($this->getConnection())->create('social_identities', function (Blueprint $table) use ($userModel): void {
            $table->id();
            $table->foreignIdFor($userModel, 'user_id')->constrained((new $userModel)->getTable())->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            // Google Workspace domain (hd) or Microsoft Entra tenant id (tid).
            $table->string('organization')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('social_identities');
    }
};
