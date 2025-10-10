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

        // Combine all queries into a single query to reduce connections
        $dashboardData = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM products WHERE shop_id = ? AND type = 'product') as product_quantity,
                (SELECT COUNT(*) FROM categories WHERE shop_id = ?) as category_quantity,
                (SELECT COUNT(*) FROM products WHERE shop_id = ? AND type = 'composition') as composition_quantity,
                (SELECT COUNT(*) FROM orders WHERE shop_id = ? AND created_at >= ? AND created_at <= ?) as transaction_count,
                (SELECT COALESCE(SUM(total), 0) FROM orders WHERE shop_id = ? AND created_at >= ? AND created_at <= ?) as revenue
        ", [
            $shopId, $shopId, $shopId, $shopId, 
            $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'),
            $shopId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')
        ])->getRowArray();

        $data = [
            'product_quantity' => (int)($dashboardData['product_quantity'] ?? 0),
            'category_quantity' => (int)($dashboardData['category_quantity'] ?? 0),
            'composition_quantity' => (int)($dashboardData['composition_quantity'] ?? 0),
            'transaction_count' => (int)($dashboardData['transaction_count'] ?? 0),
            'revenue' => (int)($dashboardData['revenue'] ?? 0),
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

        // Get both current and last month data in a single optimized query
        $monthlyMetrics = $this->getComparisonMetrics($db, $shopId, $currentYear, $currentMonth, $lastMonthYear, $lastMonth);
        $currentMonthData = $monthlyMetrics['current'];
        $lastMonthData = $monthlyMetrics['last'];

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
                'basis' => 'all_orders',
                'period' => 'monthly',
                'series' => $statistics,
                'note' => 'Berdasarkan semua pesanan'
            ]
        ];

        return api_respond_success($data, 'Dashboard data fetched');
    }

    private function getComparisonMetrics($db, $shopId, $currentYear, $currentMonth, $lastYear, $lastMonth)
    {
        // Single optimized query to get both current and last month metrics
        $metrics = $db->query("
            SELECT 
                SUM(CASE WHEN YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? THEN 1 ELSE 0 END) as current_transactions,
                SUM(CASE WHEN YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? THEN o.total ELSE 0 END) as current_revenue,
                SUM(CASE WHEN YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? THEN 1 ELSE 0 END) as last_transactions,
                SUM(CASE WHEN YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? THEN o.total ELSE 0 END) as last_revenue,
                COALESCE(items.current_items_sold, 0) as current_items_sold,
                COALESCE(items.last_items_sold, 0) as last_items_sold
            FROM orders o
            LEFT JOIN (
                SELECT 
                    SUM(CASE WHEN YEAR(ord.created_at) = ? AND MONTH(ord.created_at) = ? THEN oi.quantity ELSE 0 END) as current_items_sold,
                    SUM(CASE WHEN YEAR(ord.created_at) = ? AND MONTH(ord.created_at) = ? THEN oi.quantity ELSE 0 END) as last_items_sold
                FROM order_items oi
                JOIN orders ord ON ord.id = oi.order_id
                WHERE ord.shop_id = ?
                    AND ((YEAR(ord.created_at) = ? AND MONTH(ord.created_at) = ?) 
                         OR (YEAR(ord.created_at) = ? AND MONTH(ord.created_at) = ?))
            ) items ON 1=1
            WHERE o.shop_id = ?
                AND ((YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?) 
                     OR (YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?))
        ", [
            $currentYear, $currentMonth, // current transactions
            $currentYear, $currentMonth, // current revenue  
            $lastYear, $lastMonth,       // last transactions
            $lastYear, $lastMonth,       // last revenue
            $currentYear, $currentMonth, // current items sold
            $lastYear, $lastMonth,       // last items sold
            $shopId,                     // items subquery shop_id
            $currentYear, $currentMonth, // items subquery current
            $lastYear, $lastMonth,       // items subquery last
            $shopId,                     // main query shop_id
            $currentYear, $currentMonth, // main query current
            $lastYear, $lastMonth        // main query last
        ])->getRowArray();

        // Process current month data
        $currentTransactions = (int)($metrics['current_transactions'] ?? 0);
        $currentRevenue = (float)($metrics['current_revenue'] ?? 0);
        $currentItemsSold = (int)($metrics['current_items_sold'] ?? 0);
        $currentAov = $currentTransactions > 0 ? $currentRevenue / $currentTransactions : 0;

        // Process last month data
        $lastTransactions = (int)($metrics['last_transactions'] ?? 0);
        $lastRevenue = (float)($metrics['last_revenue'] ?? 0);
        $lastItemsSold = (int)($metrics['last_items_sold'] ?? 0);
        $lastAov = $lastTransactions > 0 ? $lastRevenue / $lastTransactions : 0;

        return [
            'current' => [
                'transactions' => $currentTransactions,
                'revenue' => $currentRevenue,
                'items_sold' => $currentItemsSold,
                'aov' => round($currentAov, 2)
            ],
            'last' => [
                'transactions' => $lastTransactions,
                'revenue' => $lastRevenue,
                'items_sold' => $lastItemsSold,
                'aov' => round($lastAov, 2)
            ]
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
        
        // Get all months data in a single query
        $results = $db->query("
            SELECT 
                MONTH(created_at) as month_num,
                COUNT(*) as transactions,
                COALESCE(SUM(total), 0) as revenue
            FROM orders 
            WHERE shop_id = ? AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
            ORDER BY month_num
        ", [$shopId, $year])->getResultArray();

        // Create array with all months (initialize with 0 values)
        $sales = [];
        for ($month = 1; $month <= 12; $month++) {
            $sales[$month] = [
                'month' => $months[$month - 1],
                'transactions' => 0,
                'revenue' => 0.0
            ];
        }

        // Fill with actual data
        foreach ($results as $result) {
            $monthNum = (int)$result['month_num'];
            $sales[$monthNum] = [
                'month' => $months[$monthNum - 1],
                'transactions' => (int)$result['transactions'],
                'revenue' => (float)$result['revenue']
            ];
        }

        return array_values($sales);
    }

    private function getStatistics($db, $shopId, $year)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Des'];
        
        // Get all statistics in a single query
        $results = $db->query("
            SELECT 
                MONTH(o.created_at) as month_num,
                COUNT(o.id) as orders,
                COALESCE(SUM(oi.quantity), 0) as items_sold
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.shop_id = ? AND YEAR(o.created_at) = ?
            GROUP BY MONTH(o.created_at)
            ORDER BY month_num
        ", [$shopId, $year])->getResultArray();

        // Create array with all months (initialize with 0 values)
        $statistics = [];
        for ($month = 1; $month <= 12; $month++) {
            $statistics[$month] = [
                'month' => $months[$month - 1],
                'orders' => 0,
                'items_sold' => 0
            ];
        }

        // Fill with actual data
        foreach ($results as $result) {
            $monthNum = (int)$result['month_num'];
            $statistics[$monthNum] = [
                'month' => $months[$monthNum - 1],
                'orders' => (int)$result['orders'],
                'items_sold' => (int)$result['items_sold']
            ];
        }

        return array_values($statistics);
    }
}