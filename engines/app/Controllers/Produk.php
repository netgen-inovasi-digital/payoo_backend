<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MyModel;

class Produk extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new MyModel('produk');
    }

    // GET /api/produk
    public function index()
    {
        $data = $this->model->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data
        ]);
    }

    // GET /api/produk/{id}
    public function show($id = null)
    {
        $data = $this->model->getDataById('kodeProduk', $id);
        
        if ($data) {
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $data
            ]);
        }
        else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ])->setStatusCode(404);
        }
    }

    // POST /api/produk
    public function create()
    {
        $data = array(
            'kodeProduk' => $this->request->getPost('kode'),
            'idKategori' => $this->request->getPost('kategori'),
            'namaProduk' => $this->request->getPost('nama'),
            'hargaJual' => $this->request->getPost('hargajual'),
        );
       
        $result = $this->model->insertData($data);
        if($result) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Data berhasil disimpan'
            ])->setStatusCode(201);
        } 
        else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data gagal disimpan'
            ])->setStatusCode(500);
        }
    }

    // PUT /api/produk/{id}
    public function update($id = null)
    {
        $input = $this->request->getRawInput();
        $data = [
            'kodeProduk' => $input['kode'] ?? null,
            'idKategori' => $input['kategori'] ?? null,
            'namaProduk' => $input['nama'] ?? null,
            'hargaJual' => $input['hargajual'] ?? null,
        ];

        $existing = $this->model->getDataById('kodeProduk', $id);
        if (!$existing) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ])->setStatusCode(404);
        }

        $result = $this->model->updateData($data, 'kodeProduk', $id);
        if($result) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Data berhasil disimpan'
            ])->setStatusCode(201);
        } 
        else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data gagal disimpan'
            ])->setStatusCode(500);
        }
    }

    // DELETE /api/produk/{id}
    public function delete($id = null)
    {
        $existing = $this->model->getDataById('kodeProduk', $id);
        if (!$existing) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ])->setStatusCode(404);
        }

        $result = $this->model->deleteData('kodeProduk', $id);
        if($result) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Data berhasil dihapus'
            ])->setStatusCode(201);
        } 
        else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data gagal dihapus'
            ])->setStatusCode(500);
        }
    }
}