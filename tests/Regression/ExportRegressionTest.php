<?php

namespace Tests\Regression;

use App\Support\ExportCell;
use App\Filament\Widgets\Timesheet\MonthlyReport;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExportRegressionTest extends IsolatedTestCase
{
    public function test_csv_text_does_not_execute_as_a_formula(): void
    {
        foreach (['=HYPERLINK("example")', '+1', '-1', '@SUM(A1)', "\t=1"] as $value) {
            $this->assertSame("'" . $value, ExportCell::text($value));
        }
        $this->assertSame('Equipo 123', ExportCell::text('Equipo 123'));
    }

    public function test_monthly_filter_rejects_sql_before_querying(): void
    {
        $user = $this->actingAs(7);
        $widget = new class extends MonthlyReport {
            public function queryFor($user, $year) { return $this->filter($user, ['year' => $year]); }
        };
        $this->expectException(HttpException::class);
        $widget->queryFor($user, '2026 OR 1=1');
    }
}
