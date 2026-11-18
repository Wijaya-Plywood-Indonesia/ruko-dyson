{{-- resources/views/filament/pages/rekap-penjualan.blade.php --}}
<x-filament-panels::page>
    @php
        $r = $this->rekap;
        $rp = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');

        $kartu = [
            ['Jumlah Nota', number_format($r['jumlah'], 0, ',', '.')],
            ['Item Terjual', number_format($r['item'], 0, ',', '.')],
            ['Total Omzet', $rp($r['omzet'])],
            ['Total Diskon', $rp($r['diskon'])],
            ['Rata-rata per Nota', $rp($r['rata'])],
        ];

        $warnaStatus = [
            'LUNAS' => 'success',
            'COD' => 'warning',
            'PENDING' => 'gray',
            'DIBATALKAN' => 'danger',
        ];
    @endphp

    <style>
        .rk-filter { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .rk-label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: 4px; }
        .rk-note { margin-top: 12px; font-size: .8rem; opacity: .7; }
        .rk-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; }
        .rk-card-label { font-size: .8rem; opacity: .7; }
        .rk-card-value { font-size: 1.4rem; font-weight: 700; margin-top: 4px; }
        .rk-duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .rk-scroll { overflow-x: auto; }
        .rk-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .rk-table th, .rk-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .25); white-space: nowrap; }
        .rk-table th { font-weight: 600; opacity: .8; }
        .rk-table tfoot td { font-weight: 700; }
        .rk-table .rk-r { text-align: right; }
        .rk-kosong { text-align: center !important; padding: 20px !important; opacity: .6; }
    </style>

    {{-- ================= FILTER ================= --}}
    <x-filament::section>
        <div class="rk-filter">
            <div>
                <label class="rk-label">Dari Tanggal</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="dari" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="rk-label">Sampai Tanggal</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="sampai" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="rk-label">Status Transaksi</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        @foreach ($this->statusOptions as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <p class="rk-note">Hanya transaksi yang sudah divalidasi yang dihitung. Tanggal mengikuti tanggal nota.</p>
    </x-filament::section>

    {{-- ================= KARTU RINGKASAN ================= --}}
    <div class="rk-cards">
        @foreach ($kartu as [$label, $nilai])
            <x-filament::section>
                <div class="rk-card-label">{{ $label }}</div>
                <div class="rk-card-value">{{ $nilai }}</div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- ================= PER METODE & PER STATUS ================= --}}
    <div class="rk-duo">
        <x-filament::section>
            <x-slot name="heading">Per Metode Pembayaran</x-slot>
            <div class="rk-scroll">
                <table class="rk-table">
                    <thead>
                        <tr>
                            <th>Metode</th>
                            <th class="rk-r">Jumlah Nota</th>
                            <th class="rk-r">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($r['perMetode'] as $baris)
                            <tr>
                                <td>{{ $baris['nama'] }}</td>
                                <td class="rk-r">{{ number_format($baris['jumlah'], 0, ',', '.') }}</td>
                                <td class="rk-r">{{ $rp($baris['nilai']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="rk-kosong">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Per Status</x-slot>
            <div class="rk-scroll">
                <table class="rk-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="rk-r">Jumlah Nota</th>
                            <th class="rk-r">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($r['perStatus'] as $baris)
                            <tr>
                                <td>
                                    <x-filament::badge :color="$warnaStatus[$baris['nama']] ?? 'gray'">
                                        {{ $baris['nama'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="rk-r">{{ number_format($baris['jumlah'], 0, ',', '.') }}</td>
                                <td class="rk-r">{{ $rp($baris['nilai']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="rk-kosong">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- ================= BARANG TERLARIS ================= --}}
    <x-filament::section>
        <x-slot name="heading">10 Barang Terlaris</x-slot>
        <div class="rk-scroll">
            <table class="rk-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Barang</th>
                        <th class="rk-r">Qty Terjual</th>
                        <th class="rk-r">Total Penjualan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['topBarang'] as $i => $baris)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $baris['nama'] }}</td>
                            <td class="rk-r">{{ number_format($baris['qty'], 0, ',', '.') }}</td>
                            <td class="rk-r">{{ $rp($baris['nilai']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="rk-kosong">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ================= DAFTAR NOTA ================= --}}
    <x-filament::section>
        <x-slot name="heading">Daftar Nota</x-slot>
        <div class="rk-scroll">
            <table class="rk-table">
                <thead>
                    <tr>
                        <th>No Nota</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Kasir</th>
                        <th class="rk-r">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['notas'] as $nota)
                        <tr>
                            <td style="font-family: monospace;">{{ $nota['no_nota'] }}</td>
                            <td>{{ $nota['tanggal'] }}</td>
                            <td>{{ $nota['customer'] }}</td>
                            <td>{{ $nota['metode'] }}</td>
                            <td>
                                <x-filament::badge :color="$warnaStatus[$nota['status']] ?? 'gray'">
                                    {{ $nota['status'] }}
                                </x-filament::badge>
                            </td>
                            <td>{{ $nota['kasir'] }}</td>
                            <td class="rk-r">{{ $rp($nota['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="rk-kosong">Tidak ada transaksi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if (count($r['notas']) > 0)
                    <tfoot>
                        <tr>
                            <td colspan="6">Total</td>
                            <td class="rk-r">{{ $rp($r['omzet']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>