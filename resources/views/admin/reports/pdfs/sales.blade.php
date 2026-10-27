@include('admin.reports.pdfs.report-head')
@inject('businessTime', 'App\Services\BusinessTime')

<h2>{{ ucfirst($period) }} Sales Report</h2>
<p class="muted">
    Reporting period: {{ $periodLabel }} · {{ $businessTime->local($from)->format('d M Y, H:i:s') }} MYT (inclusive)
    to {{ $businessTime->local($generatedAt)->format('d M Y, H:i:s') }} MYT (exclusive)
    · Generated {{ $businessTime->local($generatedAt)->format('d M Y, H:i:s') }} MYT
</p>

<div class="summary">
    <div><strong>Total orders:</strong> {{ number_format($data['total_orders']) }}</div>
    <div><strong>Completed orders:</strong> {{ number_format($data['completed_orders']) }}</div>
    <div><strong>Cancelled orders:</strong> {{ number_format($data['cancelled_orders']) }}</div>
    <div><strong>Total revenue (paid):</strong> RM {{ number_format($data['total_revenue'], 2) }}</div>
</div>

<h3>Most Popular Menu Items</h3>
@if ($data['popular_items']->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Quantity sold</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['popular_items'] as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td class="text-right">{{ number_format($item->total_quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No qualifying sold items were recorded in this period.</p>
@endif

<div class="footer">Confidential — for Le Miting management use only.</div>
</body>
</html>
