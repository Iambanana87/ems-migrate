<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ActionsBoardRequest;
use App\Http\Requests\Report\DevicesActionTableRequest;
use App\Http\Requests\Report\EfficiencyReportRequest;
use App\Http\Requests\Report\HourlyReportRequest;
use App\Http\Requests\Report\OutputReportBulkRequest;
use App\Http\Requests\Report\OutputReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function summary(SummaryReportRequest $request): JsonResponse
    {
        $data = $this->reportService->getSummaryReport(
            $request->input('from', ''),
            $request->input('to', ''),
            $request->boolean('debug')
        );

        return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
    }

    public function efficiency(EfficiencyReportRequest $request): JsonResponse
    {
        $data = $this->reportService->getEfficiencyReport(
            $request->input('process'),
            $request->input('from'),
            $request->input('to'),
            $request->input('sort_by'),
            $request->input('sort_order')
        );

        return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
    }

    public function hourly(HourlyReportRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->getHourlyReport(
                $request->input('device_id'),
                $request->input('report_date')
            );
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function output(OutputReportRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->getOutputReport(
                $request->input('process', 'mold'),
                $request->input('family', ''),
                $request->input('from', ''),
                $request->input('to', '')
            );
            return response()->json($data, 200, [], \JSON_NUMERIC_CHECK | \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($e instanceof \Illuminate\Database\QueryException && $e->getPrevious()) {
                $msg = $e->getPrevious()->getMessage();
            }
            return response()->json([
                'status'  => 'error',
                'message' => $msg
            ], 500, [], \JSON_NUMERIC_CHECK | \JSON_UNESCAPED_UNICODE);
        }
    }

    public function outputBulk(OutputReportBulkRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->getOutputReportBulk(
                $request->input('from', ''),
                $request->input('to', ''),
                $request->input('process', 'all'),
                $request->input('families', '')
            );
            return response()->json($data, 200, [], \JSON_NUMERIC_CHECK | \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($e instanceof \Illuminate\Database\QueryException && $e->getPrevious()) {
                $msg = $e->getPrevious()->getMessage();
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'get_output_report_bulk failed: ' . $msg
            ], 500, [], \JSON_NUMERIC_CHECK | \JSON_UNESCAPED_UNICODE);
        }
    }

    public function actionsBoard(ActionsBoardRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->getActionsBoard(
                $request->input('tab', 'active'),
                $request->input('process', 'all')
            );
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function devicesActionTable(DevicesActionTableRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->getDevicesActionTable(
                $request->input('display_type', 'all')
            );
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function previewNextCodes(\App\Http\Requests\DeviceAction\PreviewNextCodesRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->previewNextCodes();
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function listBackendIssues(\App\Http\Requests\Report\ListBackendIssuesRequest $request): JsonResponse
    {
        try {
            $data = $this->reportService->listBackendIssues($request->all());
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function countActions(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $count = $this->reportService->countActions($request->query());
            return response()->json(['count' => $count], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function countDeviceStatus(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $data = $this->reportService->countDeviceStatus($request->query());
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function countFlexible(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $count = $this->reportService->countFlexible($request->query());
            return response()->json(['count' => $count], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    public function getTotalCount(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $result = $this->reportService->getTotalCount($request->query());
            
            // Handle 404 from not_found error in service
            if (isset($result['error']) && $result['error'] === 'not_found') {
                return response()->json(['error' => 'not_found'], 404, [], \JSON_UNESCAPED_UNICODE);
            }

            return response()->json($result, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * tc_meta: returns the latest update timestamp and server time.
     * STRICT PARITY with api.php:3351-3369.
     */
    public function getTcMeta(): JsonResponse
    {
        try {
            $data = $this->reportService->getTcMeta();
            return response()->json($data, 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }
}
