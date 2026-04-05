<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaasCustomerController extends Controller
{
    public function index(): View
    {
        $customers = SaasCustomer::query()->orderByDesc('id')->paginate(25);

        return view('admin.saas.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('admin.saas.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,suspended'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        SaasCustomer::query()->create($data);

        return redirect()->route('admin.saas.customers.index')->with('status', 'Müşteri oluşturuldu.');
    }

    public function edit(SaasCustomer $customer): View
    {
        return view('admin.saas.customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, SaasCustomer $customer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,suspended'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $customer->update($data);

        return redirect()->route('admin.saas.customers.index')->with('status', 'Müşteri güncellendi.');
    }

    public function destroy(SaasCustomer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('admin.saas.customers.index')->with('status', 'Müşteri silindi.');
    }
}
