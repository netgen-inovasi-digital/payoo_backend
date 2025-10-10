<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CompositionModel;
use App\Models\ShopModel;
use App\Models\StockModel;
use Config\Database;

class Composition extends BaseController
{
    protected CompositionModel $model;

    public function __construct()
    {
        $this->model = new CompositionModel();
    }

    // GET /api/compositions
    public function index()
    {
        // Ambil shop_id dari token JWT
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_success([], 'No shop assigned');
        }

        // Use optimized method from CompositionModel
        $items = $this->model->getCompositionsWithStockByShop($shopId);
        
        return api_respond_success($items, 'Composition list');
    }

    // GET /api/compositions/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        
        // Use optimized method from CompositionModel
        $item = $this->model->getCompositionWithStockById($id, $shopId);
        
        if (!$item) {
            return api_respond_not_found('Composition not found');
        }
        
        return api_respond_success($item, 'Composition detail');
    }

    // POST /api/compositions
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
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_validation_error(['shop_id' => 'No shop in token']);
        }
        $data = [
            'shop_id'       => (int) $shopId,
            'name'          => trim($json->name ?? ''),
            'description'   => trim($json->description ?? ''),
            'category_id'   => $json->category_id ?? null,
            'photo'         => trim($json->photo ?? ''),
            'type'          => 'composition',
            'unit'          => $json->unit ?? null,
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
        ];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        // foreign key check
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create composition');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Composition created');
    }

    // PUT /api/compositions/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Composition not found');
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = [
            'shop_id'       => $existing['shop_id'], // tidak boleh diubah lewat update
            'name'          => isset($json->name) ? trim($json->name) : $existing['name'],
            'description'   => isset($json->description) ? trim($json->description) : $existing['description'],
            'category_id'   => isset($json->category_id) ? $json->category_id : $existing['category_id'],
            'photo'         => isset($json->photo) ? trim($json->photo) : $existing['photo'],
            'type'          => 'composition',
            'unit'          => isset($json->unit) ? $json->unit : $existing['unit'],
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
        ];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update composition');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Composition updated');
    }

    // DELETE /api/compositions/{id}
    public function delete($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Composition not found');
        }
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete composition');
        }
        return api_respond_success(null, 'Composition deleted');
    }

    // POST /api/compositions/with-stock
    public function createWithStock()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_validation_error(['shop_id' => 'No shop in token']);
        }

        // Prepare composition data
        $compositionData = [
            'shop_id'       => (int) $shopId,
            'name'          => trim($json->name ?? ''),
            'description'   => trim($json->description ?? ''),
            'photo'         => trim($json->photo ?? ''),
            'type'          => 'composition',
            'unit'          => $json->unit ?? null,
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
        ];

        // Validate composition
        if (!$this->model->validate($compositionData)) {
            return api_respond_validation_error($this->model->errors());
        }

        // Validate shop exists
        if (!(new ShopModel())->find($compositionData['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }

        // Validate stock if provided
        $initialStock = $json->stock ?? null;
        if ($initialStock !== null) {
            if (!is_numeric($initialStock) || $initialStock < 0) {
                return api_respond_validation_error(['stock' => 'Stock must be numeric and non-negative']);
            }
        }

        // Start transaction
        $db = Database::connect();
        $db->transStart();

        // Create composition
        if (!$this->model->insert($compositionData)) {
            $db->transRollback();
            return api_respond_server_error('Failed to create composition');
        }

        $compositionId = $this->model->getInsertID();

        // Create initial stock record if stock is provided and > 0
        if ($initialStock !== null && $initialStock > 0) {
            $stockModel = new StockModel();
            $stockData = [
                'product_id' => $compositionId,
                'quantity'       => (int) $initialStock,
                'type'           => 'in',
                'date'           => date('Y-m-d H:i:s'),
            ];

            if (!$stockModel->validate($stockData)) {
                $db->transRollback();
                return api_respond_validation_error(['stock' => $stockModel->errors()]);
            }

            if (!$stockModel->insert($stockData)) {
                $db->transRollback();
                return api_respond_server_error('Failed to create initial stock');
            }
        }

        $db->transComplete();
        
        if ($db->transStatus() === false) {
            return api_respond_server_error('Transaction failed');
        }

        // Get created composition with stock
        $created = $this->model->find($compositionId);
        $created['stock'] = (int) ($initialStock ?? 0);

        return api_respond_created($created, 'Composition with stock created');
    }

    // PUT /api/compositions/{id}/with-stock
    public function updateWithStock($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }

        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Composition not found');
        }

        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        // Prepare composition data
        $compositionData = [
            'shop_id'       => $existing['shop_id'], // tidak boleh diubah
            'name'          => isset($json->name) ? trim($json->name) : $existing['name'],
            'description'   => isset($json->description) ? trim($json->description) : $existing['description'],
            'photo'         => isset($json->photo) ? trim($json->photo) : $existing['photo'],
            'type'          => 'composition',
            'unit'          => isset($json->unit) ? $json->unit : $existing['unit'],
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
        ];

        // Validate composition
        if (!$this->model->validate($compositionData)) {
            return api_respond_validation_error($this->model->errors());
        }

        // Handle stock update if provided
        $newStock = $json->stock ?? null;
        if ($newStock !== null) {
            if (!is_numeric($newStock) || $newStock < 0) {
                return api_respond_validation_error(['stock' => 'Stock must be numeric and non-negative']);
            }

            // Calculate current stock
            $db = Database::connect();
            $currentStockQuery = $db->table('stocks')
                ->select('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
                ->where('product_id', $id)
                ->get()
                ->getRowArray();
            
            $currentStock = isset($currentStockQuery['stock_total']) ? (int) $currentStockQuery['stock_total'] : 0;
            $stockDifference = (int) $newStock - $currentStock;

            // Start transaction
            $db->transStart();

            // Update composition
            if (!$this->model->update($id, $compositionData)) {
                $db->transRollback();
                return api_respond_server_error('Failed to update composition');
            }

            // Add stock adjustment if needed
            if ($stockDifference != 0) {
                $stockModel = new StockModel();
                $stockData = [
                    'product_id' => $id,
                    'quantity'       => abs($stockDifference),
                    'type'           => $stockDifference > 0 ? 'in' : 'out',
                    'date'           => date('Y-m-d H:i:s'),
                ];

                if (!$stockModel->validate($stockData)) {
                    $db->transRollback();
                    return api_respond_validation_error(['stock' => $stockModel->errors()]);
                }

                if (!$stockModel->insert($stockData)) {
                    $db->transRollback();
                    return api_respond_server_error('Failed to adjust stock');
                }
            }

            $db->transComplete();
            
            if ($db->transStatus() === false) {
                return api_respond_server_error('Transaction failed');
            }
        } else {
            // Update composition only (no stock change)
            if (!$this->model->update($id, $compositionData)) {
                return api_respond_server_error('Failed to update composition');
            }

            // Get current stock for response
            $db = Database::connect();
            $stockQuery = $db->table('stocks')
                ->select('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
                ->where('product_id', $id)
                ->get()
                ->getRowArray();
            
            $newStock = isset($stockQuery['stock_total']) ? (int) $stockQuery['stock_total'] : 0;
        }

        // Get updated composition with stock
        $updated = $this->model->find($id);
        $updated['stock'] = (int) $newStock;

        return api_respond_success($updated, 'Composition with stock updated');
    }
}
