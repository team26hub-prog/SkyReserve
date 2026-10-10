<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\AdminReport;
use DomainException;

final class AdminReportController extends Controller
{
    public function __construct() { $this->requireRole('admin'); }
    public function index(): void
    {
        $analytics = null; $error = null;
        try { $analytics = (new AdminReport())->analytics($_GET); }
        catch (DomainException $exception) { http_response_code(422); $error = $exception->getMessage(); }
        $this->render('admin/reports/index',['title' => 'Reports','wide' => true,'analytics' => $analytics,'analyticsError' => $error]);
    }
    public function bookings(): void { $this->show('bookings'); }
    public function flights(): void { $this->show('flights'); }
    public function payments(): void { $this->show('payments'); }
    public function cancellations(): void { $this->show('cancellations'); }
    public function passengers(): void { $this->show('passengers'); }
    public function revenue(): void { $this->show('revenue'); }
    private function show(string $type): void
    {
        $model = new AdminReport(); $error = null;
        try { $result = $model->report($type,$_GET); }
        catch (DomainException $exception) {
            http_response_code(422); $error = $exception->getMessage();
            // Invalid filters show no data rather than silently falling back to an unfiltered report.
            $filters = array_fill_keys(['start_date','end_date','flight_id',...AdminReport::REPORTS[$type]['statuses']],'');
            foreach ($filters as $key => $value) if (isset($_GET[$key]) && is_string($_GET[$key])) $filters[$key] = mb_substr($_GET[$key],0,100);
            $result = ['rows' => [],'total' => 0,'totals' => [],'page' => 1,'pages' => 1,'filters' => $filters];
        }
        $this->render('admin/reports/report',['title' => AdminReport::REPORTS[$type]['title'],'wide' => true,'type' => $type,'result' => $result,'error' => $error,'flights' => $model->flightOptions()]);
    }
}
