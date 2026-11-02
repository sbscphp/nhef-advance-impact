<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $project['title'] }} - Project Summary Report</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            margin: 0;
            padding: 18px;
        }
        .header-table,
        .meta-table,
        .section-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-logo {
            height: 26px;
        }
        .brand {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }
        .brand-sub {
            color: #6b7280;
            font-size: 9px;
            margin-top: 2px;
        }
        .title {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }
        .title-sub {
            text-align: right;
            color: #6b7280;
            font-size: 9px;
            margin-top: 2px;
        }
        .separator {
            border-top: 2px solid #111827;
            margin: 12px 0 10px;
        }
        .meta-wrap {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #f9fafb;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
            width: 50%;
        }
        .meta-key {
            width: 90px;
            color: #6b7280;
            font-weight: bold;
        }
        .meta-value {
            color: #111827;
        }
        .section-block {
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin: 16px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #d1d5db;
        }
        .section-table {
            margin-bottom: 4px;
        }
        .section-table th,
        .section-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }
        .section-table th {
            background: #111827;
            color: #ffffff;
            font-weight: bold;
            font-size: 8px;
        }
        .section-table tbody tr:nth-child(even) td {
            background: #f9fafb;
        }
        .section-table tbody tr:nth-child(odd) td {
            background: #ffffff;
        }
        .empty {
            color: #6b7280;
            font-style: italic;
            font-size: 8.5px;
            margin: 0;
            padding: 4px 0;
        }
        .footer {
            margin-top: 16px;
            font-size: 8px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="{{ config('app.name') }}" class="header-logo">
                @else
                    <div class="brand">{{ config('app.name') }}</div>
                    <div class="brand-sub">Project Summary Report</div>
                @endif
            </td>
            <td style="text-align: right;">
                <div class="title">{{ $project['title'] }}</div>
                <div class="title-sub">Generated {{ $generatedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') }} ({{ config('app.timezone') }})</div>
            </td>
        </tr>
    </table>

    <div class="separator"></div>

    <div class="meta-wrap">
        <table class="meta-table">
            <tr>
                <td class="meta-key">Period covered</td>
                <td class="meta-value">{{ $periodLabel }}</td>
                <td class="meta-key">Status</td>
                <td class="meta-value">{{ $project['status_label'] }}</td>
            </tr>
            <tr>
                <td class="meta-key">Category</td>
                <td class="meta-value">{{ $project['category_label'] ?? '—' }}</td>
                <td class="meta-key">Location</td>
                <td class="meta-value">{{ $project['location'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="meta-key">Start date</td>
                <td class="meta-value">{{ $project['starts_at'] ?? '—' }}</td>
                <td class="meta-key">End date</td>
                <td class="meta-value">{{ $project['ends_at'] ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Budget Overview</div>
        <table class="section-table">
            <thead>
                <tr>
                    <th>Approved Budget</th>
                    <th>Funding Received</th>
                    <th>Total Utilized</th>
                    <th>Remaining Budget</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $budgetOverview['approved_budget_formatted'] }}</td>
                    <td>{{ $budgetOverview['funding_received_formatted'] }}</td>
                    <td>{{ $budgetOverview['total_utilized_formatted'] }}</td>
                    <td>{{ $budgetOverview['remaining_budget_formatted'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section-block">
        <div class="section-title">Team</div>
        @if(count($team) === 0)
            <p class="empty">No team members assigned.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Name</th><th>Role</th><th>Manager</th></tr>
                </thead>
                <tbody>
                    @foreach($team as $member)
                        <tr>
                            <td>{{ $member['name'] }}</td>
                            <td>{{ $member['role_title'] ?? '—' }}</td>
                            <td>{{ $member['is_manager'] ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Objectives</div>
        @if(count($objectives) === 0)
            <p class="empty">No objectives recorded.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Description</th><th>Completed</th></tr>
                </thead>
                <tbody>
                    @foreach($objectives as $objective)
                        <tr>
                            <td>{{ $objective['title'] }}</td>
                            <td>{{ $objective['description'] }}</td>
                            <td>{{ $objective['is_completed'] ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Milestones due in period</div>
        @if(count($milestones) === 0)
            <p class="empty">No milestones due within the selected period.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Due Date</th><th>Completion</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach($milestones as $milestone)
                        <tr>
                            <td>{{ $milestone['title'] }}</td>
                            <td>{{ $milestone['due_at'] }}</td>
                            <td>{{ $milestone['completion_percentage'] }}%</td>
                            <td>{{ $milestone['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Deliverables due in period</div>
        @if(count($deliverables) === 0)
            <p class="empty">No deliverables due within the selected period.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Milestone</th><th>Due Date</th><th>Completed</th></tr>
                </thead>
                <tbody>
                    @foreach($deliverables as $deliverable)
                        <tr>
                            <td>{{ $deliverable['title'] }}</td>
                            <td>{{ $deliverable['milestone_title'] ?? '—' }}</td>
                            <td>{{ $deliverable['due_at'] }}</td>
                            <td>{{ $deliverable['is_completed'] ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Budget Lines</div>
        @if(count($budgetLines) === 0)
            <p class="empty">No budget lines recorded.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Reference ID</th><th>Amount Allocated</th></tr>
                </thead>
                <tbody>
                    @foreach($budgetLines as $line)
                        <tr>
                            <td>{{ $line['title'] }}</td>
                            <td>{{ $line['reference_id'] }}</td>
                            <td>{{ $line['amount_allocated_formatted'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Expenditures in period</div>
        @if(count($expenditures) === 0)
            <p class="empty">No expenditures recorded within the selected period.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Budget Line</th><th>Amount</th><th>Transaction Date</th></tr>
                </thead>
                <tbody>
                    @foreach($expenditures as $expenditure)
                        <tr>
                            <td>{{ $expenditure['title'] }}</td>
                            <td>{{ $expenditure['budget_line_title'] ?? '—' }}</td>
                            <td>{{ $expenditure['amount_formatted'] }}</td>
                            <td>{{ $expenditure['transaction_date'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section-block">
        <div class="section-title">Risks raised in period</div>
        @if(count($risks) === 0)
            <p class="empty">No risks raised within the selected period.</p>
        @else
            <table class="section-table">
                <thead>
                    <tr><th>Title</th><th>Severity</th><th>Status</th><th>Raised By</th></tr>
                </thead>
                <tbody>
                    @foreach($risks as $risk)
                        <tr>
                            <td>{{ $risk['title'] }}</td>
                            <td>{{ $risk['severity_label'] }}</td>
                            <td>{{ $risk['status_label'] }}</td>
                            <td>{{ $risk['raised_by'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="footer">{{ config('app.name') }} project summary report</p>
</body>
</html>
