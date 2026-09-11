<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Support\Scope;

/**
 * Enforces one template per key within the current tenant.
 *
 * The table also carries a unique index, but SQL treats NULLs as distinct, so
 * that index does not constrain untenanted rows — which is every row when
 * tenancy is switched off. This rule covers the case the index cannot.
 */
class UniqueTemplateKey implements ValidationRule
{
    public function __construct(private readonly ?ReportTemplate $ignoring = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = ReportTemplate::query()->where('key', $value);

        if ($tenant = Scope::tenant()) {
            $query->where('tenant_type', $tenant->getMorphClass())
                ->where('tenant_id', $tenant->getKey());
        } else {
            $query->whereNull('tenant_id');
        }

        if ($this->ignoring?->exists) {
            $query->whereKeyNot($this->ignoring->getKey());
        }

        if ($query->exists()) {
            $fail("A report template with the key [{$value}] already exists.");
        }
    }
}
