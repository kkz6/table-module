<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Modules\Table\Contracts\ExportExecutionContext;
use RuntimeException;

final class AuthenticatedExportExecutionContext implements ExportExecutionContext
{
    public function run(int|string $ownerId, Closure $callback): mixed
    {
        $provider = Auth::createUserProvider(
            config('auth.guards.'.Auth::getDefaultDriver().'.provider'),
        );
        $owner = $provider?->retrieveById($ownerId);

        if (! $owner instanceof Authenticatable) {
            throw new RuntimeException('The export owner no longer exists.');
        }

        $guard        = Auth::guard();
        $previousUser = $guard->user();

        try {
            $guard->setUser($owner);

            return $callback();
        } finally {
            if ($previousUser instanceof Authenticatable) {
                $guard->setUser($previousUser);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }
}
