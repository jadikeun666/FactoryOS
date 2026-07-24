<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Jadwal Produksi — Schedule #{{ $schedule->id }}</title>
    <style>
        @page {
            margin: 100px 30px 55px 30px;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 9.5px;
            color: #1F2937;
        }

        /* ===== HEADER ===== */
        .header {
            position: fixed;
            top: -85px;
            left: 0;
            right: 0;
            height: 80px;
            border-bottom: 2.5px solid #0F172A;
            padding-bottom: 10px;
        }

        .header .brand-row {
            display: block;
            width: 100%;
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

        .header .meta-table {
            width: 100%;
            border: none;
            margin: 0;
        }

        .header .meta-table td {
            border: none;
            padding: 1px 0;
            font-size: 8.5px;
            color: #475569;
        }

        .header .meta-table .meta-label {
            color: #94A3B8;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.03em;
            width: 90px;
        }

        /* ===== SECTIONS ===== */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #0F172A;
            margin: 14px 0 7px 0;
            padding-left: 8px;
            border-left: 3px solid #F59E0B;
        }

        /* ===== SUMMARY CARDS ===== */
        .summary-cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .summary-cards td {
            width: 25%;
            text-align: left;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 8px 10px;
            background: #F8FAFC;
        }

        .summary-cards .label {
            font-size: 7.5px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .summary-cards .value {
            font-size: 16px;
            font-weight: bold;
            color: #0F172A;
            margin-top: 2px;
        }

        /* ===== TABLES ===== */
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

        .data-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }

        .data-table tbody tr.row-late {
            background: #FEF2F2;
        }

        .badge-late {
            color: #B91C1C;
            font-weight: bold;
        }

        .badge-ontime {
            color: #15803D;
            font-weight: bold;
        }

        .machine-group-start td {
            border-top: 1.5px solid #CBD5E1;
        }

        .machine-name {
            font-weight: bold;
            color: #0F172A;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand-row">
            <span class="brand-mark"></span><h1>FactoryOS</h1>
        </div>
        <div class="subtitle">Laporan Jadwal Produksi</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Algoritma</td>
                <td>{{ strtoupper($schedule->algorithm) }}</td>
                <td class="meta-label">Dijadwalkan Dari</td>
                <td>{{ $schedule->scheduled_from->format('d M Y H:i') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Digenerate</td>
                <td>{{ $generated_at->format('d M Y H:i') }}</td>
                <td class="meta-label">Oleh</td>
                <td>{{ $user->name ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Ringkasan</div>
    <table class="summary-cards">
        <tr>
            <td>
                <div class="label">Makespan</div>
                <div class="value">{{ number_format((float) $schedule->makespan_minutes, 0) }} <span style="font-size:9px;font-weight:normal;">mnt</span></div>
            </td>
            <td>
                <div class="label">Total Tardiness</div>
                <div class="value">{{ number_format((float) $schedule->total_tardiness_minutes, 0) }} <span style="font-size:9px;font-weight:normal;">mnt</span></div>
            </td>
            <td>
                <div class="label">Late WO Count</div>
                <div class="value">{{ $schedule->late_wo_count }}</div>
            </td>
            <td>
                <div class="label">Mean Flow Time</div>
                <div class="value">{{ number_format((float) $schedule->mean_flow_time_minutes, 0) }} <span style="font-size:9px;font-weight:normal;">mnt</span></div>
            </td>
        </tr>
    </table>

    <div class="section-title">Detail per Work Order</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>No WO</th>
                <th>Produk</th>
                <th>Qty</th>
                <th>Due Date</th>
                <th>Release Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($wo_rows as $row)
                <tr class="{{ $row['is_late'] ? 'row-late' : '' }}">
                    <td>WO-{{ $row['work_order']->id }}</td>
                    <td>{{ $row['work_order']->product->name ?? '—' }}</td>
                    <td>{{ number_format((float) $row['work_order']->qty, 0) }}</td>
                    <td>{{ $row['work_order']->due_date->format('d M Y') }}</td>
                    <td>{{ $row['work_order']->release_date->format('d M Y') }}</td>
                    <td>
                        @if ($row['is_late'])
                            <span class="badge-late">⚠ Terlambat</span>
                        @else
                            <span class="badge-ontime">✓ On Time</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Detail Operasi per Mesin</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Mesin</th>
                <th>WO</th>
                <th>Operasi ke-</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Durasi (mnt)</th>
            </tr>
        </thead>
        <tbody>
            @php $prevWorkCenterId = null; @endphp
            @foreach ($machine_rows as $assignment)
                @php
                    $isGroupStart = $prevWorkCenterId !== null && $prevWorkCenterId !== $assignment->work_center_id;
                    $prevWorkCenterId = $assignment->work_center_id;
                @endphp
                <tr class="{{ $isGroupStart ? 'machine-group-start' : '' }}">
                    <td class="machine-name">{{ $assignment->workCenter->name }}</td>
                    <td>WO-{{ $assignment->woOperation->workOrder->id }}</td>
                    <td>{{ $assignment->woOperation->sequence }}</td>
                    <td>{{ $assignment->start_at->format('d M H:i') }}</td>
                    <td>{{ $assignment->end_at->format('d M H:i') }}</td>
                    <td>{{ $assignment->start_at->diffInMinutes($assignment->end_at) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>