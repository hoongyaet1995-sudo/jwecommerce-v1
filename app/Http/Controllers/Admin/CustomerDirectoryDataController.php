<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CustomerDirectoryDataService;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDirectoryDataController extends Controller
{
    public function index(Request $request, CustomerDirectoryDataService $customers): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        return response()->json($customers->data($filters['search'] ?? null));
    }

    public function store(Request $request, CustomerDirectoryDataService $directory): JsonResponse
    {
        $data = $this->validated($request);
        $customer = DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create($this->customerAttributes($data));
            $this->replaceAddresses($customer, $data['addresses'] ?? []);
            return $customer;
        });
        return response()->json(['data' => $directory->findData($customer)], 201);
    }

    public function update(Request $request, Customer $customer, CustomerDirectoryDataService $directory): JsonResponse
    {
        $data = $this->validated($request, $customer);
        DB::transaction(function () use ($customer, $data): void {
            $customer->update($this->customerAttributes($data));
            $this->replaceAddresses($customer, $data['addresses'] ?? []);
        });
        return response()->json(['data' => $directory->findData($customer)]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required','string','max:255'], 'email' => ['required','email','max:255',Rule::unique('customers','email')->ignore($customer)],
            'phone' => ['nullable','string','max:50'], 'avatar' => ['nullable','string','max:2048'],
            'status' => ['required',Rule::in(['active','vip','blocked'])], 'tags' => ['array','max:100'], 'tags.*' => ['string','max:100'],
            'notes' => ['nullable','string','max:10000'], 'addresses' => ['array','max:50'], 'addresses.*.id' => ['nullable','string','max:64'],
            'addresses.*.isDefault' => ['required','boolean'], 'addresses.*.type' => ['required',Rule::in(['shipping','billing'])],
            'addresses.*.firstName' => ['required','string','max:255'], 'addresses.*.lastName' => ['required','string','max:255'],
            'addresses.*.phone' => ['nullable','string','max:50'], 'addresses.*.address1' => ['required','string','max:255'],
            'addresses.*.address2' => ['nullable','string','max:255'], 'addresses.*.city' => ['required','string','max:255'],
            'addresses.*.province' => ['nullable','string','max:255'], 'addresses.*.country' => ['required','string','max:100'],
            'addresses.*.postalCode' => ['required','string','max:50'],
        ]);
    }

    private function customerAttributes(array $data): array
    {
        $parts = preg_split('/\s+/', trim($data['name']), 2) ?: [$data['name']];
        return ['first_name'=>$parts[0], 'last_name'=>$parts[1] ?? null, 'email'=>$data['email'], 'phone'=>$data['phone'] ?? null,
            'avatar'=>$data['avatar'] ?? null, 'status'=>$data['status'], 'tags'=>$data['tags'] ?? [], 'notes'=>$data['notes'] ?? null];
    }

    private function replaceAddresses(Customer $customer, array $addresses): void
    {
        $existing = $customer->addresses()->get()->keyBy('id'); $kept = []; $defaultClaimed = false;
        foreach ($addresses as $address) {
            $id = ctype_digit((string) ($address['id'] ?? '')) ? (int) $address['id'] : null;
            $isDefault = ! $defaultClaimed && $address['isDefault'];
            $defaultClaimed = $defaultClaimed || $isDefault;
            $attributes = ['type'=>$address['type'],'is_default'=>$isDefault,'first_name'=>$address['firstName'],'last_name'=>$address['lastName'],
                'phone'=>$address['phone'] ?? null,'line_1'=>$address['address1'],'line_2'=>$address['address2'] ?? null,'city'=>$address['city'],
                'state'=>$address['province'] ?? null,'country'=>$address['country'],'postal_code'=>$address['postalCode']];
            $model = $id && $existing->has($id) ? $existing[$id] : $customer->addresses()->create($attributes);
            if ($id && $existing->has($id)) $model->update($attributes);
            $kept[] = $model->id;
        }
        $customer->addresses()->whereNotIn('id', $kept)->delete();
    }
}
