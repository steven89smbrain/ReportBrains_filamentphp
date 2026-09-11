<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ReportBrains\ReportDesigner\Schema\ReportSchema;
use ReportBrains\ReportDesigner\Support\Scope;

/**
 * A stored report document.
 *
 * The `schema` column holds the whole document and is what every renderer reads.
 * `key` and `title` are mirrored into columns so they can be indexed and listed
 * without unpacking JSON — the columns are authoritative and are written back
 * into the document on save, so the two can never drift apart.
 *
 * @property string $key
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed> $schema
 * @property int $schema_version
 */
#[Fillable(['key', 'title', 'description', 'schema'])]
class ReportTemplate extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->owner()->associate(Scope::owner());
            $template->tenant()->associate(Scope::tenant());
        });

        // Validating here rather than only in the form means no code path —
        // seeder, import, console command — can store a document that would
        // later blow up at render time.
        static::saving(function (self $template): void {
            $template->syncSchemaWithColumns();

            app(ReportSchema::class)->validate($template->schema);
        });
    }

    public function getTable(): string
    {
        return config('report-designer.table_name', 'report_templates');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit to the templates the current user and tenant may see.
     */
    public function scopeVisible(Builder $query): Builder
    {
        if (Scope::tenancyEnabled() && $tenant = Scope::tenant()) {
            $query->where('tenant_type', $tenant->getMorphClass())
                ->where('tenant_id', $tenant->getKey());
        }

        if (Scope::ownershipEnabled() && $owner = Scope::owner()) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        return $query;
    }

    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Copy identity from the columns into the document, and default the version.
     */
    protected function syncSchemaWithColumns(): void
    {
        $schema = $this->schema ?? [];

        $schema['schema_version'] ??= ReportSchema::CURRENT_VERSION;
        $schema['key'] = $this->key;
        $schema['title'] = $this->title;

        $this->schema = $schema;
        $this->schema_version = $schema['schema_version'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'schema_version' => 'integer',
        ];
    }
}
