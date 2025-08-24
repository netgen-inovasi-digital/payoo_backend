<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CompositionModel;
use App\Models\ShopModel;
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

        $items = $this->model->where('shop_id', $shopId)->orderBy('id', 'DESC')->findAll();

        // Ambil total stok per composition (in - out) dalam 1 query
        $db = Database::connect();
        $stockRows = [];
        if (!empty($items)) {
            $compositionIds = array_column($items, 'id');
            $stockRows = $db->table('stocks')
                ->select('composition_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
                ->whereIn('composition_id', $compositionIds)
                ->groupBy('composition_id')
                ->get()
                ->getResultArray();
        }
        $stockMap = [];
        foreach ($stockRows as $r) {
            $stockMap[$r['composition_id']] = (int) $r['stock_total'];
        }
        foreach ($items as &$row) {
            $row['stock'] = $stockMap[$row['id']] ?? 0;
        }
        unset($row);
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
        $item = $this->model->where('shop_id', $shopId)->find($id);
        if (!$item) {
            return api_respond_not_found('Composition not found');
        }
        // Hitung stok untuk composition ini
        $db = Database::connect();
        $stock = $db->table('stocks')
            ->select('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
            ->where('composition_id', $id)
            ->get()
            ->getRowArray();
        $item['stock'] = isset($stock['stock_total']) ? (int) $stock['stock_total'] : 0;
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
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
            'unit'          => $json->unit ?? null,
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
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
            'unit'          => isset($json->unit) ? $json->unit : $existing['unit'],
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
}
