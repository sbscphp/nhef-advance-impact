<?php

namespace App\Support;

/**
 * Local-dev convenience only: this repo hosts two products (NHEF Nexus on `main`, AdvanceImpact
 * on `sync-advance-impact`), each with its own local MySQL database. Picks the AdvanceImpact DB
 * automatically when the checked-out branch is `sync-advance-impact`, so switching branches
 * doesn't require hand-editing `.env`. Never active outside `APP_ENV=local`, and silently falls
 * back to `DB_DATABASE` if git isn't available or the override env var isn't set - this must never
 * break a deploy, where each product already has its own separate DB server anyway.
 */
final class LocalBranchDatabase
{
    private const ADVANCE_IMPACT_BRANCH = 'sync-advance-impact';

    public static function resolve(): ?string
    {
        if (env('APP_ENV') !== 'local') {
            return null;
        }

        $override = env('DB_DATABASE_ADVANCE_IMPACT');
        if (! is_string($override) || $override === '') {
            return null;
        }

        return self::currentBranch() === self::ADVANCE_IMPACT_BRANCH ? $override : null;
    }

    private static function currentBranch(): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        $output = [];
        $exitCode = 0;
        exec('git rev-parse --abbrev-ref HEAD 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        return trim($output[0]);
    }
}
