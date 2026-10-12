<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit directory binding for LDAP-managed staff accounts (ADR-0001 / ARCH-010).
 * The pair (ldap_source, ldap_username) is unique; NULL means "not enrolled".
 * No application permission is derived from these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ldap_source', 64)->nullable()->after('viisp_local_password_set_at');
            $table->string('ldap_username', 255)->nullable()->after('ldap_source');
            $table->timestamp('ldap_linked_at')->nullable()->after('ldap_username');
            $table->unique(['ldap_source', 'ldap_username'], 'users_ldap_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_ldap_identity_unique');
            $table->dropColumn(['ldap_source', 'ldap_username', 'ldap_linked_at']);
        });
    }
};
