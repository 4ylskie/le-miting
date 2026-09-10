@include('admin.reports.pdfs.report-head')

<h2>{{ ucfirst($period) }} Reservation Report</h2>
<p class="muted">
    Reporting period: {{ $periodLabel }} · {{ $from->format('d M Y') }} to {{ $generatedAt->format('d M Y') }}
    · Generated {{ $generatedAt->format('d M Y, g:i A') }}
</p>

<div class="summary">
    <div><strong>Total reservations:</strong> {{ number_format($data['total_reservations']) }}</div>
    <div><strong>Completed reservations:</strong> {{ number_format($data['completed_reservations']) }}</div>
    <div><strong>Cancelled reservations:</strong> {{ number_format($data['cancelled_reservations']) }}</div>
    <div><strong>Total guests:</strong> {{ number_format($data['guest_count']) }}</div>
</div>

<h3>Most Popular Reservation Times</h3>
@if ($data['popular_times']->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Time slot</th>
                <th style="text-align:right;">Reservations</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['popular_times'] as $slot)
                <tr>
                    <td>{{ \Carbon\Carbon::createFromFormat('H:i', $slot->time_slot)->format('g:i A') }}</td>
                    <td style="text-align:right;">{{ number_format($slot->reservation_count) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No reservations were recorded in this period.</p>
@endif

<div class="footer">Confidential — for Le Miting management use only.</div>
</body>
</html>