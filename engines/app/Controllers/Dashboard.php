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
            'date' => $now->format('Y-m-d'),
        ];

        return api_respond_success($data, 'Dashboard data retrieved successfully');
    }
}
