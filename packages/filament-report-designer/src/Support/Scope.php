<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Resolves who owns a template and which tenant it belongs to.
 *
 * Both are opt-in; when disabled the resolvers return null and templates are
 * shared by everyone with panel access.
 */
class Scope
{
    public static function ownershipEnabled(): bool
    {
        return (bool) config('report-designer.ownership.enabled', false);
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) config('report-designer.tenancy.enabled', false);
    }

    public static function owner(): ?Model
    {
        if (! static::ownershipEnabled()) {
            return null;
        }

        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    public static function tenant(): ?Model
    {
        if (! static::tenancyEnabled()) {
            return null;
        }

        $resolver = config('report-designer.tenancy.resolver');

        if (is_callable($resolver)) {
            $tenant = $resolver();

            return $tenant instanceof Model ? $tenant : null;
        }

        // Filament throws rather than returning null when the panel has no
        // tenancy configured, which is a legitimate state here.
        try {
            $tenant = Filament::getTenant();
        } catch (Throwable) {
            return null;
        }

        return $tenant instanceof Model ? $tenant : null;
    }
}
