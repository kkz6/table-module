<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Shared\Tenancy\TenantContext;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

class LegacyAuthProbeTable extends Table
{
    /** @var array{authUserId: int|null, tenantUserId: int|null, companyId: int|null}|null */
    public static ?array $observedContext = null;

    public function resource(): Builder|string
    {
        return User::class;
    }

    public function columns(): array
    {
        return [];
    }

    public function exports(): array
    {
        return [
            Export::make(label: 'Legacy queued export')
                ->using(function (Table $_table, Export $_export, Request $_request, Builder $_query): void {
                    $tenant = app(TenantContext::class);

                    self::$observedContext = [
                        'authUserId'   => auth()->id(),
                        'tenantUserId' => $tenant->isEstablished() ? $tenant->userId() : null,
                        'companyId'    => $tenant->isEstablished() ? $tenant->companyId() : null,
                    ];
                }),
        ];
    }
}
