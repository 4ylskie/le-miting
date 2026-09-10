@include('admin.reports.pdfs.report-head')

<h2>{{ ucfirst($period) }} Feedback Report</h2>
<p class="muted">
    Reporting period: {{ $periodLabel }} · {{ $from->format('d M Y') }} to {{ $generatedAt->format('d M Y') }}
    · Generated {{ $generatedAt->format('d M Y, g:i A') }}
</p>

<div class="summary">
    <div><strong>Reviews received:</strong> {{ number_format($data['total_reviews']) }}</div>
    <div><strong>Average food rating:</strong> {{ number_format($data['average_food'], 2) }} / 5</div>
    <div><strong>Average service rating:</strong> {{ number_format($data['average_service'], 2) }} / 5</div>
    <div><strong>Average overall experience:</strong> {{ number_format($data['average_experience'], 2) }} / 5</div>
</div>

<h3>Rating Distribution</h3>
@if ($data['total_reviews'] > 0)
    <table>
        <thead>
            <tr>
                <th>Rating</th>
                <th style="text-align:right;">Food</th>
                <th style="text-align:right;">Service</th>
                <th style="text-align:right;">Experience</th>
            </tr>
        </thead>
        <tbody>
            @foreach (range(5, 1) as $star)
                <tr>
                    <td>{{ $star }} star{{ $star === 1 ? '' : 's' }}</td>
                    <td style="text-align:right;">{{ $data['distributions']['food_rating'][$star] }}</td>
                    <td style="text-align:right;">{{ $data['distributions']['service_rating'][$star] }}</td>
                    <td style="text-align:right;">{{ $data['distributions']['experience_rating'][$star] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="muted">No feedback was received in this period.</p>
@endif

<div class="footer">Confidential — for Le Miting management use only.</div>
</body>
</html>