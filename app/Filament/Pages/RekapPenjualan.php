<?php

namespace App\Filament\Pages;

use App\Exports\RekapPenjualanExport;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class RekapPenjualan extends Page
{
    protected string $view = 'filament.pages.rekap-penjualan';

    /* ================= MENU SIDEBAR ================= */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?string $navigationLabel = 'Rekap Penjualan';
    protected static ?string $title = 'Rekap Penjualan';
    protected static ?int $navigationSort = 3;

    /* ================= FILTER ================= */
    public string $dari = '';
    public string $sampai = '';
    public string $status = 'omzet';

    /**
     * Menu hanya tampil untuk user yang boleh melihat daftar penjualan,
     * jadi tidak perlu membuat permission baru di Shield.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('ViewAny:Penjualan') ?? false;
    }

    public function mount(): void
    {
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
    }

    /* ================= PILIHAN STATUS ================= */
    #[Computed]
    public function statusOptions(): array
    {
        return [
            'omzet' => 'Omzet (Lunas + COD)',
            'LUNAS' => 'Lunas',
            'COD' => 'COD',
            'PENDING' => 'Pending',
            'DIBATALKAN' => 'Dibatalkan',
            'semua' => 'Semua status',
        ];
    }

    /* ================= QUERY DASAR ================= */

    /** Rentang tanggal; otomatis ditukar kalau "dari" lebih besar dari "sampai". */
    protected function rentang(): array
    {
        $dari = $this->dari ?: now()->startOfMonth()->toDateString();
        $sampai = $this->sampai ?: now()->toDateString();

        return $dari <= $sampai ? [$dari, $sampai] : [$sampai, $dari];
    }

    /** Semua angka di halaman ini bersumber dari query yang sama. */
    protected function baseQuery(): Builder
    {
        [$dari, $sampai] = $this->rentang();

        return Penjualan::query()
            ->whereNotNull('validated_by') // hanya transaksi yang sudah divalidasi
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->when(
                $this->status === 'omzet',
                fn (Builder $q) => $q->whereIn('status_transaksi', ['LUNAS', 'COD'])
            )
            ->when(
                in_array($this->status, ['LUNAS', 'COD', 'PENDING', 'DIBATALKAN'], true),
                fn (Builder $q) => $q->where('status_transaksi', $this->status)
            );
    }

    /* ================= DATA REKAP ================= */
    #[Computed]
    public function rekap(): array
    {
        $header = $this->baseQuery()
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(total), 0) as omzet')
            ->first();

        $detail = DetailPenjualan::query()
            ->whereIn('penjualan_id', $this->baseQuery()->select('id'))
            ->selectRaw('COALESCE(SUM(qty), 0) as item, COALESCE(SUM(COALESCE(potongan, 0) * qty), 0) as diskon')
            ->first();

        $jumlah = (int) $header->jumlah;
        $omzet = (float) $header->omzet;

        $perMetode = $this->baseQuery()
            ->selectRaw('metode_pembayaran as nama, COUNT(*) as jumlah, COALESCE(SUM(total), 0) as nilai')
            ->groupBy('metode_pembayaran')
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'jumlah' => (int) $r->jumlah, 'nilai' => (float) $r->nilai])
            ->all();

        $perStatus = $this->baseQuery()
            ->selectRaw('status_transaksi as nama, COUNT(*) as jumlah, COALESCE(SUM(total), 0) as nilai')
            ->groupBy('status_transaksi')
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'jumlah' => (int) $r->jumlah, 'nilai' => (float) $r->nilai])
            ->all();

        $topBarang = DetailPenjualan::query()
            ->whereIn('penjualan_id', $this->baseQuery()->select('id'))
            ->selectRaw('nama_barang, SUM(qty) as total_qty, SUM(subtotal) as total_subtotal')
            ->groupBy('nama_barang')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nama' => $r->nama_barang,
                'qty' => (int) $r->total_qty,
                'nilai' => (float) $r->total_subtotal,
            ])
            ->all();

        $notas = $this->baseQuery()
            ->with('user:id,name')
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn ($p) => [
                'no_nota' => $p->no_nota,
                'tanggal' => $p->tanggal?->format('d/m/Y H:i'),
                'customer' => $p->nama_customer,
                'metode' => $p->metode_pembayaran,
                'status' => $p->status_transaksi,
                'total' => (float) $p->total,
                'kasir' => $p->user?->name ?? '-',
            ])
            ->all();

        return [
            'jumlah' => $jumlah,
            'item' => (int) $detail->item,
            'omzet' => $omzet,
            'diskon' => (float) $detail->diskon,
            'rata' => $jumlah > 0 ? $omzet / $jumlah : 0,
            'perMetode' => $perMetode,
            'perStatus' => $perStatus,
            'topBarang' => $topBarang,
            'notas' => $notas,
        ];
    }

    /* ================= EXPORT ================= */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    [$dari, $sampai] = $this->rentang();

                    $periode = Carbon::parse($dari)->format('d/m/Y')
                        . ' s/d '
                        . Carbon::parse($sampai)->format('d/m/Y');

                    return Excel::download(
                        new RekapPenjualanExport(
                            $this->rekap,
                            $periode,
                            $this->statusOptions[$this->status] ?? $this->status,
                        ),
                        "Rekap-Penjualan-{$dari}-sd-{$sampai}.xlsx"
                    );
                }),
        ];
    }
}