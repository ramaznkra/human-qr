<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Services\TableQrCodeService;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TableController extends Controller
{
    public function __construct(private TableQrCodeService $qr) {}

    public function index(): View
    {
        $tables = Table::query()->orderByNumber()->get();

        return view('admin.tables.index', compact('tables'));
    }

    public function create(): View
    {
        return view('admin.tables.form', ['table' => new Table]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                TenantRules::uniqueModel(Table::class, 'number'),
            ],
        ]);
        $data['qr_token'] = Table::generateToken();
        $data['is_active'] = true;

        $table = Table::create($data);
        $this->qr->generateFor($table);

        return redirect()->route('admin.tables.index')->with('success', 'Masa eklendi ve QR kod oluşturuldu.');
    }

    public function edit(Table $table): View
    {
        return view('admin.tables.form', compact('table'));
    }

    public function update(Request $request, Table $table): RedirectResponse
    {
        $data = $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                TenantRules::uniqueModel(Table::class, 'number', $table->id),
            ],
        ]);

        $numberChanged = $table->number !== $data['number'];
        $table->update($data);

        if ($numberChanged) {
            $this->qr->deleteFor($table);
            $this->qr->generateFor($table->fresh());
        }

        return redirect()->route('admin.tables.index')->with('success', 'Masa güncellendi.');
    }

    public function destroy(Table $table): RedirectResponse
    {
        $this->qr->deleteFor($table);
        $table->delete();

        return redirect()->route('admin.tables.index')->with('success', 'Masa silindi.');
    }

    public function toggleActive(Table $table): JsonResponse
    {
        $table->update(['is_active' => ! $table->is_active]);

        return response()->json([
            'success' => true,
            'table_id' => $table->id,
            'is_active' => $table->is_active,
            'label' => $table->is_active ? 'Masa açık' : 'Masa kapalı',
        ]);
    }

    public function regenerate(Table $table): RedirectResponse
    {
        $table->update(['qr_token' => Table::generateToken()]);
        $this->qr->generateFor($table->fresh());

        return back()->with('success', 'QR kod yeniden oluşturuldu.');
    }

    public function qrPng(Table $table): Response
    {
        $filename = 'masa-'.$table->number.'-qr.png';

        return response($this->qr->pngContents($table), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function qrSvg(Table $table): Response
    {
        $filename = 'masa-'.$table->number.'-qr.svg';

        return response($this->qr->svgContents($table), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
