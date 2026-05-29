<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Models\Account;

class AccountController extends Controller
{
    use CrudController;
    protected string $modelClass = Account::class;
}
