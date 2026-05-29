<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Models\Transfer;

class TransferController extends Controller
{
    use CrudController;
    protected string $modelClass = Transfer::class;
    protected array $with = ['fromAccount', 'toAccount'];
}
