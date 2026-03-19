<?php

use App\Models\Problem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('problems', 'title')) {
            return;
        }

        Problem::query()
            ->where(function ($q) {
                $q->whereNull('description')
                    ->orWhere('description', '');
            })
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->each(function (Problem $problem) {
                $problem->update(['description' => $problem->title]);
            });

        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('status');
        });
    }
};
