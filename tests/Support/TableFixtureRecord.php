<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TableFixtureRecord extends Model
{
    use SoftDeletes;

    protected $table = 'table_fixture_records';

    public $timestamps = false;

    protected $guarded = [];

    public static function createSchema(): void
    {
        Schema::create('table_fixture_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->softDeletes();
        });
    }
}
