<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getSummaryReport(string $from_raw, string $to_raw, bool $debug): array
    {
        /* =======================
         * 1) Parse time (Asia/Ho_Chi_Minh) + build business windows
         * ======================= */
        $tzVN  = new DateTimeZone('Asia/Ho_Chi_Minh');

        $fromVN = $from_raw ? new DateTime(str_replace('T', ' ', $from_raw), $tzVN) : null;
        $toVN   = $to_raw   ? new DateTime(str_replace('T', ' ', $to_raw),   $tzVN) : null;

        // Fallback: [07:00 hôm qua -> 07:00 hôm nay]
        if (!$fromVN || !$toVN) {
            $toVN   = new DateTime('today 07:00:00', $tzVN);
            $fromVN = (clone $toVN)->modify('-1 day');
        }
        // guard
        if ($fromVN >= $toVN) {
            $toVN = (clone $fromVN)->modify('+1 hour');
        }

        // Khung “day” = 07:00 -> 19:00 của ngày bắt đầu; “night” = 19:00 -> 07:00 kế tiếp.
        $day_from_dt   = clone $fromVN;
        $day_to_dt     = (clone $fromVN)->setTime(19, 0, 0);
        $night_from_dt = clone $day_to_dt;
        $night_to_dt   = clone $toVN;

        $day_from   = $day_from_dt->format('Y-m-d H:i:s');
        $day_to     = $day_to_dt->format('Y-m-d H:i:s');
        $night_from = $night_from_dt->format('Y-m-d H:i:s');
        $night_to   = $night_to_dt->format('Y-m-d H:i:s');

        $sec_day   = max(1, strtotime($day_to)   - strtotime($day_from));
        $sec_night = max(1, strtotime($night_to) - strtotime($night_from));
        $sec_total = max(1, strtotime($toVN->format('Y-m-d H:i:s')) - strtotime($fromVN->format('Y-m-d H:i:s')));

        $hrs_day   = $sec_day   / 3600.0;
        $hrs_night = $sec_night / 3600.0;
        $hrs_total = $sec_total / 3600.0;

        /* =======================
         * 2) + 3) Lấy output/day-night và capacity/h theo process (2 query)
         * ======================= */

        $sqlOutputs = "
            SELECT proc,
                   SUM(day_out)   AS day_out,
                   SUM(night_out) AS night_out
            FROM (
                /* MOLDING */
                SELECT 'mold' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from_m AND t.`datetime`<:day_to_m
                                THEN COALESCE(t.cavities,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from_m AND t.`datetime`<:night_to_m
                                THEN COALESCE(t.cavities,0) ELSE 0 END) AS night_out
                FROM mold t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='mold'
                  AND d.process IN ('Single','1st')
                  AND t.cycle_time > 20
                  AND t.`datetime` >= :from_all_m AND t.`datetime` < :to_all_m

                UNION ALL

                /* TUFTING */
                SELECT 'tuft' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from_t AND t.`datetime`<:day_to_t
                                THEN COALESCE(t.output,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from_t AND t.`datetime`<:night_to_t
                                THEN COALESCE(t.output,0) ELSE 0 END) AS night_out
                FROM tuft t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='tuft'
                  AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')
                  AND t.`datetime` >= :from_all_t AND t.`datetime` < :to_all_t

                UNION ALL

                /* BLISTER */
                SELECT 'blister' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from_b AND t.`datetime`<:day_to_b
                                THEN COALESCE(t.output,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from_b AND t.`datetime`<:night_to_b
                                THEN COALESCE(t.output,0) ELSE 0 END) AS night_out
                FROM blister t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='blister'
                  AND t.`datetime` >= :from_all_b AND t.`datetime` < :to_all_b
            ) X
            GROUP BY proc
        ";

        $bindingsOut = [
            'day_from_m'   => $day_from,
            'day_to_m'     => $day_to,
            'night_from_m' => $night_from,
            'night_to_m'   => $night_to,
            'from_all_m'   => $day_from,
            'to_all_m'     => $night_to,

            'day_from_t'   => $day_from,
            'day_to_t'     => $day_to,
            'night_from_t' => $night_from,
            'night_to_t'   => $night_to,
            'from_all_t'   => $day_from,
            'to_all_t'     => $night_to,

            'day_from_b'   => $day_from,
            'day_to_b'     => $day_to,
            'night_from_b' => $night_from,
            'night_to_b'   => $night_to,
            'from_all_b'   => $day_from,
            'to_all_b'     => $night_to,
        ];

        $outM = ['day_out' => 0.0, 'night_out' => 0.0];
        $outT = ['day_out' => 0.0, 'night_out' => 0.0];
        $outB = ['day_out' => 0.0, 'night_out' => 0.0];

        foreach (DB::select($sqlOutputs, $bindingsOut) as $row) {
            $r = (array) $row;
            $proc = $r['proc'];
            $pair = ['day_out' => (float)$r['day_out'], 'night_out' => (float)$r['night_out']];
            if ($proc === 'mold')        $outM = $pair;
            elseif ($proc === 'tuft')    $outT = $pair;
            elseif ($proc === 'blister') $outB = $pair;
        }

        $sqlCaps = "
            SELECT proc, SUM(capacity) AS cap_per_hour
            FROM (
                SELECT 'mold' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='mold' AND d.process IN ('Single','1st')

                UNION ALL
                SELECT 'tuft' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='tuft'
                  AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')

                UNION ALL
                SELECT 'blister' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='blister'
            ) C
            GROUP BY proc
        ";

        $caps = [];
        foreach (DB::select($sqlCaps) as $row) {
            $r = (array) $row;
            $caps[$r['proc']] = $r['cap_per_hour'];
        }

        $capM = (float)($caps['mold']    ?? 0.0);
        $capT = (float)($caps['tuft']    ?? 0.0);
        $capB = (float)($caps['blister'] ?? 0.0);

        /* =======================
         * 4) Efficiency = ratio-of-sums
         * ======================= */
        $eff = function(float $sumOutput, float $sumCapPerHour, float $hours): float {
            return ($sumCapPerHour > 0 && $hours > 0)
                ? round(($sumOutput / ($sumCapPerHour * $hours)) * 100.0, 2)
                : 0.0;
        };

        $effM_day   = $eff($outM['day_out'],                      $capM, $hrs_day);
        $effM_night = $eff($outM['night_out'],                    $capM, $hrs_night);
        $effM_avg   = $eff($outM['day_out'] + $outM['night_out'], $capM, $hrs_total);

        $effT_day   = $eff($outT['day_out'],                      $capT, $hrs_day);
        $effT_night = $eff($outT['night_out'],                    $capT, $hrs_night);
        $effT_avg   = $eff($outT['day_out'] + $outT['night_out'], $capT, $hrs_total);

        $effB_day   = $eff($outB['day_out'],                      $capB, $hrs_day);
        $effB_night = $eff($outB['night_out'],                    $capB, $hrs_night);
        $effB_avg   = $eff($outB['day_out'] + $outB['night_out'], $capB, $hrs_total);

        /* =======================
         * 5) Lost
         * ======================= */
        $makeOutputNode = function(array $self, ?array $base): array {
            $day   = (int)round($self['day_out']   ?? 0);
            $night = (int)round($self['night_out'] ?? 0);
            $total = $day + $night;

            if (!$base) {
                return [
                    'day' => $day, 'night' => $night, 'total' => $total,
                    'day_lost_pcs' => 0, 'day_loss_percent' => 0.0,
                    'night_lost_pcs' => 0, 'night_loss_percent' => 0.0,
                    'total_lost_pcs' => 0, 'total_loss_percent' => 0.0
                ];
            }

            $base_day   = (int)round($base['day_out']   ?? 0);
            $base_night = (int)round($base['night_out'] ?? 0);
            $lost_day   = max(0, $base_day   - $day);
            $lost_night = max(0, $base_night - $night);
            $lost_total = $lost_day + $lost_night;

            $pct_day    = $base_day   > 0 ? round($lost_day   / $base_day   * 100, 2) : 0.0;
            $pct_night  = $base_night > 0 ? round($lost_night / $base_night * 100, 2) : 0.0;
            $base_total = $base_day + $base_night;
            $pct_total  = $base_total > 0 ? round($lost_total / $base_total * 100, 2) : 0.0;

            return [
                'day' => $day, 'night' => $night, 'total' => $total,
                'day_lost_pcs'   => $lost_day,    'day_loss_percent'   => $pct_day,
                'night_lost_pcs' => $lost_night,  'night_loss_percent' => $pct_night,
                'total_lost_pcs' => $lost_total,  'total_loss_percent' => $pct_total,
            ];
        };

        $countsBy = [
            'mold'    => ['running' => 0, 'breakdown' => 0, 'warning' => 0, 'total' => 0],
            'tuft'    => ['running' => 0, 'breakdown' => 0, 'warning' => 0, 'total' => 0],
            'blister' => ['running' => 0, 'breakdown' => 0, 'warning' => 0, 'total' => 0],
        ];

        $sqlStatus = "
            SELECT
                d.display_type AS type,
                UPPER(COALESCE(ds.connection_status,'DISCONNECTED')) AS conn,
                UPPER(COALESCE(ds.threshold_status,'NORMAL'))        AS thres
            FROM devices d
            LEFT JOIN device_status ds ON ds.device_id = d.device_id
            WHERE
                (d.display_type='mold'    )
                OR
                (d.display_type='tuft'    )
                OR
                (d.display_type='blister')
        ";

        foreach (DB::select($sqlStatus) as $row) {
            $r = (array) $row;
            $t = $r['type']; 
            if (!isset($countsBy[$t])) continue;
            
            $countsBy[$t]['total']++;
            $conn  = current([$r['conn']]) ?: 'DISCONNECTED';
            $thres = current([$r['thres']]) ?: 'NORMAL';
            if ($thres === 'WARNING') $thres = 'BREACHED';

            if ($conn === 'DISCONNECTED') {
                $countsBy[$t]['breakdown']++;
            } else {
                $countsBy[$t]['running']++;
                if ($thres === 'BREACHED') $countsBy[$t]['warning']++;
            }
        }

        /* =======================
         * 6) Payload
         * ======================= */
        $payload = [
            'mold' => [
                'status'     => $countsBy['mold'],
                'efficiency' => ['day' => $effM_day, 'night' => $effM_night, 'average' => $effM_avg],
                'output'     => $makeOutputNode($outM, null),
            ],
            'tuft' => [
                'status'     => $countsBy['tuft'],
                'efficiency' => ['day' => $effT_day, 'night' => $effT_night, 'average' => $effT_avg],
                'output'     => $makeOutputNode($outT, $outM),
            ],
            'blister' => [
                'status'     => $countsBy['blister'],
                'efficiency' => ['day' => $effB_day, 'night' => $effB_night, 'average' => $effB_avg],
                'output'     => $makeOutputNode($outB, $outT),
            ],
            '_range' => [
                'day_from'   => $day_from,
                'day_to'     => $day_to,
                'night_from' => $night_from,
                'night_to'   => $night_to,
            ],
        ];

        if ($debug) {
            $payload['_debug'] = [
                'windows' => [
                    'day_from' => $day_from, 'day_to' => $day_to,
                    'night_from' => $night_from, 'night_to' => $night_to,
                    'hours' => ['day' => $hrs_day, 'night' => $hrs_night, 'total' => $hrs_total],
                ],
                'sum_output' => ['mold' => $outM, 'tuft' => $outT, 'blister' => $outB],
                'sum_capacity_per_hour' => ['mold' => $capM, 'tuft' => $capT, 'blister' => $capB],
            ];
        }

        return $payload;
    }

    public function getEfficiencyReport(
        ?string $process_type,
        ?string $from_datetime_str,
        ?string $to_datetime_str,
        ?string $sort_by,
        ?string $sort_order
    ): array {
        /* ========= 0) Input & defaults ========= */
        $process_type      = $process_type      ?: 'mold';
        $from_datetime_str = $from_datetime_str ?: date('Y-m-d H:i:s');
        $to_datetime_str   = $to_datetime_str   ?: date('Y-m-d H:i:s');
        $sort_by           = $sort_by           ?: 'machine_id';
        $sort_order        = $sort_order        ?: 'ASC';

        // Whitelist sort
        $allowed_sort_columns = ['machine_id','family','process','efficiency','current_cycle','total_lost_pcs','output','capacity'];
        if (!in_array($sort_by, $allowed_sort_columns, true)) $sort_by = 'machine_id';
        $sort_order = strtoupper($sort_order);
        if (!in_array($sort_order, ['ASC','DESC'], true)) $sort_order = 'ASC';

        // Tables + join key cho DETAILS (process đang chọn)
        $data_table = [
            'mold'    => 'mold',
            'tuft'    => 'tuft',
            'blister' => 'blister'
        ][$process_type] ?? 'mold';

        $joinOn = ($process_type === 'mold')
            ? "ON BINARY t.device_id  = BINARY d.device_id"
            : "ON BINARY t.device_id = BINARY d.device_id";

        // Process filters (đồng bộ với get_summary_report)
        $process_where_condition = "1=1";
        $extra_where_condition   = "1=1";
        if ($process_type === 'mold') {
            $process_where_condition = "d.process IN ('Single','1st')";
            $extra_where_condition   = "t.cycle_time > 20";
        } elseif ($process_type === 'tuft') {
            $process_where_condition = "(BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')";
        }

        /* ========= 1) Timezones, parse from/to ========= */
        $tzVN  = new DateTimeZone('Asia/Ho_Chi_Minh');
        $tzUTC = new DateTimeZone('UTC');

        $DB_IS_UTC = false;

        $fromLocal = DateTime::createFromFormat('Y-m-d H:i:s', $from_datetime_str, $tzVN)
                  ?: DateTime::createFromFormat('Y-m-d H:i',    $from_datetime_str, $tzVN);
        $toLocal   = DateTime::createFromFormat('Y-m-d H:i:s', $to_datetime_str,   $tzVN)
                  ?: DateTime::createFromFormat('Y-m-d H:i',    $to_datetime_str,   $tzVN);

        if (!$fromLocal || !$toLocal) {
            $fromLocal = new DateTime('now', $tzVN);
            $toLocal   = (clone $fromLocal)->modify('+1 hour');
        }
        if ($fromLocal >= $toLocal) {
            $toLocal = (clone $fromLocal)->modify('+1 hour');
        }

        $fromForQuery = $DB_IS_UTC ? (clone $fromLocal)->setTimezone($tzUTC)->format('Y-m-d H:i:s')
                                   : $fromLocal->format('Y-m-d H:i:s');
        $toForQuery   = $DB_IS_UTC ? (clone $toLocal)->setTimezone($tzUTC)->format('Y-m-d H:i:s')
                                   : $toLocal->format('Y-m-d H:i:s');

        $hours_total = max(0.0001, ($toLocal->getTimestamp() - $fromLocal->getTimestamp()) / 3600.0);

        /* ========= 2) DETAILS (per machine) – giữ schema hiện tại ========= */
        switch ($process_type) {
            case 'mold':
                $sum_output_sql = "SUM(CASE WHEN t.cycle_time > 20 THEN COALESCE(t.cavities,0) ELSE 0 END)";
                $select_fields = "
                    AVG(t.cavities)   AS actual_cavity,
                    {$sum_output_sql} AS output,
                    AVG(t.cycle_time) AS current_cycle,
                    GREATEST(0, ((AVG(d.capacity) / 3600 * TIMESTAMPDIFF(SECOND, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql})) AS total_lost_pcs,
                    GREATEST(0, (((AVG(d.capacity) / 3600 * TIMESTAMPDIFF(SECOND, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql}) / (AVG(d.capacity) / 60))) AS lost_time
                ";
                break;

            case 'tuft':
                $sum_output_sql = "SUM(COALESCE(t.output,0))";
                $select_fields = "
                    0                  AS actual_cavity,
                    {$sum_output_sql}  AS output,
                    AVG(t.output)      AS current_cycle,
                    GREATEST(0, ((AVG(d.capacity) / 60 * TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql})) AS total_lost_pcs,
                    GREATEST(0, (((AVG(d.capacity) / 60 * TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql}) / (AVG(d.capacity) / 60))) AS lost_time
                ";
                break;

            case 'blister':
                $sum_output_sql = "SUM(CAST(t.output AS DECIMAL(10,2)) * 60)";
                $select_fields = "
                    0                                     AS actual_cavity,
                    {$sum_output_sql}                     AS output,
                    AVG(CAST(t.output AS DECIMAL(10,2)))  AS current_cycle,
                    SUM(d.capacity - (CAST(t.output AS DECIMAL(10,2)) * 60)) AS total_lost_pcs,
                    SUM((d.capacity - (CAST(t.output AS DECIMAL(10,2)) * 60)) / (d.capacity / 60)) AS lost_time
                ";
                break;
        }

        $details_sql = "
            SELECT 
                d.device_id             AS machine_id,
                d.product               AS family,
                d.process               AS process,
                d.cavities              AS mold_cavity,
                d.capacity              AS capacity_per_hour,
                (d.capacity * :hoursTotal1) AS capacity,

                d.target_limit          AS target,
                d.upper_limit,
                d.lower_limit,
                {$select_fields},
                (CASE WHEN COALESCE(d.capacity,0) > 0
                    THEN ({$sum_output_sql}) / (d.capacity * :hoursTotal2) * 100
                    ELSE 0 END) AS efficiency
            FROM {$data_table} t
            JOIN devices d {$joinOn}
            WHERE d.display_type = :proc
              AND t.`datetime`   >= :fromQ
              AND t.`datetime`   <  :toQ
              AND {$process_where_condition}
              AND {$extra_where_condition}
            GROUP BY d.device_id, d.product, d.process, d.cavities, d.capacity, d.target_limit, d.upper_limit, d.lower_limit
            ORDER BY {$sort_by} {$sort_order}
        ";
        
        $bindings = [
            'proc'        => $process_type,
            'fromQ'       => $fromForQuery,
            'toQ'         => $toForQuery,
            'hoursTotal1' => $hours_total,
            'hoursTotal2' => $hours_total
        ];

        $details_data = array_map(function ($row) {
            return (array) $row;
        }, DB::select($details_sql, $bindings));

        /* ========= 3) SUMMARY WINDOWS (giống get_summary_report) =========
         * Day   = fromLocal(07:00) -> 19:00 cùng ngày-from
         * Night = 19:00 cùng ngày-from -> toLocal(07:00)
         * Avg   = fromLocal(07:00) -> toLocal(07:00)
         */
        $day_from_vn   = clone $fromLocal;
        $day_to_vn     = (clone $fromLocal)->setTime(19,0,0);
        $night_from_vn = clone $day_to_vn;
        $night_to_vn   = clone $toLocal;

        $hDay   = max(1, ($day_to_vn->getTimestamp()   - $day_from_vn->getTimestamp()))   / 3600.0;
        $hNight = max(1, ($night_to_vn->getTimestamp() - $night_from_vn->getTimestamp())) / 3600.0;
        $hAll   = max(1, ($toLocal->getTimestamp()     - $fromLocal->getTimestamp()))     / 3600.0;

        $toQuery = function(DateTime $dt) use ($DB_IS_UTC,$tzUTC) {
            $x = clone $dt;
            if ($DB_IS_UTC) $x->setTimezone($tzUTC);
            return $x->format('Y-m-d H:i:s');
        };
        $day_from_q   = $toQuery($day_from_vn);
        $day_to_q     = $toQuery($day_to_vn);
        $night_from_q = $toQuery($night_from_vn);
        $night_to_q   = $toQuery($night_to_vn);

        /* ========= 3a) SUMMARY cho 1 process (closure) =========
         * - Đồng bộ filter/process & công thức với get_summary_report:
         *   mold   => cavities (cycle_time > 20)
         *   tuft   => output (pcs)
         *   blister=> output (pcs)  // KHÔNG *60 trong SUMMARY
         */
        $computeSummaryFor = function(string $proc) use ($day_from_q, $day_to_q, $night_from_q, $night_to_q, $hDay, $hNight, $hAll) {
            // Table + Join
            if ($proc === 'mold') {
                $tbl = 'mold';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "CASE WHEN t.cycle_time > 20 THEN COALESCE(t.cavities,0) ELSE 0 END";
                $whereProc   = "d.display_type='mold' AND d.process IN ('Single','1st')";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) AS sum_cap FROM devices WHERE display_type='mold' AND process IN ('Single','1st')";
                $extraWhere  = "t.cycle_time > 20";
            } elseif ($proc === 'tuft') {
                $tbl = 'tuft';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "COALESCE(t.output,0)";
                $whereProc   = "d.display_type='tuft' AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) AS sum_cap FROM devices WHERE display_type='tuft' AND (BINARY process LIKE BINARY '%Single%' OR BINARY process LIKE BINARY '%1st%')";
                $extraWhere  = "1=1";
            } else { // blister
                $tbl = 'blister';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "COALESCE(t.output,0)"; // KHÔNG *60 để khớp get_summary_report
                $whereProc   = "d.display_type='blister'";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) AS sum_cap FROM devices WHERE display_type='blister'";
                $extraWhere  = "1=1";
            }

            // Helper sum output
            $sumOutInRange = function($fromQ, $toQ) use ($tbl, $joinOnLocal, $whereProc, $extraWhere, $sumOutExpr) {
                $sql = "
                    SELECT SUM({$sumOutExpr}) AS sum_output
                    FROM {$tbl} t
                    JOIN devices d {$joinOnLocal}
                    WHERE {$whereProc}
                      AND {$extraWhere}
                      AND t.`datetime` >= :fromQ
                      AND t.`datetime` <  :toQ
                ";
                $res = DB::select($sql, ['fromQ' => $fromQ, 'toQ' => $toQ]);
                return (float)($res[0]->sum_output ?? 0.0);
            };

            $capRes = DB::select($capSql);
            $sumCapPerHour = (float)($capRes[0]->sum_cap ?? 0.0);
            
            $sumDay        = $sumOutInRange($day_from_q,   $day_to_q);
            $sumNight      = $sumOutInRange($night_from_q, $night_to_q);

            $effDay   = ($sumCapPerHour > 0) ? round(($sumDay   / ($sumCapPerHour * $hDay  )) * 100, 2) : 0.0;
            $effNight = ($sumCapPerHour > 0) ? round(($sumNight / ($sumCapPerHour * $hNight)) * 100, 2) : 0.0;
            $effAvg   = ($sumCapPerHour > 0) ? round((($sumDay + $sumNight) / ($sumCapPerHour * $hAll)) * 100, 2) : 0.0;

            return [
                'day'     => $effDay,
                'night'   => $effNight,
                'average' => $effAvg,
            ];
        };

        // Tính summary cho cả 3 process
        $summary_all = [
            'mold'    => $computeSummaryFor('mold'),
            'tuft'    => $computeSummaryFor('tuft'),
            'blister' => $computeSummaryFor('blister'),
        ];

        /* ========= 4) Response ========= */
        return [
            // SUMMARY_ALL cho cả 3 process như bạn yêu cầu
            'summary_all'  => $summary_all,

            // DETAILS = theo from/to datepicker
            'details' => $details_data,

            // Debug info
            '_range_details' => [
                'from_local' => $fromLocal->format('Y-m-d H:i:s'),
                'to_local'   => $toLocal->format('Y-m-d H:i:s'),
                'hours'      => round($hours_total, 4),
                'db_is_utc'  => $DB_IS_UTC,
            ],
            '_range_summary_windows' => [
                'day_from_vn'   => $day_from_vn->format('Y-m-d H:i:s'),
                'day_to_vn'     => $day_to_vn->format('Y-m-d H:i:s'),
                'night_from_vn' => $night_from_vn->format('Y-m-d H:i:s'),
                'night_to_vn'   => $night_to_vn->format('Y-m-d H:i:s'),
                'h_day'         => $hDay,
                'h_night'       => $hNight,
                'h_total'       => $hAll,
            ],
        ];
    }

    private function sumOutputBlockLocal(string $process, string $fromLocal, string $toLocal, string $familyFilter = ''): float
    {
        $isMold = ($process === 'mold');
        $table  = ['mold'=>'mold','tuft'=>'tuft','blister'=>'blister'][$process] ?? 'mold';

        $joinOn = $isMold
            ? "BINARY t.mold_id  = BINARY d.device_id"
            : "BINARY t.device_id = BINARY d.device_id";

        $procFilter = $isMold
            ? "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)"
            : ($process === 'tuft'
                ? "AND (BINARY d.process LIKE '%Single%' OR BINARY d.process LIKE '%1st%')"
                : "");

        $outCol = $isMold ? "t.cavities" : "t.output";

        $sql = "
            SELECT SUM(COALESCE($outCol,0)) AS sum_output
            FROM $table t
            JOIN devices d ON $joinOn
            WHERE d.display_type = ?
              $procFilter
              AND t.`datetime` >= ? AND t.`datetime` < ?
        ";
        $params = [$process, $fromLocal, $toLocal];

        if ($familyFilter !== '') {
            $sql .= " AND d.product LIKE ?";
            $params[] = '%' . $familyFilter . '%';
        }

        $result = DB::selectOne($sql, $params);
        return (float)($result->sum_output ?? 0);
    }

    public function getOutputReport(string $processType, string $familyFilter, string $fromRaw, string $toRaw): array
    {
        $tzVN = new \DateTimeZone('Asia/Ho_Chi_Minh');

        $fromVN = null;
        if ($fromRaw) {
            try { $fromVN = new \DateTime(str_replace('T',' ', $fromRaw), $tzVN); } catch (\Throwable $e) {}
        }
        $toVN = null;
        if ($toRaw) {
            try { $toVN = new \DateTime(str_replace('T',' ', $toRaw), $tzVN); } catch (\Throwable $e) {}
        }

        if (!$fromVN || !$toVN) {
            $base = new \DateTime('today 07:00:00', $tzVN);
            $fromVN = $fromVN ?: (clone $base)->modify('-1 day');
            $toVN   = $toVN   ?: clone $base;
        }
        if ($fromVN >= $toVN) {
            $toVN = (clone $fromVN)->modify('+1 hour');
        }

        $segments = (function(\DateTime $fromVN, \DateTime $toVN) {
            $segments = [];
            $cursor = (clone $fromVN)->setTime(0,0,0);
            while ($cursor < $toVN) {
                $dayStart   = (clone $cursor)->setTime(7,0,0);
                $dayEnd     = (clone $cursor)->setTime(19,0,0);
                $nightStart = (clone $cursor)->setTime(19,0,0);
                $nightEnd   = (clone $cursor)->modify('+1 day')->setTime(7,0,0);

                $dFrom = max($dayStart,   $fromVN);
                $dTo   = min($dayEnd,     $toVN);
                $nFrom = max($nightStart, $fromVN);
                $nTo   = min($nightEnd,   $toVN);

                if ($dFrom < $dTo) $segments[] = ['type'=>'day',
                    'fromStr'=>$dFrom->format('Y-m-d H:i:s'), 'toStr'=>$dTo->format('Y-m-d H:i:s')];
                if ($nFrom < $nTo) $segments[] = ['type'=>'night',
                    'fromStr'=>$nFrom->format('Y-m-d H:i:s'), 'toStr'=>$nTo->format('Y-m-d H:i:s')];

                $cursor = (clone $cursor)->modify('+1 day')->setTime(0,0,0);
            }
            return $segments;
        })($fromVN, $toVN);

        $daySum   = ['mold'=>0.0,'tuft'=>0.0,'blister'=>0.0];
        $nightSum = ['mold'=>0.0,'tuft'=>0.0,'blister'=>0.0];

        foreach ($segments as $seg) {
            foreach (['mold','tuft','blister'] as $p) {
                $sum = $this->sumOutputBlockLocal($p, $seg['fromStr'], $seg['toStr'], $familyFilter);
                if ($seg['type'] === 'day')   $daySum[$p]   += $sum;
                else /* night */                 $nightSum[$p] += $sum;
            }
        }

        $day_out   = $daySum[$processType];
        $night_out = $nightSum[$processType];

        $prevOf = ['mold'=>null, 'tuft'=>'mold', 'blister'=>'tuft'];
        $prev   = $prevOf[$processType];

        if ($prev === null) {
            $day_lost = 0; $night_lost = 0;
            $day_loss_pct = 0; $night_loss_pct = 0; $total_loss_pct = 0;
        } else {
            $day_base   = max(0, (int)round($daySum[$prev]));
            $night_base = max(0, (int)round($nightSum[$prev]));
            $day_curr   = (int)round($day_out);
            $night_curr = (int)round($night_out);

            $day_lost   = max(0, $day_base   - $day_curr);
            $night_lost = max(0, $night_base - $night_curr);

            $day_loss_pct   = $day_base   > 0 ? round($day_lost   / $day_base   * 100, 2) : 0;
            $night_loss_pct = $night_base > 0 ? round($night_lost / $night_base * 100, 2) : 0;

            $total_base     = $day_base + $night_base;
            $total_loss_pct = $total_base > 0 ? round(($day_lost + $night_lost) / $total_base * 100, 2) : 0;
        }

        $summary_data = [
            'day_output'         => (int)round($day_out),
            'night_output'       => (int)round($night_out),
            'total_output'       => (int)round($day_out + $night_out),
            'day_lost'           => (int)$day_lost,
            'night_lost'         => (int)$night_lost,
            'total_lost'         => (int)($day_lost + $night_lost),
            'day_loss_percent'   => $day_loss_pct,
            'night_loss_percent' => $night_loss_pct,
            'total_loss_percent' => $total_loss_pct,
        ];

        $data_table = ['mold'=>'mold','tuft'=>'tuft','blister'=>'blister'][$processType] ?? 'mold';
        $joinOn     = ($processType === 'mold')
            ? "BINARY t.mold_id = BINARY d.device_id"
            : "BINARY t.device_id = BINARY d.device_id";

        $procFilterDetails = '';
        if ($processType === 'mold') {
            $procFilterDetails = "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)";
        } elseif ($processType === 'tuft') {
            $procFilterDetails = "AND (BINARY d.process LIKE '%Single%' OR BINARY d.process LIKE '%1st%')";
        }

        $details_where = "
            d.display_type = ?
            AND t.`datetime` >= ? AND t.`datetime` < ?
            $procFilterDetails
        ";
        $params = [
            $processType,
            $fromVN->format('Y-m-d H:i:s'),
            $toVN->format('Y-m-d H:i:s')
        ];

        if (!empty($familyFilter)) {
            $details_where .= " AND d.product LIKE ?";
            $params[] = '%' . $familyFilter . '%';
        }

        $output_col = ($processType === 'mold') ? 't.cavities' : 't.output';

        $details_sql = "
            SELECT
                X.machine_id,
                X.family,
                X.process,
                X.mold_cavity,
                X.capacity,
                X.target,
                X.upper_limit,
                X.lower_limit,

                X.output,
                X.subtotal AS SubTotal,

                ROUND(CASE WHEN X.cap_expected > 0
                    THEN (X.output / X.cap_expected) * 100 ELSE 0 END, 2) AS efficiency,

                GREATEST(0, X.cap_expected - X.output) AS total_lost_pcs,
                ROUND(CASE WHEN X.capacity > 0
                    THEN GREATEST(0, (X.cap_expected - X.output) / (X.capacity/60.0))
                    ELSE 0 END, 0) AS lost_time,

                X.current_cycle

            FROM (
                SELECT
                    d.device_id   AS machine_id,
                    d.product     AS family,
                    d.process,
                    d.cavities    AS mold_cavity,
                    COALESCE(d.capacity,0)      AS capacity,
                    d.target_limit AS target,
                    d.upper_limit, d.lower_limit,

                    SUM(COALESCE($output_col,0)) AS output,
                    SUM(COALESCE($output_col,0)) AS subtotal,

                    GREATEST(1, TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) AS device_minutes,
                    (COALESCE(d.capacity,0) * GREATEST(1, TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) / 60.0) AS cap_expected,

                    " . ($processType === 'mold'
                        ? "AVG(t.cycle_time)"
                        : "AVG($output_col)") . " AS current_cycle

                FROM {$data_table} t
                JOIN devices d ON {$joinOn}
                WHERE $details_where
                GROUP BY d.device_id, d.product, d.process, d.cavities, d.capacity, d.target_limit, d.upper_limit, d.lower_limit
            ) X
            ORDER BY X.family, X.machine_id
        ";
        
        $details_data = DB::select($details_sql, $params);

        // ---------- Subtotal theo family & family_counts ----------
        $family_subtotals = [];
        $arr_details = json_decode(json_encode($details_data), true);

        foreach ($arr_details as $item) {
            $fam = $item['family'] ?? 'Unknown';
            $family_subtotals[$fam] = ($family_subtotals[$fam] ?? 0) + (float)($item['output'] ?? 0);
        }
        
        usort($arr_details, fn($a,$b)=>($a['family'] ?? '') <=> ($b['family'] ?? ''));
        $final_details_data = [];
        $family_counts = [];
        $n = count($arr_details);

        for ($i=0;$i<$n;$i++) {
            $row = $arr_details[$i];
            $fam = $row['family'] ?? 'Unknown';
            $family_counts[$fam] = ($family_counts[$fam] ?? 0) + 1;
            $row['subtotal'] = null;
            if ($i+1 >= $n || $arr_details[$i+1]['family'] !== $fam) {
                $row['subtotal'] = $family_subtotals[$fam];
            }
            $final_details_data[] = $row;
        }

        $lastSubtotalByFamily = [];
        foreach ($final_details_data as $it) {
            if ($it['subtotal'] !== null && $it['subtotal'] !== '') {
                $lastSubtotalByFamily[$it['family']] = $it['subtotal'];
            }
        }
        $firstIndexByFamily = [];
        foreach ($final_details_data as $idx=>$it) {
            if (!isset($firstIndexByFamily[$it['family']])) $firstIndexByFamily[$it['family']] = $idx;
        }
        foreach ($firstIndexByFamily as $fam=>$idx) {
            if (isset($lastSubtotalByFamily[$fam])) {
                $final_details_data[$idx]['subtotal'] = $lastSubtotalByFamily[$fam];
            }
        }

        return [
            'summary'        => $summary_data,
            'details'        => $final_details_data,
            'family_counts'  => $family_counts
        ];
    }

    public function getOutputReportBulk(string $from_raw, string $to_raw, string $processParam = 'all', string $list_raw = ''): array
    {
        $tzVN = new \DateTimeZone('Asia/Ho_Chi_Minh');

        $fromVN   = null;
        $toVN     = null;
        if (!empty($from_raw)) {
            try { $fromVN = new \DateTime(str_replace('T',' ', $from_raw), $tzVN); } catch (\Throwable $e) {}
        }
        if (!empty($to_raw)) {
            try { $toVN = new \DateTime(str_replace('T',' ', $to_raw), $tzVN); } catch (\Throwable $e) {}
        }

        if (!$fromVN || !$toVN) {
            throw new \Exception('Invalid from/to', 400);
        }

        $PROC = [
            'mold'    => ['table' => 'mold',    'join_on' => 'BINARY t.mold_id = BINARY d.device_id',
                          'where_ext' => "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)",
                          'output_col' => 't.cavities'],
            'tuft'    => ['table' => 'tuft',    'join_on' => 'BINARY t.device_id = BINARY d.device_id',
                          'where_ext' => "AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')",
                          'output_col' => 't.output'],
            'blister' => ['table' => 'blister', 'join_on' => 'BINARY t.device_id = BINARY d.device_id',
                          'where_ext' => "",
                          'output_col' => 't.output'],
        ];

        $processParam = strtolower(trim($processParam));
        $allProcKeys  = array_keys($PROC);
        if ($processParam !== 'all' && !in_array($processParam, $allProcKeys, true)) {
            throw new \Exception('Invalid process', 400);
        }

        $PROC_SQL = ($processParam === 'all') ? $PROC : [$processParam => $PROC[$processParam]];
        $procKeysAll = array_keys($PROC);

        $hours = max(0, ($toVN->getTimestamp() - $fromVN->getTimestamp()) / 3600.0);

        $requestedProducts = [];
        if ($list_raw !== '') {
            foreach (explode(',', $list_raw) as $p) {
                $p = trim($p);
                if ($p !== '') $requestedProducts[] = $p;
            }
        }

        $parts  = [];
        $params = [
            'from' => $fromVN->format('Y-m-d H:i:s'),
            'to'   => $toVN->format('Y-m-d H:i:s'),
        ];

        $prodFilterSql = '';
        if (!empty($requestedProducts)) {
            $ph = [];
            foreach ($requestedProducts as $i => $p) { 
                $k = ":prod{$i}"; 
                $ph[] = $k; 
                $params["prod{$i}"] = $p; 
            }
            $prodFilterSql = ' AND TRIM(d.product) IN ('.implode(',', $ph).') ';
        }

        foreach ($PROC_SQL as $procName => $cfg) {
            $t   = $cfg['table'];
            $jo  = $cfg['join_on'];
            $oc  = $cfg['output_col'];
            $wxOn    = $cfg['where_on']    ?? '';
            $wxWhere = $cfg['where_where'] ?? ($cfg['where_ext'] ?? '');

            $parts[] = "
                SELECT
                    '{$procName}' AS process,
                    Y.product     AS product,
                    SUM(Y.output)   AS output_total,
                    SUM(Y.capacity) AS capacity_sum
                FROM (
                    SELECT
                        d.device_id,
                        TRIM(d.product) AS product,
                        COALESCE(d.capacity,0) AS capacity,
                        COALESCE(SUM(COALESCE({$oc},0)),0) AS output
                    FROM devices d
                    LEFT JOIN {$t} t
                      ON {$jo}
                     AND t.`datetime` >= :from AND t.`datetime` < :to
                     {$wxOn}
                    WHERE d.display_type = '{$procName}'
                      {$wxWhere}
                      {$prodFilterSql}
                    GROUP BY d.device_id, TRIM(d.product), d.capacity
                ) Y
                GROUP BY Y.product
            ";
        }

        $sql = implode("\nUNION ALL\n", $parts) . "\nORDER BY product, process";
        
        // Execute using Laravel DB facade
        $rawRows = \Illuminate\Support\Facades\DB::select($sql, $params);
        
        // Convert array of stdClass objects to associative arrays to match PDO::FETCH_ASSOC
        $rows = array_map(function ($row) {
            return (array) $row;
        }, $rawRows);

        $matrix = [];

        if (!empty($requestedProducts)) {
            foreach ($requestedProducts as $prodName) {
                $prodName = trim($prodName);
                if ($prodName === '') continue;
                if (!isset($matrix[$prodName])) $matrix[$prodName] = [];
                foreach ($procKeysAll as $p) {
                    if (!isset($matrix[$prodName][$p])) {
                        $matrix[$prodName][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
                    }
                }
            }
        }

        $totals = ['out'=>[], 'cap'=>[], 'eff_weighted'=>[], 'eff_avg'=>[]];
        $effSum = []; $effCnt = [];
        foreach ($procKeysAll as $p) {
            $totals['out'][$p] = 0; $totals['cap'][$p] = 0;
            $totals['eff_weighted'][$p] = 0; $totals['eff_avg'][$p] = 0;
            $effSum[$p] = 0; $effCnt[$p] = 0;
        }

        foreach ($rows as $r) {
            $prod = trim($r['product'] ?? 'Unknown');
            $proc = $r['process'];

            if (!isset($matrix[$prod])) $matrix[$prod] = [];
            foreach ($procKeysAll as $p) {
                if (!isset($matrix[$prod][$p])) {
                    $matrix[$prod][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
                }
            }

            $o = (float)($r['output_total'] ?? 0);
            $c = (float)($r['capacity_sum'] ?? 0);

            $matrix[$prod][$proc]['output'] = $o;
            $matrix[$prod][$proc]['cap']    = $c;

            $eff = ($c > 0 && $hours > 0) ? ($o / ($c * $hours)) * 100 : 0.0;
            $matrix[$prod][$proc]['efficiency'] = round($eff, 2);

            $totals['out'][$proc] += $o;
            $totals['cap'][$proc] += $c;
            if ($eff > 0) { $effSum[$proc] += $eff; $effCnt[$proc]++; }
        }

        foreach ($procKeysAll as $p) {
            $totals['eff_weighted'][$p] = ($totals['cap'][$p] > 0 && $hours > 0)
                ? round(($totals['out'][$p] / ($totals['cap'][$p] * $hours)) * 100, 2)
                : 0.0;
            $totals['eff_avg'][$p] = ($effCnt[$p] > 0)
                ? round($effSum[$p] / $effCnt[$p], 2)
                : 0.0;
        }

        if (empty($requestedProducts) && empty($rows)) {
            $prod = '(No data)';
            $matrix[$prod] = [];
            foreach ($procKeysAll as $p) {
                $matrix[$prod][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
            }
        }

        return [
            'status' => 'success',
            'hours'  => $hours,
            'matrix' => $matrix,
            'totals' => $totals,
        ];
    }

    public function getActionsBoard(string $tab = 'active', string $process = 'all'): array
    {
        $tab = ($tab === 'done') ? 'done' : 'active';
        $allowProc = ['all','mold','tuft','blister','injection','end-rounding'];
        if (!in_array($process, $allowProc, true)) $process = 'all';

        $sql = "
            SELECT 
              dv.display_type,
              dv.device_id,
              dv.product,
              dv.process,
              SUM(CASE WHEN ag.plans_total > 0 AND ag.plans_done = ag.plans_total THEN 1 ELSE 0 END)  AS done_count,
              SUM(CASE WHEN (ag.plans_total = 0 OR ag.plans_done < ag.plans_total) THEN 1 ELSE 0 END) AS open_count,
              MAX(ag.latest_due) AS latest_due
            FROM (
              SELECT 
                da.id,
                da.device_id,
                COALESCE(SUM(CASE WHEN dap.status='done' THEN 1 ELSE 0 END), 0) AS plans_done,
                COALESCE(COUNT(dap.id), 0) AS plans_total,
                MAX(dap.est_date) AS latest_due
              FROM device_actions da
              LEFT JOIN device_action_plans dap ON dap.action_id = da.id
              WHERE da.status <> 'cancelled'
              GROUP BY da.id, da.device_id
            ) ag
            JOIN devices dv ON dv.device_id = ag.device_id
            " . ($process !== 'all' ? "WHERE dv.display_type = :proc " : "") . "
            GROUP BY dv.display_type, dv.device_id, dv.product, dv.process
            HAVING " . ($tab === 'done' ? "done_count > 0" : "open_count > 0") . "
            ORDER BY dv.display_type, dv.device_id
        ";

        $params = [];
        if ($process !== 'all') {
            $params['proc'] = $process;
        }

        $rawRows = \Illuminate\Support\Facades\DB::select($sql, $params);
        $rows = array_map(function ($row) {
            return (array) $row;
        }, $rawRows);

        $data = array_map(function($r) use ($tab) {
            return [
                'type'       => $r['display_type'],
                'device_id'  => $r['device_id'],
                'product'    => $r['product'],
                'process'    => $r['process'],
                'open'       => ($tab === 'done') ? (int)$r['done_count'] : (int)$r['open_count'],
                'latest_due' => $r['latest_due'] ?: null,
            ];
        }, $rows);

        return [
            'tab'     => $tab,
            'process' => $process,
            'devices' => $data
        ];
    }

    public function getDevicesActionTable(string $displayType = 'all'): array
    {
        $params = [];
        $where  = '';
        if ($displayType !== 'all') { 
            $where = 'WHERE d.display_type = ?'; 
            $params[] = $displayType; 
        }

        $sql = "
          SELECT
            d.device_id,
            d.display_type,
            d.product,
            d.process,

            COALESCE(a.open_count, 0)     AS open_count,
            COALESCE(a.urgent_overdue, 0) AS urgent_overdue,

            la.id                         AS last_action_id,
            la.title                      AS last_title,
            la.status                     AS last_status,
            la.priority                   AS last_priority,
            DATE(lp.max_est_date)         AS last_planned_completion_date
          FROM devices d

          /* Tổng hợp theo thiết bị */
          LEFT JOIN (
            SELECT
              TRIM(a.device_id) AS device_id,
              SUM(
                CASE
                  WHEN a.status <> 'cancelled'
                   AND NOT (a.status = 'done' AND a.approval_status = 'approved')
                  THEN 1 ELSE 0
                END
              ) AS open_count,
              SUM(
                CASE
                  WHEN a.status <> 'cancelled'
                   AND NOT (a.status = 'done' AND a.approval_status = 'approved')
                   AND a.priority = 'urgent'
                   AND p.min_est_date IS NOT NULL
                   AND p.min_est_date < CURRENT_DATE()
                  THEN 1 ELSE 0
                END
              ) AS urgent_overdue
            FROM device_actions a
            LEFT JOIN (
              SELECT action_id, MIN(est_date) AS min_est_date
              FROM device_action_plans
              GROUP BY action_id
            ) p ON p.action_id = a.id
            GROUP BY TRIM(a.device_id)
          ) a ON a.device_id = d.device_id

          /* Action mới nhất theo created_at */
          LEFT JOIN (
            SELECT da.*
            FROM device_actions da
            JOIN (
              SELECT device_id, MAX(created_at) AS max_created_at
              FROM device_actions
              GROUP BY device_id
            ) mx
              ON mx.device_id = da.device_id
             AND da.created_at = mx.max_created_at
          ) la ON la.device_id = d.device_id

          /* Ngày hoàn tất dự kiến cuối cùng của action mới nhất (từ plan) */
          LEFT JOIN (
            SELECT action_id, MAX(est_date) AS max_est_date
            FROM device_action_plans
            GROUP BY action_id
          ) lp ON lp.action_id = la.id

          $where
          ORDER BY d.display_type, d.device_id
        ";

        $rawRows = \Illuminate\Support\Facades\DB::select($sql, $params);
        $rows = array_map(function ($row) {
            return (array) $row;
        }, $rawRows);

        return $rows;
    }

    public function getHourlyReport(string $deviceId, ?string $reportDateStr = null): array
    {
        $report_date_str = $reportDateStr ?? date('Y-m-d');
        $start_datetime = $report_date_str . ' 07:00:00';
        $end_datetime   = date('Y-m-d H:i:s', strtotime($start_datetime . ' +24 hours -1 second'));

        $device_config = DB::selectOne("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);

        if (!$device_config || empty($device_config->data_source)) {
            throw new \Exception('Device configuration not found or incomplete', 404);
        }

        $table = $device_config->data_source;
        if (!ctype_alnum(str_replace('_', '', $table))) {
            throw new \Exception('Invalid data source table name', 400);
        }

        $efficiency_sql = "0";
        $output_sql = "0";
        $id_column = "device_id";
        $extra_where_condition = "1=1";

        switch ($device_config->display_type) {
            case 'mold':
                $id_column = "device_id"; // Fixed schema mismatch
                $target_cycle_time = (float)($device_config->target_limit ?? 0); // sec/shot
                $efficiency_sql = "AVG( CASE WHEN t.cycle_time < 20 THEN 0 ELSE ({$target_cycle_time} / NULLIF(t.cycle_time, 0)) * 100 END )";
                $output_sql     = "AVG( CASE WHEN t.cycle_time < 20 THEN 0 ELSE (60 * t.cavities) / NULLIF(t.cycle_time, 0) END ) * 60"; // pcs/h
                break;

            case 'tuft':
                $id_column = "device_id";
                $target_output = (float)($device_config->target_limit ?? 0); // pcs/min
                $efficiency_sql = "AVG(t.output / NULLIF({$target_output}, 0)) * 100";
                $output_sql     = "AVG(t.output) * 60"; // pcs/h
                break;

            case 'blister':
                $id_column = "device_id";
                $target_cycles_per_min = (float)($device_config->target_limit ?? 0);
                $brushes_per_cycle     = (int)  ($device_config->brushes_per_cycle ?? 0);
                $target_pcs_per_min    = $target_cycles_per_min * $brushes_per_cycle;
                $efficiency_sql = "AVG(t.output / NULLIF({$target_pcs_per_min}, 0)) * 100";
                $output_sql     = "AVG(t.output) * 60"; // pcs/h
                break;

            default:
                return [
                    'status' => 'success',
                    'hourly_data' => [],
                    'lost_pcs_24' => array_fill(0,24,0),
                    'idle_24_min' => array_fill(0,24,0),
                    'window' => ['from'=>$start_datetime,'to'=>$end_datetime,'start_hour'=>7]
                ];
        }

        $sql_query = "
            SELECT HOUR(t.`datetime`) as hour_of_day,
                   {$efficiency_sql} as avg_efficiency,
                   {$output_sql}     as total_output
            FROM `{$table}` AS t
            WHERE t.`{$id_column}` = ?
              AND t.`datetime` BETWEEN ? AND ?
              AND {$extra_where_condition}
            GROUP BY hour_of_day
            ORDER BY hour_of_day ASC
        ";

        $rows = DB::select($sql_query, [$deviceId, $start_datetime, $end_datetime]);

        // Chuẩn hóa 24 giờ 07->06 (index = (hour - 7 + 24) % 24)
        $capacity_hr = (float)($device_config->capacity ?? 0);
        if ($capacity_hr < 0) $capacity_hr = 0.0;
        $cap_per_min = $capacity_hr > 0 ? $capacity_hr / 60.0 : 0.0;

        $lost_pcs_24 = array_fill(0, 24, 0.0);
        $idle_24_min = array_fill(0, 24, 0.0);
        $hourly_data = array_fill(0, 24, [
            'hour'                 => null,
            'avg_efficiency'       => 0.0,
            'total_output'         => 0.0,
            'total_loss_pcs'       => 0.0,
            'total_idle_breakdown' => 0.0,
        ]);

        foreach ($rows as $r) {
            $hAbs = (int)$r->hour_of_day;             // 0..23 lịch
            $idx  = ($hAbs - 7 + 24) % 24;              // 0..23 theo cửa sổ 07->06
            $actual = round((float)$r->total_output, 2); // pcs/h
            $lost   = max(0.0, $capacity_hr - $actual);
            $idleM  = $cap_per_min > 0 ? $lost / $cap_per_min : 0.0;

            $lost_pcs_24[$idx] = round($lost, 2);
            $idle_24_min[$idx] = round($idleM, 2);

            $hourly_data[$idx] = [
                'hour'                 => $hAbs,
                'avg_efficiency'       => round((float)$r->avg_efficiency, 2),
                'total_output'         => $actual,
                'total_loss_pcs'       => round($lost, 2),
                'total_idle_breakdown' => round($idleM, 2),
            ];
        }

        // Điền trường hour cho các giờ trống (để FE dễ debug)
        for ($i=0; $i<24; $i++) {
            if ($hourly_data[$i]['hour'] === null) {
                // Map ngược về giờ lịch:
                $hAbs = ($i + 7) % 24;
                $hourly_data[$i]['hour'] = $hAbs;
            }
        }

        return [
            'status'          => 'success',
            'device_id'       => $deviceId,
            'window'          => ['from'=>$start_datetime,'to'=>$end_datetime,'start_hour'=>7],
            'capacity'        => $capacity_hr,
            'efficiency_limit'=> 100,
            'eff_lower_limit' => (float)($device_config->efficiency_lower_limit ?? 0),
            'eff_upper_limit' => (float)($device_config->efficiency_upper_limit ?? 0),
            'hourly_data'     => $hourly_data,
            'lost_pcs_24'     => $lost_pcs_24,
            'idle_24_min'     => $idle_24_min,
        ];
    }
}
