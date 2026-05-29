<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $route = $this->route()?->getName();

        return match ($route) {
            'income.store', 'income.update' => [
                'amount' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
                'source_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer'],
                'received_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
                'description' => ['nullable', 'string', 'max:1000'],
                'is_blocked' => ['sometimes', 'boolean'],
            ],
            'expenses.store', 'expenses.update' => [
                'amount' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
                'category_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer'],
                'expense_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
                'description' => ['nullable', 'string', 'max:1000'],
                'is_blocked' => ['sometimes', 'boolean'],
            ],
            'income-sources.store', 'income-sources.update' => [
                'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:191'],
                'type' => ['sometimes', Rule::in(['active', 'passive'])],
                'is_blocked' => ['sometimes', 'boolean'],
            ],
            'expense-categories.store', 'expense-categories.update' => [
                'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:191'],
                'color' => ['sometimes', 'string', 'max:20'],
                'icon' => ['sometimes', 'string', 'max:50'],
                'is_blocked' => ['sometimes', 'boolean'],
            ],
            'budgets.store', 'budgets.update' => [
                'category_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer'],
                'amount' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0.01'],
                'start_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
                'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
                'period' => ['sometimes', Rule::in(['monthly'])],
                'alert_at_percent' => ['sometimes', 'integer', 'between:1,100'],
                'is_blocked' => ['sometimes', 'boolean'],
            ],
            'reminders.store', 'reminders.update' => [
                'title' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:191'],
                'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'due_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
                'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
                'frequency' => ['sometimes', 'string', 'max:50'],
                'notify_before_days' => ['sometimes', 'integer', 'min:0'],
                'is_paid' => ['sometimes', 'boolean'],
            ],
            default => [
                'name' => ['sometimes', 'string', 'max:191'],
                'title' => ['sometimes', 'string', 'max:191'],
                'amount' => ['sometimes', 'numeric', 'min:0'],
                'currency' => ['sometimes', 'string', 'size:3'],
                'type' => ['sometimes', 'string', 'max:50'],
                'source_id' => ['sometimes', 'integer'],
                'account_id' => ['sometimes', 'integer'],
                'category_id' => ['sometimes', 'nullable', 'integer'],
                'from_account_id' => ['sometimes', 'integer'],
                'to_account_id' => ['sometimes', 'integer'],
                'parent_id' => ['sometimes', 'nullable', 'integer'],
                'received_date' => ['sometimes', 'date'],
                'expense_date' => ['sometimes', 'date'],
                'transaction_date' => ['sometimes', 'date'],
                'transferred_at' => ['sometimes', 'date'],
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'nullable', 'date'],
                'due_date' => ['sometimes', 'nullable', 'date'],
                'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
                'next_due_date' => ['sometimes', 'date'],
                'paid_at' => ['sometimes', 'date'],
                'period' => ['sometimes', 'in:monthly,weekly,yearly'],
                'frequency' => ['sometimes', 'string', 'max:50'],
                'payment_method' => ['sometimes', 'in:cash,card,online,cheque'],
                'status' => ['sometimes', 'string', 'max:50'],
                'person_name' => ['sometimes', 'string', 'max:191'],
                'original_amount' => ['sometimes', 'numeric', 'min:0'],
                'remaining_amount' => ['sometimes', 'numeric', 'min:0'],
                'fee' => ['sometimes', 'numeric', 'min:0'],
                'color' => ['sometimes', 'string', 'max:20'],
                'icon' => ['sometimes', 'string', 'max:50'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'alert_at_percent' => ['sometimes', 'integer', 'between:1,100'],
                'notify_before_days' => ['sometimes', 'integer', 'min:0'],
                'is_active' => ['sometimes', 'boolean'],
                'is_default' => ['sometimes', 'boolean'],
                'is_system' => ['sometimes', 'boolean'],
                'is_paid' => ['sometimes', 'boolean'],
                'is_recurring' => ['sometimes', 'boolean'],
                'is_blocked' => ['sometimes', 'boolean'],
                'recurrence_rule' => ['sometimes', 'nullable', 'array'],
                'tags' => ['sometimes', 'nullable', 'array'],
                'location' => ['nullable', 'string', 'max:191'],
                'client_name' => ['nullable', 'string', 'max:191'],
                'invoice_no' => ['nullable', 'string', 'max:191'],
                'started_at' => ['sometimes', 'nullable', 'date'],
                'description' => ['nullable', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xlsx,csv', 'max:10240'],
            ],
        };
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($this->hasFile('attachment')) {
            $data['attachment'] = $this->file('attachment')->store('private/users/'.$this->user()->id);
        }

        return $data;
    }
}
