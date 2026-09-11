<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tableName(), function (Blueprint $table): void {
            $table->id();

            $table->string('key');
            $table->string('title');
            $table->text('description')->nullable();

            // The document itself. `json` maps to a native JSON column on MySQL
            // and PostgreSQL, and to TEXT on SQLite, so it stays portable.
            $table->json('schema');
            $table->unsignedSmallInteger('schema_version');

            // Ownership and tenancy are opt-in (see config/report-designer.php).
            // The columns are always present so enabling either later needs a
            // data backfill rather than a migration against live customer data.
            $table->nullableMorphs('owner');
            $table->nullableMorphs('tenant');

            $table->timestamps();

            // Note: SQL treats NULLs as distinct, so this index does not stop two
            // *untenanted* templates from sharing a key. UniqueTemplateKey covers
            // that case in the application layer.
            $table->unique(['tenant_type', 'tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        return config('report-designer.table_name', 'report_templates');
    }
};
