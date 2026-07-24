<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardKpiService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController — GET summary KPI lintas 3 engine (FR-10, docs/prd.md).
 *
 * Menggantikan halaman /dashboard bawaan Breeze (sebelumnya Blade kosong
 * `view('dashboard')`) menjadi Dashboard KPI Inertia yang sesungguhnya.
 *
 * Thin controller murni: tidak ada kalkulasi apapun di sini, semua
 * agregasi didelegasikan ke DashboardKpiService.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardKpiService $kpi,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'engine1' => $this->kpi->engine1Summary(),
            'engine2' => $this->kpi->engine2Summary(),
            'engine3' => $this->kpi->engine3Summary(),
        ]);
    }
}