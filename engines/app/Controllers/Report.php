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

        // Hitung range waktu
        $now = new \DateTime('now');
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
     * GET /api/reports/{shop_id}/orders?period=today|this_week|this_month&status=pending&limit=50&offset=0
     * Get orders with period filter and pagination
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
        $limit = (int)($this->request->getGet('limit') ?? 50);
        $offset = (int)($this->request->getGet('offset') ?? 0);

        if (!in_array($period, ['today', 'this_week', 'this_month'])) {
            return api_respond_validation_error(['period' => 'Invalid period (today, this_week, this_month)']);
        }

        if ($limit > 100) $limit = 100; // Max limit
        if ($limit < 1) $limit = 10;
        if ($offset < 0) $offset = 0;

        // Hitung range waktu
        $now = new \DateTime('now');
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

        // Count total untuk pagination info (perlu query terpisah karena GROUP BY)
        $countBuilder = $db->table('orders');
        $countBuilder->where('shop_id', $shopId);
        $countBuilder->where('created_at >=', $start->format('Y-m-d H:i:s'));
        $countBuilder->where('created_at <=', $end->format('Y-m-d H:i:s'));
        
        if ($status && in_array($status, ['pending', 'paid', 'shipped', 'completed', 'cancelled'])) {
            $countBuilder->where('status', $status);
        }
        
        $total = $countBuilder->countAllResults();

        // Get orders dengan limit dan offset
        $orders = $builder->orderBy('o.created_at', 'DESC')
                         ->limit($limit, $offset)
                         ->get()
                         ->getResultArray();

        $data = [
            'orders' => $orders,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ],
            'period' => $period,
            'status_filter' => $status,
            'date_range' => [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s')
            ]
        ];

        return api_respond_success($data, 'Orders retrieved successfully');
    }
}
