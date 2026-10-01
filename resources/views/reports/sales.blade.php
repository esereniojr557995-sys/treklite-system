@extends('layouts.app')

@section('title', 'Sales Report')

@section('content')
    <style>
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-backdrop { display: none !important; }
            .main-wrapper { margin-left: 0 !important; }
            body { background: #fff !important; }
            .chart-card { break-inside: avoid; }
        }
        .chart-box { position: relative; height: 280px; }
    </style>

    @php
        // ---------- chart data (built from the data the controller already passes) ----------
        $singleDay = $from->isSameDay($to);
        $timeLabels = [];
        $timeRevenue = [];
        $timeCounts = [];

        if ($singleDay) {
            // Group by hour of the day
            $byHour = $sales->groupBy(fn ($s) => (int) $s->sold_at->format('G'));
            $hours = $byHour->keys()->all();
            $minH = count($hours) ? min(8, min($hours)) : 8;
            $maxH = count($hours) ? max(20, max($hours)) : 20;
            foreach (range($minH, $maxH) as $h) {
                $group = $byHour->get($h, collect());
                $timeLabels[]  = \Carbon\Carbon::createFromTime($h)->format('g A');
                $timeRevenue[] = round((float) $group->sum('total_amount'), 2);
                $timeCounts[]  = $group->count();
            }
        } else {
            // Group by day, including days with no sales
            $byDay = $sales->groupBy(fn ($s) => $s->sold_at->format('Y-m-d'));
            $period = \Carbon\CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
            foreach ($period as $day) {
                $group = $byDay->get($day->format('Y-m-d'), collect());
                $timeLabels[]  = $day->format('M d');
                $timeRevenue[] = round((float) $group->sum('total_amount'), 2);
                $timeCounts[]  = $group->count();
            }
        }

        $payLabels = [];
        $payTotals = [];
        foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label) {
            $t = (float) ($byPayment[$key]['total'] ?? 0);
            if ($t > 0) {
                $payLabels[] = $label;
                $payTotals[] = round($t, 2);
            }
        }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Sales Report</h3>
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer"></i> Print</button>
    </div>

    <form method="GET" class="row g-2 mb-4 no-print">
        <div class="col-auto"><input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}"></div>
        <div class="col-auto"><input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}"></div>
        <div class="col-auto"><button class="btn btn-success">Filter</button></div>
    </form>

    <div class="small text-muted mb-2">{{ $from->format('M d, Y') }} &ndash; {{ $to->format('M d, Y') }}</div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Total revenue</div>
                <div class="fs-4 fw-bold">₱{{ number_format($totalRevenue, 2) }}</div>
                <div class="text-muted small">{{ $sales->count() }} transaction(s)</div>
            </div></div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">By payment method</div>
                @foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label)
                    <div class="d-flex justify-content-between small">
                        <span>{{ $label }} <span class="text-muted">({{ $byPayment[$key]['count'] ?? 0 }})</span></span>
                        <span class="fw-semibold">₱{{ number_format($byPayment[$key]['total'] ?? 0, 2) }}</span>
                    </div>
                @endforeach
            </div></div>
        </div>
    </div>

    {{-- ============ Charts ============ --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100 chart-card">
                <div class="card-header fw-bold">
                    <i class="bi bi-bar-chart-line"></i>
                    Revenue {{ $singleDay ? 'by hour' : 'by day' }}
                </div>
                <div class="card-body">
                    @if ($sales->isEmpty())
                        <div class="text-center text-muted py-5">No sales in this range.</div>
                    @else
                        <div class="chart-box"><canvas id="timeChart"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 chart-card">
                <div class="card-header fw-bold"><i class="bi bi-pie-chart"></i> Sales by payment method</div>
                <div class="card-body">
                    @if (empty($payTotals))
                        <div class="text-center text-muted py-5">No sales in this range.</div>
                    @else
                        <div class="chart-box"><canvas id="payChart"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Processed By</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $sale->customer_name }}</td>
                        <td>{{ $sale->user->name }}</td>
                        <td>{{ $sale->payment_label }}</td>
                        <td class="text-end">₱{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No sales in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const timeLabels  = @json($timeLabels);
            const timeRevenue = @json($timeRevenue);
            const timeCounts  = @json($timeCounts);
            const payLabels   = @json($payLabels);
            const payTotals   = @json($payTotals);

            const timeEl = document.getElementById('timeChart');
            if (timeEl) {
                new Chart(timeEl, {
                    type: 'bar',
                    data: {
                        labels: timeLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: timeRevenue,
                            backgroundColor: '#1e5b31',
                            borderRadius: 4,
                            maxBarThickness: 48,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => peso(ctx.parsed.y),
                                    afterLabel: ctx => timeCounts[ctx.dataIndex] + ' transaction(s)',
                                },
                            },
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } },
                            x: { grid: { display: false } },
                        },
                    },
                });
            }

            const payEl = document.getElementById('payChart');
            if (payEl) {
                new Chart(payEl, {
                    type: 'doughnut',
                    data: {
                        labels: payLabels,
                        datasets: [{
                            data: payTotals,
                            backgroundColor: ['#1e5b31', '#0d6efd', '#6f42c1', '#fd7e14', '#20c997'],
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + peso(ctx.parsed) } },
                        },
                    },
                });
            }
        });
    </script>
@endsection