<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    use CrudController;
    protected string $modelClass = Reminder::class;

    public function markPaid(Request $request, int $id)
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);
        $reminder->update(['is_paid' => true]);
        return ApiResource::make($reminder->refresh());
    }

    public function markOpen(Request $request, int $id)
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);
        $reminder->update(['is_paid' => false]);
        return ApiResource::make($reminder->refresh());
    }
}
