<?php

declare(strict_types=1);

use Modules\Auth\Enums\Role;
use Modules\Company\Models\Company;
use Modules\Shared\Tenancy\TenantAwareExportExecutionContext;
use Modules\Shared\Tenancy\TenantContext;
use Modules\Table\Contracts\ExportExecutionContext;
use Modules\Table\ExportJob;
use Modules\Table\Exports\Jobs\Middleware\RunExportAsOwner;
use Modules\Table\Exports\Support\AuthenticatedExportExecutionContext;
use Modules\Table\Tests\Support\LegacyAuthProbeTable;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

it('provides the host tenant-aware export execution context', function (): void {
    expect(app(ExportExecutionContext::class))
        ->toBeInstanceOf(TenantAwareExportExecutionContext::class);
});

it('restores the default auth context when an export callback throws', function (): void {
    $adminRole = SpatieRole::findOrCreate(Role::ADMIN->value, 'web');
    $adminRole->givePermissionTo(Permission::findOrCreate('access all company data', 'web'));

    $previous = User::factory()->forInternalCompany()->create();
    $previous->assignRole(Role::ADMIN);
    $owner    = User::factory()->create();
    auth()->setUser($previous);

    $context = new AuthenticatedExportExecutionContext;

    expect(fn () => $context->run($owner->id, function () use ($owner): never {
        expect(auth()->id())->toBe($owner->id);

        throw new RuntimeException('Export failed.');
    }))->toThrow(RuntimeException::class, 'Export failed.')
        ->and(auth()->id())->toBe($previous->id);
});

it('runs legacy queued exports as their owner and restores the previous tenant', function (): void {
    SpatieRole::findOrCreate(Role::EMPLOYEE->value, 'web');
    SpatieRole::findOrCreate(Role::COMPANY_MANAGER->value, 'web');

    $company  = Company::factory()->create(['is_internal' => false]);
    $owner    = User::factory()->create(['company_id' => $company->id]);
    $previous = User::factory()->create(['company_id' => $company->id]);
    $previous->assignRole(Role::EMPLOYEE, Role::COMPANY_MANAGER);

    $tenant = app(TenantContext::class);

    auth()->setUser($previous);
    auth('company')->setUser($previous);
    $tenant->establish($previous);
    LegacyAuthProbeTable::$observedContext = null;

    (new ExportJob(LegacyAuthProbeTable::make(), 0, $owner->id))->handle();

    expect(LegacyAuthProbeTable::$observedContext)->toBe([
        'authUserId'   => $owner->id,
        'tenantUserId' => $owner->id,
        'companyId'    => $company->id,
    ])->and(auth()->id())->toBe($previous->id)
        ->and(auth('company')->id())->toBe($previous->id)
        ->and($tenant->userId())->toBe($previous->id)
        ->and($tenant->companyId())->toBe($company->id);
});

it('carries the owner context into the downstream Laravel Excel jobs', function (): void {
    SpatieRole::findOrCreate(Role::EMPLOYEE->value, 'web');
    SpatieRole::findOrCreate(Role::COMPANY_MANAGER->value, 'web');

    $company  = Company::factory()->create(['is_internal' => false]);
    $owner    = User::factory()->create(['company_id' => $company->id]);
    $previous = User::factory()->create(['company_id' => $company->id]);
    $previous->assignRole(Role::EMPLOYEE, Role::COMPANY_MANAGER);

    $tenant = app(TenantContext::class);

    auth()->setUser($previous);
    $tenant->establish($previous);

    $export = TestUsersTable::make()->getExportById(0)
        ?? throw new RuntimeException('Legacy export is missing.');
    $middleware = $export->makeExporter($owner->id)->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RunExportAsOwner::class);

    $middleware[0]->handle(new stdClass, function () use ($company, $owner, $tenant): void {
        expect(auth()->id())->toBe($owner->id)
            ->and($tenant->userId())->toBe($owner->id)
            ->and($tenant->companyId())->toBe($company->id);
    });

    expect(auth()->id())->toBe($previous->id)
        ->and($tenant->userId())->toBe($previous->id)
        ->and($tenant->companyId())->toBe($company->id);
});

it('does not execute exports for a deleted owner', function (): void {
    $owner = User::factory()->create();
    $owner->delete();

    expect(fn () => app(ExportExecutionContext::class)->run($owner->id, fn (): null => null))
        ->toThrow(RuntimeException::class, 'The tenant user no longer exists.');
});
