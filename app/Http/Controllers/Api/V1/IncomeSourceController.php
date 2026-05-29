<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Models\IncomeSource;

class IncomeSourceController extends Controller
{
    use CrudController;
    protected string $modelClass = IncomeSource::class;
}
