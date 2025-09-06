<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductModel;
use App\Models\ShopModel;
use App\Models\CategoryModel;
use App\Models\ProductCompositionModel;
use App\Models\CompositionModel;
use Config\Database;

class Product extends BaseController
{
    protected ProductModel $model;
    protected ProductCompositionModel $productCompositionModel;

    public function __construct()
    {
        $this->model = new ProductModel();
        $this->productCompositionModel = new ProductCompositionModel();
    }

    // GET /api/products
    public function index()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_success([], 'No shop assigned');
        }
        $products = $this->model->where('shop_id', $shopId)->orderBy('id', 'DESC')->findAll();
        return api_respond_success($products, 'Product list');
    }

    // GET /api/products/{id}
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
        $product = $this->model->where('shop_id', $shopId)->find($id);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }
        
        // Tambahkan compositions untuk product ini
        $product['compositions'] = $this->productCompositionModel->getCompositionsByProduct($id);
        
        return api_respond_success($product, 'Product detail');
    }

    // POST /api/products
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
            'category_id'   => (int) ($json->category_id ?? 0),
            'name'          => trim($json->name ?? ''),
            'description'   => trim($json->description ?? ''),
            'photo'         => trim($json->photo ?? ''),
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
        ];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }

        // Ensure FK existence
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!(new CategoryModel())->find($data['category_id'])) {
            return api_respond_validation_error(['category_id' => 'Category not found']);
        }

        // Validasi compositions jika ada
        $compositions = $json->compositions ?? [];
        if (!empty($compositions)) {
            if (!is_array($compositions)) {
                return api_respond_validation_error(['compositions' => 'Compositions must be an array']);
            }
            
            $compositionModel = new CompositionModel();
            foreach ($compositions as $compositionId) {
                if (!is_numeric($compositionId)) {
                    return api_respond_validation_error(['compositions' => 'All composition IDs must be numeric']);
                }
                if (!$compositionModel->where('shop_id', $shopId)->find($compositionId)) {
                    return api_respond_validation_error(['compositions' => "Composition with ID {$compositionId} not found in your shop"]);
                }
            }
        }

        $db = Database::connect();
        $db->transStart();

        if (!$this->model->insert($data)) {
            $db->transRollback();
            return api_respond_server_error('Failed to create product');
        }

        $productId = $this->model->getInsertID();

        // Insert compositions jika ada
        if (!empty($compositions)) {
            foreach ($compositions as $compositionId) {
                $compositionData = [
                    'product_id'     => $productId,
                    'composition_id' => (int) $compositionId
                ];
                if (!$this->productCompositionModel->insert($compositionData)) {
                    $db->transRollback();
                    return api_respond_server_error('Failed to create product compositions');
                }
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return api_respond_server_error('Transaction failed');
        }

        $created = $this->model->find($productId);
        $created['compositions'] = $this->productCompositionModel->getCompositionsByProduct($productId);
        
        return api_respond_created($created, 'Product created');
    }

    // PUT /api/products/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Product not found');
        }

        $data = [
            'shop_id'       => $existing['shop_id'], // tidak boleh diubah lewat update
            'category_id'   => isset($json->category_id) ? (int)$json->category_id : $existing['category_id'],
            'name'          => isset($json->name) ? trim($json->name) : $existing['name'],
            'photo'         => isset($json->photo) ? trim($json->photo) : $existing['photo'],
            'description'   => isset($json->description) ? trim($json->description) : $existing['description'],
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
        ];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!(new CategoryModel())->find($data['category_id'])) {
            return api_respond_validation_error(['category_id' => 'Category not found']);
        }

        // Validasi compositions jika ada
        $compositions = null;
        if (isset($json->compositions)) {
            $compositions = $json->compositions;
            if (!is_array($compositions)) {
                return api_respond_validation_error(['compositions' => 'Compositions must be an array']);
            }
            
            $compositionModel = new CompositionModel();
            foreach ($compositions as $compositionId) {
                if (!is_numeric($compositionId)) {
                    return api_respond_validation_error(['compositions' => 'All composition IDs must be numeric']);
                }
                if (!$compositionModel->where('shop_id', $shopId)->find($compositionId)) {
                    return api_respond_validation_error(['compositions' => "Composition with ID {$compositionId} not found in your shop"]);
                }
            }
        }

        $db = Database::connect();
        $db->transStart();

        if (!$this->model->update($id, $data)) {
            $db->transRollback();
            return api_respond_server_error('Failed to update product');
        }

        // Update compositions jika disediakan
        if ($compositions !== null) {
            // Hapus semua compositions lama
            $this->productCompositionModel->deleteByProduct($id);
            
            // Insert compositions baru
            foreach ($compositions as $compositionId) {
                $compositionData = [
                    'product_id'     => $id,
                    'composition_id' => (int) $compositionId
                ];
                if (!$this->productCompositionModel->insert($compositionData)) {
                    $db->transRollback();
                    return api_respond_server_error('Failed to update product compositions');
                }
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return api_respond_server_error('Transaction failed');
        }

        $updated = $this->model->find($id);
        $updated['compositions'] = $this->productCompositionModel->getCompositionsByProduct($id);
        
        return api_respond_success($updated, 'Product updated');
    }

    // DELETE /api/products/{id}
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
            return api_respond_not_found('Product not found');
        }

        $db = Database::connect();
        $db->transStart();

        // Hapus compositions terlebih dahulu
        $this->productCompositionModel->deleteByProduct($id);

        // Hapus product
        if (!$this->model->delete($id)) {
            $db->transRollback();
            return api_respond_server_error('Failed to delete product');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return api_respond_server_error('Transaction failed');
        }

        return api_respond_success(null, 'Product deleted');
    }

    // POST /api/products/{id}/compositions
    public function addComposition($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid product id']);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;

        // Cek product ada dan milik shop yang benar
        $product = $this->model->where('shop_id', $shopId)->find($id);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }

        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        $compositionId = $json->composition_id ?? null;
        if (!$compositionId || !is_numeric($compositionId)) {
            return api_respond_validation_error(['composition_id' => 'Valid composition ID is required']);
        }

        // Validasi composition ada dan milik shop yang benar
        $compositionModel = new CompositionModel();
        if (!$compositionModel->where('shop_id', $shopId)->find($compositionId)) {
            return api_respond_validation_error(['composition_id' => 'Composition not found in your shop']);
        }

        // Cek apakah sudah ada
        $existing = $this->productCompositionModel
            ->where('product_id', $id)
            ->where('composition_id', $compositionId)
            ->first();

        if ($existing) {
            return api_respond_validation_error(['composition_id' => 'Composition already added to this product']);
        }

        $data = [
            'product_id'     => $id,
            'composition_id' => (int) $compositionId
        ];

        if (!$this->productCompositionModel->insert($data)) {
            return api_respond_server_error('Failed to add composition to product');
        }

        $compositions = $this->productCompositionModel->getCompositionsByProduct($id);
        return api_respond_success(['compositions' => $compositions], 'Composition added to product');
    }

    // DELETE /api/products/{id}/compositions/{composition_id}
    public function removeComposition($id = null, $compositionId = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid product id']);
        }
        if (!$this->isValidId($compositionId)) {
            return api_respond_validation_error(['composition_id' => 'Invalid composition id']);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;

        // Cek product ada dan milik shop yang benar
        $product = $this->model->where('shop_id', $shopId)->find($id);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }

        // Cek product composition ada
        $existing = $this->productCompositionModel
            ->where('product_id', $id)
            ->where('composition_id', $compositionId)
            ->first();

        if (!$existing) {
            return api_respond_not_found('Product composition not found');
        }

        if (!$this->productCompositionModel->delete($existing['id'])) {
            return api_respond_server_error('Failed to remove composition from product');
        }

        $compositions = $this->productCompositionModel->getCompositionsByProduct($id);
        return api_respond_success(['compositions' => $compositions], 'Composition removed from product');
    }

    // GET /api/products/{id}/compositions
    public function getCompositions($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid product id']);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;

        // Cek product ada dan milik shop yang benar
        $product = $this->model->where('shop_id', $shopId)->find($id);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }

        $compositions = $this->productCompositionModel->getCompositionsByProduct($id);
        return api_respond_success($compositions, 'Product compositions');
    }
}
