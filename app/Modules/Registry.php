<?php

namespace App\Modules;

use App\Models\School;
use App\Models\SchoolConfig;

/**
 * Module enable/disable registry. Reads definitions from config/modules.php
 * and the per-school enabled set from school_configs.enabled_modules.
 *
 * Used by menu-items.blade.php and the Settings UI. Routes are NOT gated
 * by this (lightweight phase) — disabling a module just hides its menu
 * entries.
 */
class Registry
{
    private static ?array $enabledCache = null;

    public static function definitions(): array
    {
        return config('modules', []);
    }

    public static function enabled(string $slug): bool
    {
        $defs = self::definitions();
        if (!isset($defs[$slug])) {
            return false;
        }
        if ($defs[$slug]['always_on'] ?? false) {
            return true;
        }
        return in_array($slug, self::enabledList(), true);
    }

    /**
     * The slugs currently enabled school-wide. Falls back to every
     * default_enabled module when no school_configs row exists yet.
     */
    public static function enabledList(): array
    {
        if (self::$enabledCache !== null) {
            return self::$enabledCache;
        }

        $school = School::first();
        $stored = $school?->config(\App\Models\SchoolConfig::ENABLED_MODULES);

        if ($stored === null) {
            return self::$enabledCache = collect(self::definitions())
                ->filter(fn ($d) => $d['default_enabled'] ?? true)
                ->keys()
                ->all();
        }

        return self::$enabledCache = (array) $stored;
    }

    public static function setEnabled(string $slug, bool $enabled): void
    {
        $school = School::first();
        if (!$school) {
            return;
        }

        $defs = self::definitions();
        if (!isset($defs[$slug]) || ($defs[$slug]['always_on'] ?? false)) {
            // Unknown or core-always-on — silently ignore.
            return;
        }

        $current = self::enabledList();
        $next = $enabled
            ? array_values(array_unique([...$current, $slug]))
            : array_values(array_filter($current, fn ($s) => $s !== $slug));

        SchoolConfig::updateOrCreate(
            ['school_id' => $school->id, 'key' => \App\Models\SchoolConfig::ENABLED_MODULES],
            ['value' => $next],
        );

        self::$enabledCache = null;
    }
}
