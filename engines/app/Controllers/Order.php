<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\ProductCompositionModel;
use App\Models\StockModel;
use Config\Database;

class Order extends BaseController
{
    protected OrderModel $model;

    public function __construct()
    {
        $this->model = new OrderModel();
    }

    // POST /api/orders
    public function create()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $userId = $payload->sub ?? null;
        $shopId = $payload->shop_id ?? null;
        if (!$userId) {
            return api_respond_validation_error(['user_id' => 'User not found in token']);
        }
        // Gunakan timezone WITA untuk konsistensi
        $timezone = new \DateTimeZone('Asia/Makassar'); // UTC+08:00 (WITA)
        $now = new \DateTime('now', $timezone);
        $currentDateTime = $now->format('Y-m-d H:i:s');
        
        $data = [
            'user_id'     => (int) $userId,
            'shop_id'     => (int) $shopId,
            'status'      => 'pending',
            'notes'       => $json->notes ?? null,
            'total'       => $json->total ?? null,
            'amount_paid' => $json->amount_paid ?? 0,
            'change_money' => $json->total ? (($json->amount_paid ?? 0) - $json->total) : 0,
            'tax'         => $json->tax ?? 0,
            'payment_method' => $json->payment_method ?? 'cash',
            'created_at'  => $json->created_at ?? $currentDateTime,
            'updated_at'  => $json->created_at ?? $currentDateTime,
        ];
        $orderItems = $json->order_items ?? [];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!is_array($orderItems) || count($orderItems) === 0) {
            return api_respond_validation_error(['order_items' => 'Order items required']);
        }

        $db = Database::connect();
        $db->transStart();
        
        $orderId = $this->model->insert($data);
        if (!$orderId) {
            $db->transRollback();
            return api_respond_server_error('Failed to create order');
        }
        
        $orderItemModel = new OrderItemModel();
        $insertedItems = [];
        
        foreach ($orderItems as $item) {
            // Normalize stdClass to array for safety
            if (is_object($item)) $item = (array)$item;

            $itemData = [
                'order_id'   => $orderId,
                'product_id' => $item['product_id'] ?? null,
                'quantity'   => $item['quantity'] ?? null,
                'price'      => $item['price'] ?? null,
            ];
            if (!$orderItemModel->validate($itemData)) {
                $db->transRollback();
                return api_respond_validation_error(['order_items' => $orderItemModel->errors()]);
            }
            if (!$orderItemModel->insert($itemData)) {
                $db->transRollback();
                return api_respond_server_error('Failed to insert order item');
            }
            $insertedItems[] = $orderItemModel->find($orderItemModel->getInsertID());
        }

        // Reduce stock based on product type
        $pcModel = new ProductCompositionModel();
        $stockModel = new StockModel();
        $productModel = new \App\Models\ProductModel();
        
        foreach ($orderItems as $rawItem) {
            if (is_object($rawItem)) $rawItem = (array)$rawItem;
            
            $productId = $rawItem['product_id'] ?? null;
            $itemQty = isset($rawItem['quantity']) ? (int)$rawItem['quantity'] : 0;
            
            if (!$productId || $itemQty <= 0) {
                continue;
            }

            // Get product details to check type
            $product = $productModel->find($productId);
            if (!$product) {
                continue;
            }

            $productType = $product['type'] ?? null;
            
            if ($productType === 'product') {
                // For finished products: reduce stock of compositions/raw materials
                $comps = $pcModel->getCompositionsByProduct($productId);
                if (empty($comps)) {
                    continue; // product has no compositions -> no stock change
                }

                foreach ($comps as $comp) {
                    $compId = $comp['composition_id'] ?? null;
                    $perProdQty = $comp['quantity'] ?? null;
                    
                    if (!$compId || !$perProdQty) {
                        continue;
                    }

                    // Calculate total quantity to reduce from stock
                    $outQty = (int)$perProdQty * $itemQty;
                    
                    $stockData = [
                        'product_id' => (int)$compId,
                        'quantity'   => $outQty,
                        'type'       => 'out',
                        'notes'      => 'Order #' . $orderId . ' (BOM)',
                        'date'       => $currentDateTime,
                    ];

                    if (!$stockModel->validate($stockData)) {
                        $db->transRollback();
                        return api_respond_validation_error(['stock' => $stockModel->errors()]);
                    }
                    
                    if (!$stockModel->insert($stockData)) {
                        $db->transRollback();
                        return api_respond_server_error("Failed to reduce stock for composition {$compId}");
                    }
                }
            } elseif ($productType === 'composition') {
                // For compositions: reduce stock directly
                $stockData = [
                    'product_id' => (int)$productId,
                    'quantity'   => $itemQty,
                    'type'       => 'out',
                    'notes'      => 'Order #' . $orderId . ' (Direct)',
                    'date'       => $currentDateTime,
                ];

                if (!$stockModel->validate($stockData)) {
                    $db->transRollback();
                    return api_respond_validation_error(['stock' => $stockModel->errors()]);
                }
                
                if (!$stockModel->insert($stockData)) {
                    $db->transRollback();
                    return api_respond_server_error("Failed to reduce stock for composition {$productId}");
                }
            }
        }
        
        $db->transComplete();
        if ($db->transStatus() === false) {
            return api_respond_server_error('Transaction failed');
        }
        
        $created = $this->model->find($orderId);
        $created['order_items'] = $insertedItems;
        return api_respond_created($created, 'Order created');
    }

    // GET /api/orders/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        // Join orders dan order_items dalam satu query
        $db = Database::connect();
        $builder = $db->table('orders');
        $builder->select('orders.*, order_items.id as item_id, order_items.product_id, order_items.quantity, order_items.price, products.name as product_name');
        $builder->join('order_items', 'order_items.order_id = orders.id', 'left');
        $builder->join('products', 'products.id = order_items.product_id', 'left');
        $builder->where('orders.id', $id);
        $result = $builder->get()->getResultArray();
        if (!$result || count($result) === 0) {
            return api_respond_not_found('Order not found');
        }
        // Ambil data order dari baris pertama
        $order = [
            'id'          => $result[0]['id'],
            'user_id'     => $result[0]['user_id'],
            'shop_id'     => $result[0]['shop_id'],
            'status'      => $result[0]['status'],
            'notes'       => $result[0]['notes'],
            'total'       => $result[0]['total'],
            'amount_paid' => $result[0]['amount_paid'],
            'created_at'  => $result[0]['created_at'],
            'updated_at'  => $result[0]['updated_at'],
            'order_items' => []
        ];
        foreach ($result as $row) {
            if ($row['item_id']) {
                $order['order_items'][] = [
                    'id'         => $row['item_id'],
                    'product_id' => $row['product_id'],
                    'name'       => $row['product_name'],
                    'quantity'   => $row['quantity'],
                    'price'      => $row['price'],
                ];
            }
        }
        return api_respond_success($order, 'Order detail');
    }

    // GET /api/orders/user/{user_id}
    public function userOrders($userId = null)
    {
        if (!$this->isValidId($userId)) {
            return api_respond_validation_error(['user_id' => 'Invalid user id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        // Only allow if user matches token
        if ($payload->sub != $userId) {
            return api_respond_forbidden('Access denied');
        }
        $orders = $this->model->where('user_id', $userId)->orderBy('id', 'DESC')->findAll();
        return api_respond_success($orders, 'User orders');
    }

    // GET /api/orders/shop/{shop_id}
    public function shopOrders($shopId = null)
    {
        if (!$this->isValidId($shopId)) {
            return api_respond_validation_error(['shop_id' => 'Invalid shop id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        // Only allow if shop matches token
        if ($payload->shop_id != $shopId) {
            return api_respond_forbidden('Access denied');
        }
        $orders = $this->model->where('shop_id', $shopId)->orderBy('id', 'DESC')->findAll();
        return api_respond_success($orders, 'Shop orders');
    }

    // PUT /api/orders/status/{id}
    public function updateStatus($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $userId = $payload->sub ?? null;
        $shopId = $payload->shop_id ?? null;
        $order = $this->model->where('id', $id)
            ->groupStart()
            ->where('user_id', $userId)
            ->orWhere('shop_id', $shopId)
            ->groupEnd()
            ->first();
        if (!$order) {
            return api_respond_not_found('Order not found');
        }
        $json = $this->request->getJSON();
        if (!$json || !isset($json->status)) {
            return api_respond_validation_error(['status' => 'Status is required']);
        }
        $newStatus = $json->status;
        $data = [
            'status' => $newStatus
        ];
        // Validate status
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update status');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Order status updated');
    }
}
