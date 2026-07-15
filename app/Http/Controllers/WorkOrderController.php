<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkOrderOperationGenerationException;
use App\Exceptions\WorkOrderStatusException;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderStatusRequest;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WoOperationGeneratorService;
use App\Services\WorkOrder\WorkOrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WorkOrderController — thin controller sesuai docs/architecture.md.
 *
 * Tidak ada kalkulasi atau logic percabangan status di sini; semua didelegasikan
 * ke WoOperationGeneratorService dan WorkOrderStatusService.
 */
class WorkOrderController extends Controller
{
    public function __construct(
        private readonly WoOperationGeneratorService $operationGenerator,
        private readonly WorkOrderStatusService $statusService,
    ) {
    }

    /**
     * Daftar Work Order, dengan filter status opsional via query string.
     */
    public function index(Request $request): Response
    {
        $workOrders = WorkOrder::query()
            ->with('product')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $workOrders,
            'filters'    => $request->only('status'),
        ]);
    }

    /**
     * Form pembuatan Work Order baru.
     */
    public function create(): Response
    {
        return Inertia::render('WorkOrders/Create', [
            // Hanya produk yang sudah punya routing yang boleh ditawarkan,
            // supaya generate wo_operations tidak gagal setelah WO dibuat.
            'products' => Product::query()->has('routings')->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    /**
     * Simpan Work Order baru, lalu langsung generate wo_operations dari routing produk.
     */
    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['release_date'] = $data['release_date'] ?? now()->toDateString();
        $data['priority'] = $data['priority'] ?? 5;
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        $workOrder = WorkOrder::create($data);

        try {
            $this->operationGenerator->generate($workOrder);
        } catch (WorkOrderOperationGenerationException $e) {
            // WO tetap tersimpan (sesuai FR-02), tapi user diberi tahu operations
            // belum ter-generate agar bisa retry manual setelah routing dilengkapi.
            return redirect()
                ->route('work-orders.show', $workOrder)
                ->with('error', "Work Order berhasil dibuat, tapi gagal generate operasi: {$e->getMessage()}");
        }

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('success', 'Work Order berhasil dibuat beserta operasinya.');
    }

    /**
     * Detail satu Work Order beserta operasi dan produk terkait.
     */
    public function show(WorkOrder $workOrder): Response
    {
        $workOrder->load(['product', 'operations' => fn ($q) => $q->orderBy('sequence'), 'operations.workCenter']);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => $workOrder,
        ]);
    }

    /**
     * Form edit Work Order.
     */
    public function edit(WorkOrder $workOrder): Response
    {
        $this->authorize('update', $workOrder);

        return Inertia::render('WorkOrders/Edit', [
            'workOrder' => $workOrder,
            'products'  => Product::orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    /**
     * Update atribut Work Order (bukan status — status lewat updateStatus()).
     */
    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        $workOrder->update($request->validated());

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('success', 'Work Order berhasil diperbarui.');
    }

    /**
     * Transisi status Work Order (draft → scheduled → in_progress → done/late).
     * Validasi transisi didelegasikan ke WorkOrderStatusService.
     */
    public function updateStatus(UpdateWorkOrderStatusRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        try {
            $this->statusService->transition($workOrder, $request->validated('status'));
        } catch (WorkOrderStatusException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status Work Order berhasil diperbarui.');
    }

    /**
     * Regenerate wo_operations secara manual (mis. setelah routing produk diperbaiki).
     */
    public function regenerateOperations(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        try {
            $this->operationGenerator->generate($workOrder, force: true);
        } catch (WorkOrderOperationGenerationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Operasi Work Order berhasil digenerate ulang.');
    }

    /**
     * Hapus Work Order. Ditolak jika status in_progress/done (FR-02),
     * validasi didelegasikan ke WorkOrderStatusService, otorisasi ke Policy.
     */
    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('delete', $workOrder);

        try {
            $this->statusService->assertDeletable($workOrder);
        } catch (WorkOrderStatusException $e) {
            return back()->with('error', $e->getMessage());
        }

        $workOrder->delete();

        return redirect()
            ->route('work-orders.index')
            ->with('success', 'Work Order berhasil dihapus.');
    }
}