<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan OEE Harian — {{ $date->format('d M Y') }}</title>
    <style>
        @page { margin: 100px 30px 55px 30px; }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 9.5px;
            color: #1F2937;
        }

        .header {
            position: fixed;
            top: -85px;
            left: 0;
            right: 0;
            height: 80px;
            border-bottom: 2.5px solid #0F172A;
            padding-bottom: 10px;
        }

        .header .brand-mark {
            display: inline-block;
            width: 4px;
            height: 18px;
            background: #F59E0B;
            margin-right: 6px;
            vertical-align: middle;
        }

        .header h1 {
            display: inline-block;
            font-size: 15px;
            font-weight: bold;
            margin: 0;
            color: #0F172A;
            vertical-align: middle;
        }

        .header .subtitle {
            font-size: 9px;
            color: #64748B;
            margin: 3px 0 6px 10px;
        }

        .header .meta-table { width: 100%; border: none; margin: 0; }
        .header .meta-table td { border: none; padding: 1px 0; font-size: 8.5px; color: #475569; }
        .header .meta-table .meta-label {
            color: #94A3B8;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.03em;
            width: 90px;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #0F172A;
            margin: 14px 0 7px 0;
            padding-left: 8px;
            border-left: 3px solid #F59E0B;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .data-table th {
            background: #0F172A;
            color: #F8FAFC;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 5px 6px;
            text-align: left;
        }

        .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #E5E7EB;
        }

        .data-table tbody tr.band-bad { background: #FEF2F2; }
        .data-table tbody tr.band-warn { background: #FFFBEB; }
        .data-table tbody tr.band-good { background: #F0FDF4; }

        .oee-value.band-bad { color: #B91C1C; font-weight: bold; }
        .oee-value.band-warn { color: #B45309; font-weight: bold; }
        .oee-value.band-good { color: #15803D; font-weight: bold; }

        .no-data {
            padding: 10px;
            color: #94A3B8;
            font-style: italic;
        }
    </style>
</head>
<body>

    <div class="header">
        <span class="brand-mark"></span><h1>FactoryOS</h1>
        <div class="subtitle">Laporan OEE Harian</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Tanggal</td>
                <td>{{ $date->format('d M Y') }}</td>
                <td class="meta-label">Mesin</td>
                <td>{{ $work_center->name ?? 'Semua Mesin' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Digenerate</td>
                <td>{{ $generated_at->format('d M Y H:i') }}</td>
                <td class="meta-label">Oleh</td>
                <td>{{ $user->name ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">OEE per Mesin per Shift</div>
    @if ($rows->isEmpty())
        <div class="no-data">Tidak ada log produksi untuk tanggal ini.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mesin</th>
                    <th>Shift</th>
                    <th>Planned (mnt)</th>
                    <th>Downtime (mnt)</th>
                    <th>Output</th>
                    <th>Good Output</th>
                    <th>Availability</th>
                    <th>Performance</th>
                    <th>Quality</th>
                    <th>OEE</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ $row['oee_band'] ? 'band-'.$row['oee_band'] : '' }}">
                        <td>{{ $row['log']->workCenter->name }}</td>
                        <td>{{ $row['log']->shift->name }}</td>
                        <td>{{ number_format((float) $row['log']->planned_minutes, 0) }}</td>
                        <td>{{ number_format((float) $row['log']->downtime_minutes, 0) }}</td>
                        <td>{{ number_format((float) $row['log']->actual_output, 0) }}</td>
                        <td>{{ number_format((float) $row['log']->good_output, 0) }}</td>
                        @if ($row['snapshot'])
                            <td>{{ number_format((float) $row['snapshot']->availability * 100, 1) }}%</td>
                            <td>{{ number_format((float) $row['snapshot']->performance * 100, 1) }}%</td>
                            <td>{{ number_format((float) $row['snapshot']->quality * 100, 1) }}%</td>
                            <td class="oee-value {{ 'band-'.$row['oee_band'] }}">
                                {{ number_format((float) $row['snapshot']->oee * 100, 1) }}%
                            </td>
                        @else
                            <td colspan="4" style="color:#94A3B8;font-style:italic;">Belum dihitung</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Ringkasan Pareto Downtime</div>
    @if (empty($pareto))
        <div class="no-data">Tidak ada downtime tercatat untuk tanggal ini.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Total Downtime (mnt)</th>
                    <th>Persentase</th>
                    <th>Kumulatif</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pareto as $row)
                    <tr>
                        <td>{{ ucfirst($row['category']) }}</td>
                        <td>{{ number_format((float) $row['total_minutes'], 0) }}</td>
                        <td>{{ number_format((float) $row['percentage'], 1) }}%</td>
                        <td>{{ number_format((float) $row['cumulative'], 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>