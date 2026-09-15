<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Audit\AuditLogger;
use App\Support\MasterOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductPriceController extends Controller
{
    public function index(Request $request): View
    {
        $prices = ProductPrice::query()
            ->with(['product', 'institution', 'doctor'])
            ->when($request->filled('product_id'), fn (Builder $query): Builder => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('institution_id'), fn (Builder $query): Builder => $query->where('institution_id', $request->integer('institution_id')))
            ->when($request->filled('doctor_id'), fn (Builder $query): Builder => $query->where('doctor_id', $request->integer('doctor_id')))
            ->when($request->filled('price_type'), fn (Builder $query): Builder => $query->where('price_type', $request->string('price_type')->value()))
            ->when($request->filled('active'), function (Builder $query) use ($request): void {
                $query->where('active', $request->string('active')->value() === 'active');
            })
            ->orderBy('product_id')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('masters.prices.index', [
            'prices' => $prices,
            'products' => Product::query()->where('active', true)->orderBy('product_code')->orderBy('name')->get(),
            'institutions' => Institution::query()->where('active', true)->orderBy('name')->get(),
            'doctors' => Doctor::query()->where('active', true)->orderBy('name')->get(),
            'priceTypes' => MasterOptions::PRICE_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('masters.prices.create', $this->formOptions());
    }

    public function store(StoreProductPriceRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $price = DB::transaction(fn (): ProductPrice => ProductPrice::create($data));
        $price->load(['product', 'institution', 'doctor']);
        $auditLogger->record('price.created', $price, [], $this->snapshot($price));

        return redirect()->route('masters.prices.index')
            ->with('status', 'Precio de producto creado correctamente.');
    }

    public function edit(ProductPrice $price): View
    {
        $price->load(['product', 'institution', 'doctor']);

        return view('masters.prices.edit', $this->formOptions($price));
    }

    public function update(UpdateProductPriceRequest $request, ProductPrice $price, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $this->snapshot($price);
        $price->update($request->validated());
        $price->refresh()->load(['product', 'institution', 'doctor']);
        $after = $this->snapshot($price);
        $auditLogger->record('price.updated', $price, $before, $after);

        if ($before['active'] !== $after['active']) {
            $auditLogger->record($after['active'] ? 'price.enabled' : 'price.disabled', $price, $before, $after);
        }

        return redirect()->route('masters.prices.index', ['product_id' => $price->product_id])
            ->with('status', 'Precio de producto actualizado correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?ProductPrice $price = null): array
    {
        $selectedInstitutionId = $price?->institution_id;
        $selectedDoctorId = $price?->doctor_id;

        return [
            'price' => $price,
            'products' => Product::query()->where(function (Builder $query) use ($price): void {
                $query->where('active', true);

                if ($price?->product_id !== null) {
                    $query->orWhere('id', $price->product_id);
                }
            })->orderBy('product_code')->orderBy('name')->get(),
            'institutions' => Institution::query()->where(function (Builder $query) use ($selectedInstitutionId): void {
                $query->where('active', true);

                if ($selectedInstitutionId !== null) {
                    $query->orWhere('id', $selectedInstitutionId);
                }
            })->orderBy('name')->get(),
            'doctors' => Doctor::query()->where(function (Builder $query) use ($selectedDoctorId): void {
                $query->where('active', true);

                if ($selectedDoctorId !== null) {
                    $query->orWhere('id', $selectedDoctorId);
                }
            })->orderBy('name')->get(),
            'priceTypes' => MasterOptions::PRICE_TYPES,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(ProductPrice $price): array
    {
        return [
            'product_id' => $price->product_id,
            'institution_id' => $price->institution_id,
            'doctor_id' => $price->doctor_id,
            'unit_price' => $price->unit_price,
            'minimum_price' => $price->minimum_price,
            'currency' => $price->currency,
            'price_type' => $price->price_type,
            'valid_from' => $price->valid_from?->toDateString(),
            'valid_until' => $price->valid_until?->toDateString(),
            'active' => (bool) $price->active,
        ];
    }
}
