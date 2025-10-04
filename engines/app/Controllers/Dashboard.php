<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Config\Database;

class Dashboard extends BaseController
{
    /**
     * GET /api/dashboard
     * Dashboard summary with counts and revenue for today
     */
    public function index()
    {
        // Auth & get shop_id from token
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_validation_error(['shop_id' => 'No shop assigned']);
        }

        // Always use today period
        $now = new \DateTime('now');
        $start = clone $now;
        $start->setTime(0, 0, 0); // Start of today (00:00:00)
        $end = clone $now; // Current time

        $db = Database::connect();

        // Get product quantity
        $productQuery = $db->table('products')
            ->where('shop_id', $shopId)
            ->countAllResults();

        // Get category quantity
        $categoryQuery = $db->table('categories')
            ->where('shop_id', $shopId)
            ->countAllResults();

        // Get composition quantity
        $compositionQuery = $db->table('compositions')
            ->where('shop_id', $shopId)
            ->countAllResults();

        // Get transaction count and revenue for today
        $transactionQuery = $db->table('orders')
            ->select('COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as revenue')
            ->where('shop_id', $shopId)
            ->where('created_at >=', $start->format('Y-m-d H:i:s'))
            ->where('created_at <=', $end->format('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        $data = [
            'product_quantity' => (int)$productQuery,
            'category_quantity' => (int)$categoryQuery,
            'composition_quantity' => (int)$compositionQuery,
            'transaction_count' => (int)($transactionQuery['transaction_count'] ?? 0),
            'revenue' => (int)($transactionQuery['revenue'] ?? 0),
            'date' => $now->format('d-m-Y'),
        ];

        return api_respond_success($data, 'Dashboard data retrieved successfully');
    }

    public function dashboardWeb()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }

        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_unauthorized('No shop assigned to user');
        }

        $db = Database::connect();
        $currentYear = date('Y');
        $currentMonth = date('n');
        $lastMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $lastMonthYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

        // Get current month data
        $currentMonthData = $this->getMonthlyMetrics($db, $shopId, $currentYear, $currentMonth);
        
        // Get last month data for comparison
        $lastMonthData = $this->getMonthlyMetrics($db, $shopId, $lastMonthYear, $lastMonth);

        // Calculate growth percentages
        $transactionsGrowth = $this->calculateGrowth($currentMonthData['transactions'], $lastMonthData['transactions']);
        $revenueGrowth = $this->calculateGrowth($currentMonthData['revenue'], $lastMonthData['revenue']);
        $itemsSoldGrowth = $this->calculateGrowth($currentMonthData['items_sold'], $lastMonthData['items_sold']);
        $aovGrowth = $this->calculateGrowth($currentMonthData['aov'], $lastMonthData['aov']);

        // Get monthly sales data for the year
        $monthlySales = $this->getMonthlySales($db, $shopId, $currentYear);

        // Get statistics data for the year
        $statistics = $this->getStatistics($db, $shopId, $currentYear);

        $data = [
            'summary' => [
                [
                    'key' => 'transactions',
                    'label' => 'Transaksi',
                    'total' => $currentMonthData['transactions'],
                    'growth_pct' => $transactionsGrowth['percentage'],
                    'direction' => $transactionsGrowth['direction'],
                    'compare_text' => 'vs. bulan lalu'
                ],
                [
                    'key' => 'gross_revenue',
                    'label' => 'Pendapatan Kotor',
                    'total' => $currentMonthData['revenue'],
                    'growth_pct' => $revenueGrowth['percentage'],
                    'direction' => $revenueGrowth['direction'],
                    'compare_text' => 'vs. bulan lalu'
                ],
                [
                    'key' => 'items_sold',
                    'label' => 'Barang Terjual',
                    'total' => $currentMonthData['items_sold'],
                    'growth_pct' => $itemsSoldGrowth['percentage'],
                    'direction' => $itemsSoldGrowth['direction'],
                    'compare_text' => 'vs. bulan lalu'
                ],
                [
                    'key' => 'aov',
                    'label' => 'Rata-rata Transaksi',
                    'total' => $currentMonthData['aov'],
                    'growth_pct' => $aovGrowth['percentage'],
                    'direction' => $aovGrowth['direction'],
                    'compare_text' => 'vs. bulan lalu'
                ]
            ],
            'monthly_sales' => $monthlySales,
            'statistics' => [
                'basis' => 'completed_orders',
                'period' => 'monthly',
                'series' => $statistics,
                'note' => 'Berdasarkan total pesanan selesai'
            ]
        ];

        return api_respond_success($data, 'Dashboard data fetched');
    }

    private function getMonthlyMetrics($db, $shopId, $year, $month)
    {
        // Get transactions count and revenue
        $orderMetrics = $db->table('orders')
            ->select('COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->where('shop_id', $shopId)
            ->where('status', 'completed')
            ->where('YEAR(created_at)', $year)
            ->where('MONTH(created_at)', $month)
            ->get()
            ->getRowArray();

        // Get items sold
        $itemsSold = $db->table('order_items oi')
            ->select('COALESCE(SUM(oi.quantity), 0) as items_sold')
            ->join('orders o', 'o.id = oi.order_id')
            ->where('o.shop_id', $shopId)
            ->where('o.status', 'completed')
            ->where('YEAR(o.created_at)', $year)
            ->where('MONTH(o.created_at)', $month)
            ->get()
            ->getRowArray();

        $transactions = (int)$orderMetrics['transactions'];
        $revenue = (float)$orderMetrics['revenue'];
        $itemsSoldCount = (int)$itemsSold['items_sold'];
        $aov = $transactions > 0 ? $revenue / $transactions : 0;

        return [
            'transactions' => $transactions,
            'revenue' => $revenue,
            'items_sold' => $itemsSoldCount,
            'aov' => round($aov, 2)
        ];
    }

    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'down'
            ];
        }

        $growth = (($current - $previous) / $previous) * 100;
        return [
            'percentage' => round(abs($growth), 2),
            'direction' => $growth >= 0 ? 'up' : 'down'
        ];
    }

    private function getMonthlySales($db, $shopId, $year)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Des'];
        $sales = [];

        for ($month = 1; $month <= 12; $month++) {
            $result = $db->table('orders')
                ->select('COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
                ->where('shop_id', $shopId)
                ->where('status', 'completed')
                ->where('YEAR(created_at)', $year)
                ->where('MONTH(created_at)', $month)
                ->get()
                ->getRowArray();

            $sales[] = [
                'month' => $months[$month - 1],
                'transactions' => (int)$result['transactions'],
                'revenue' => (float)$result['revenue']
            ];
        }

        return $sales;
    }

    private function getStatistics($db, $shopId, $year)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Des'];
        $statistics = [];

        for ($month = 1; $month <= 12; $month++) {
            // Get orders count
            $orderCount = $db->table('orders')
                ->selectCount('id', 'orders')
                ->where('shop_id', $shopId)
                ->where('status', 'completed')
                ->where('YEAR(created_at)', $year)
                ->where('MONTH(created_at)', $month)
                ->get()
                ->getRowArray();

            // Get items sold
            $itemsSold = $db->table('order_items oi')
                ->select('COALESCE(SUM(oi.quantity), 0) as items_sold')
                ->join('orders o', 'o.id = oi.order_id')
                ->where('o.shop_id', $shopId)
                ->where('o.status', 'completed')
                ->where('YEAR(o.created_at)', $year)
                ->where('MONTH(o.created_at)', $month)
                ->get()
                ->getRowArray();

            $statistics[] = [
                'month' => $months[$month - 1],
                'orders' => (int)$orderCount['orders'],
                'items_sold' => (int)$itemsSold['items_sold']
            ];
        }

        return $statistics;
    }
}