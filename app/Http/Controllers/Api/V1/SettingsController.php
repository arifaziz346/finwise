<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function profile(Request $request)
    {
        if ($request->isMethod('put')) {
            $data = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email'], 'phone' => ['nullable', 'string'], 'date_of_birth' => ['nullable', 'date']]);
            $request->user()->update($request->only('name', 'email'));
            $request->user()->profile()->updateOrCreate(['user_id' => $request->user()->id], collect($data)->only(['phone', 'date_of_birth'])->all());
        }
        return ApiResource::make($request->user()->load('profile'));
    }

    public function preferences(Request $request)
    {
        if ($request->isMethod('put')) {
            $request->user()->update($request->validate(['currency' => ['required', 'string', 'size:3'], 'timezone' => ['required', 'string']]));
        }
        return ApiResource::make($request->user());
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required'], 'password' => ['required', 'confirmed', 'min:8']]);
        abort_unless(Hash::check($data['current_password'], $request->user()->password), 422, 'Current password is incorrect.');
        $request->user()->update(['password' => $data['password']]);
        return ['message' => 'Password updated.'];
    }

    public function exportData(Request $request) { return $request->user()->load(['accounts', 'incomeRecords', 'expenseRecords', 'budgets', 'debts']); }
    public function importCsv(Request $request) { $request->validate(['file' => ['required', 'file', 'mimes:csv', 'max:10240']]); return ['message' => 'CSV accepted for processing.']; }
}
