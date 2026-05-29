<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use CrudController;
    protected string $modelClass = Document::class;

    public function store(FinanceRequest $request)
    {
        $file = $request->file('file');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,xlsx,csv', 'max:10240'],
            'name' => ['nullable', 'string', 'max:191'],
            'tags' => ['nullable', 'array'],
        ]);

        $path = $file->store('private/users/'.$request->user()->id);

        return ApiResource::make(Document::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'tags' => $data['tags'] ?? [],
        ]));
    }
}
