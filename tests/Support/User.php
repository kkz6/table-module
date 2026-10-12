<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

// Export fixtures use the host application's configured authenticatable model.
// An alias preserves its factory and relationships without coupling this package
// to any particular application's module namespace.
class_alias(config('auth.providers.users.model'), __NAMESPACE__.'\\User');
