<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class Report extends BaseController
{
    /**
     * GET /api/reports/{shop_id}/summary?period=today|this_week|this_month
     * Simple summary & transaction count report
     */
    public function summary($shopId = null)
    {
        // Validasi shop id
        if (!$this->isValidId($shopId)) {
            return api_respond_validation_error(['shop_id' => 'Invalid shop id']);
        }

        // Auth & otorisasi
        $payload = $this->decodeToken();
        if (!$payload) return api_respond_unauthorized('Invalid token');
        if (($payload->shop_id ?? null) != $shopId) {
            return api_respond_forbidden('Access denied');
        }

        $period = $this->request->getGet('period') ?? 'today';
        if (!in_array($period, ['today', 'this_week', 'this_month'])) {
            return api_respond_validation_error(['period' => 'Invalid period (today, this_week, this_month)']);
        }

        // Hitung range waktu dengan zona waktu WITA (UTC+08:00)
        $timezone = new \DateTimeZone('Asia/Makassar'); // UTC+08:00 (WITA)
        $now = new \DateTime('now', $timezone);
        $start = clone $now;
        switch ($period) {
            case 'today':
                $start->setTime(0,0,0);
                break;
            case 'this_week':
                $dayOfWeek = (int)$now->format('N'); // 1 (Mon) - 7 (Sun)
                $start->modify('-'.($dayOfWeek-1).' days')->setTime(0,0,0);
                break;
            case 'this_month':
                $start->modify('first day of this month')->setTime(0,0,0);
                break;
        }
        $end = clone $now;

        $db = Database::connect();
        $builder = $db->table('orders');
        $builder->select("COUNT(*) as total_transactions, COALESCE(SUM(total),0) as total_revenue");
        $builder->where('shop_id', $shopId);
        $builder->where('created_at >=', $start->format('Y-m-d H:i:s'));
        $builder->where('created_at <=', $end->format('Y-m-d H:i:s'));
        $result = $builder->get()->getRowArray() ?? [];

        $data = [
            'total_revenue' => number_format((float)($result['total_revenue'] ?? 0), 2, '.', ''),
            'total_transactions' => (int)($result['total_transactions'] ?? 0)
        ];

        return api_respond_success($data, 'Report data successfully obtained');
    }

    /**
     * GET /api/reports/{shop_id}/orders?period=today|this_week|this_month
     * Get orders with period filter
     */
    public function orders($shopId = null)
    {
        // Validasi shop id
        if (!$this->isValidId($shopId)) {
            return api_respond_validation_error(['shop_id' => 'Invalid shop id']);
        }

        // Auth & otorisasi
        $payload = $this->decodeToken();
        if (!$payload) return api_respond_unauthorized('Invalid token');
        if (($payload->shop_id ?? null) != $shopId) {
            return api_respond_forbidden('Access denied');
        }

        $period = $this->request->getGet('period') ?? 'today';
        $status = $this->request->getGet('status');

        if (!in_array($period, ['today', 'this_week', 'this_month'])) {
            return api_respond_validation_error(['period' => 'Invalid period (today, this_week, this_month)']);
        }

        // Hitung range waktu dengan zona waktu WITA (UTC+08:00)
        $timezone = new \DateTimeZone('Asia/Makassar'); // UTC+08:00 (WITA)
        $now = new \DateTime('now', $timezone);
        $start = clone $now;
        switch ($period) {
            case 'today':
                $start->setTime(0,0,0);
                break;
            case 'this_week':
                $dayOfWeek = (int)$now->format('N');
                $start->modify('-'.($dayOfWeek-1).' days')->setTime(0,0,0);
                break;
            case 'this_month':
                $start->modify('first day of this month')->setTime(0,0,0);
                break;
        }
        $end = clone $now;

        $db = Database::connect();
        $builder = $db->table('orders o');
        $builder->select('o.*, COALESCE(SUM(oi.quantity), 0) as total_items');
        $builder->join('order_items oi', 'oi.order_id = o.id', 'left');
        $builder->where('o.shop_id', $shopId);
        $builder->where('o.created_at >=', $start->format('Y-m-d H:i:s'));
        $builder->where('o.created_at <=', $end->format('Y-m-d H:i:s'));
        
        if ($status && in_array($status, ['pending', 'paid', 'shipped', 'completed', 'cancelled'])) {
            $builder->where('o.status', $status);
        }

        $builder->groupBy('o.id');

        // Get all orders without pagination
        $orders = $builder->orderBy('o.created_at', 'DESC')
                         ->get()
                         ->getResultArray();

        $data = [
            'orders' => $orders,
            'total_orders' => count($orders),
            'period' => $period,
            // 'status_filter' => $status,
            'date_range' => [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s')
            ]
        ];

        return api_respond_success($data, 'Orders retrieved successfully');
    }

    /**
     * GET /api/reports/{shop_id}/ordersv2?range_start=DD-MM-YYYY&range_end=DD-MM-YYYY&status=pending&limit=20&offset=0
     * Get orders with custom date range filter
     */
    public function ordersv2($shopId = null)
    {
        // Validasi shop id
        if (!$this->isValidId($shopId)) {
            return api_respond_validation_error(['shop_id' => 'Invalid shop id']);
        }

        // Auth & otorisasi
        $payload = $this->decodeToken();
        if (!$payload) return api_respond_unauthorized('Invalid token');
        if (($payload->shop_id ?? null) != $shopId) {
            return api_respond_forbidden('Access denied');
        }

        // Get parameters
        $rangeStart = $this->request->getGet('range_start'); // Format: DD-MM-YYYY
        $rangeEnd = $this->request->getGet('range_end');     // Format: DD-MM-YYYY
        $limit = (int)($this->request->getGet('limit') ?? 20);
        $offset = (int)($this->request->getGet('offset') ?? 0);

        // Validasi limit dan offset
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        // Setup timezone WITA
        $timezone = new \DateTimeZone('Asia/Makassar'); // UTC+08:00 (WITA)
        
        // Parse dan validasi tanggal
        try {
            if ($rangeStart) {
                $start = \DateTime::createFromFormat('d-m-Y', $rangeStart, $timezone);
                if (!$start) {
                    return api_respond_validation_error(['range_start' => 'Invalid date format. Use DD-MM-YYYY']);
                }
                $start->setTime(0, 0, 0); // Set to start of day
            } else {
                // Default: today
                $start = new \DateTime('now', $timezone);
                $start->setTime(0, 0, 0);
            }

            if ($rangeEnd) {
                $end = \DateTime::createFromFormat('d-m-Y', $rangeEnd, $timezone);
                if (!$end) {
                    return api_respond_validation_error(['range_end' => 'Invalid date format. Use DD-MM-YYYY']);
                }
                $end->setTime(23, 59, 59); // Set to end of day
            } else {
                // Default: today
                $end = new \DateTime('now', $timezone);
                $end->setTime(23, 59, 59);
            }

            // Validasi range
            if ($start > $end) {
                return api_respond_validation_error(['date_range' => 'Start date cannot be later than end date']);
            }

            // Maksimal range 1 tahun
            $maxDays = 365;
            $daysDiff = $end->diff($start)->days;
            if ($daysDiff > $maxDays) {
                return api_respond_validation_error(['date_range' => 'Date range cannot exceed 365 days']);
            }

        } catch (\Exception $e) {
            return api_respond_validation_error(['date_range' => 'Invalid date format']);
        }

        $db = Database::connect();
        
        // Query untuk mendapatkan total count
        $countBuilder = $db->table('orders o');
        $countBuilder->select('COUNT(*) as total_count');
        $countBuilder->where('o.shop_id', $shopId);
        $countBuilder->where('o.created_at >=', $start->format('Y-m-d H:i:s'));
        $countBuilder->where('o.created_at <=', $end->format('Y-m-d H:i:s'));
        
        $totalCount = (int)$countBuilder->get()->getRowArray()['total_count'];

        // Query untuk mendapatkan data orders dengan pagination
        $builder = $db->table('orders o');
        $builder->select('o.*, COALESCE(SUM(oi.quantity), 0) as total_items');
        $builder->join('order_items oi', 'oi.order_id = o.id', 'left');
        $builder->where('o.shop_id', $shopId);
        $builder->where('o.created_at >=', $start->format('Y-m-d H:i:s'));
        $builder->where('o.created_at <=', $end->format('Y-m-d H:i:s'));

        $builder->groupBy('o.id');
        $builder->orderBy('o.created_at', 'DESC');
        $builder->limit($limit, $offset);

        $orders = $builder->get()->getResultArray();

        // Calculate pagination info
        $totalPages = ceil($totalCount / $limit);
        $currentPage = floor($offset / $limit) + 1;

        // Filter untuk meta
        $appliedFilters = [];
        if ($rangeStart) $appliedFilters['range_start'] = $rangeStart;
        if ($rangeEnd) $appliedFilters['range_end'] = $rangeEnd;

        return api_respond_success($orders, 'Orders retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => $totalPages
            ],
            'filters' => $appliedFilters,
        ]);
    }
}
