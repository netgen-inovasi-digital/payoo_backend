<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ShopDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Shop for user_id 1
        $shopData = [
            'user_id'    => 1,
            'name'       => 'Toko Berkah Jaya',
            'email'      => 'tokoberkah@example.com',
            'address'    => 'Jl. Raya Surabaya No. 123, Surabaya',
            'type'       => 'mandiri',
            'province'   => 'Jawa Timur',
            'city'       => 'Surabaya',
            'phone'      => '031-1234567',
            'photo'      => 'shop_berkah.jpg',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('shops')->insert($shopData);
        $shopId = $this->db->insertID();
        
        // 2. Create 2 Categories
        $categories = [
            [
                'shop_id'    => $shopId,
                'name'       => 'Makanan',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'    => $shopId,
                'name'       => 'Minuman',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('categories')->insertBatch($categories);
        
        // Get category IDs
        $makananId = $this->db->query("SELECT id FROM categories WHERE shop_id = {$shopId} AND name = 'Makanan'")->getRow()->id;
        $minumanId = $this->db->query("SELECT id FROM categories WHERE shop_id = {$shopId} AND name = 'Minuman'")->getRow()->id;
        
        // 3. Create 5 Compositions (type = 'composition')
        $compositions = [
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Tepung Terigu',
                'description'   => 'Tepung terigu berkualitas tinggi untuk baking',
                'photo'         => 'tepung_terigu.jpg',
                'type'          => 'composition',
                'unit'          => 'gr',
                'cost_price'    => 15000,
                'selling_price' => 18000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Gula Pasir',
                'description'   => 'Gula pasir putih halus',
                'photo'         => 'gula_pasir.jpg',
                'type'          => 'composition',
                'unit'          => 'gr',
                'cost_price'    => 12000,
                'selling_price' => 15000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Mentega',
                'description'   => 'Mentega berkualitas untuk baking',
                'photo'         => 'mentega.jpg',
                'type'          => 'composition',
                'unit'          => 'gr',
                'cost_price'    => 25000,
                'selling_price' => 30000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $minumanId,
                'name'          => 'Kopi Bubuk',
                'description'   => 'Kopi bubuk robusta pilihan',
                'photo'         => 'kopi_bubuk.jpg',
                'type'          => 'composition',
                'unit'          => 'gr',
                'cost_price'    => 45000,
                'selling_price' => 55000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $minumanId,
                'name'          => 'Susu Cair',
                'description'   => 'Susu segar berkualitas tinggi',
                'photo'         => 'susu_cair.jpg',
                'type'          => 'composition',
                'unit'          => 'lembar',
                'cost_price'    => 8000,
                'selling_price' => 10000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('products')->insertBatch($compositions);
        
        // Get composition IDs
        $compositionIds = [];
        $compositionQuery = $this->db->query("SELECT id, name FROM products WHERE shop_id = {$shopId} AND type = 'composition'");
        foreach ($compositionQuery->getResult() as $comp) {
            $compositionIds[$comp->name] = $comp->id;
        }
        
        // 4. Create 5 Products (type = 'product')
        $products = [
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Roti Tawar',
                'description'   => 'Roti tawar lembut dan segar',
                'photo'         => 'roti_tawar.jpg',
                'type'          => 'product',
                'unit'          => 'pcs',
                'cost_price'    => 8000,
                'selling_price' => 12000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Kue Donat',
                'description'   => 'Donat manis dengan berbagai topping',
                'photo'         => 'kue_donat.jpg',
                'type'          => 'product',
                'unit'          => 'pcs',
                'cost_price'    => 3000,
                'selling_price' => 5000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $makananId,
                'name'          => 'Cookies Chocolate',
                'description'   => 'Cookies renyah dengan cokelat chip',
                'photo'         => 'cookies_chocolate.jpg',
                'type'          => 'product',
                'unit'          => 'pcs',
                'cost_price'    => 4000,
                'selling_price' => 7000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $minumanId,
                'name'          => 'Kopi Latte',
                'description'   => 'Kopi latte dengan susu premium',
                'photo'         => 'kopi_latte.jpg',
                'type'          => 'product',
                'unit'          => 'pcs',
                'cost_price'    => 8000,
                'selling_price' => 15000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ],
            [
                'shop_id'       => $shopId,
                'category_id'   => $minumanId,
                'name'          => 'Cappuccino',
                'description'   => 'Cappuccino dengan foam yang sempurna',
                'photo'         => 'cappuccino.jpg',
                'type'          => 'product',
                'unit'          => 'pcs',
                'cost_price'    => 9000,
                'selling_price' => 17000,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('products')->insertBatch($products);
        
        // Get product IDs
        $productIds = [];
        $productQuery = $this->db->query("SELECT id, name FROM products WHERE shop_id = {$shopId} AND type = 'product'");
        foreach ($productQuery->getResult() as $prod) {
            $productIds[$prod->name] = $prod->id;
        }
        
        // 5. Create Product Compositions (BOM/Recipe for products)
        $productCompositions = [
            // Roti Tawar composition
            [
                'product_id'     => $productIds['Roti Tawar'],
                'composition_id' => $compositionIds['Tepung Terigu'],
                'quantity'       => 500,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Roti Tawar'],
                'composition_id' => $compositionIds['Gula Pasir'],
                'quantity'       => 50,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Roti Tawar'],
                'composition_id' => $compositionIds['Mentega'],
                'quantity'       => 100,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            
            // Kue Donat composition
            [
                'product_id'     => $productIds['Kue Donat'],
                'composition_id' => $compositionIds['Tepung Terigu'],
                'quantity'       => 200,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Kue Donat'],
                'composition_id' => $compositionIds['Gula Pasir'],
                'quantity'       => 75,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            
            // Cookies Chocolate composition
            [
                'product_id'     => $productIds['Cookies Chocolate'],
                'composition_id' => $compositionIds['Tepung Terigu'],
                'quantity'       => 150,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Cookies Chocolate'],
                'composition_id' => $compositionIds['Mentega'],
                'quantity'       => 50,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            
            // Kopi Latte composition
            [
                'product_id'     => $productIds['Kopi Latte'],
                'composition_id' => $compositionIds['Kopi Bubuk'],
                'quantity'       => 20,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Kopi Latte'],
                'composition_id' => $compositionIds['Susu Cair'],
                'quantity'       => 1,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            
            // Cappuccino composition
            [
                'product_id'     => $productIds['Cappuccino'],
                'composition_id' => $compositionIds['Kopi Bubuk'],
                'quantity'       => 25,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ],
            [
                'product_id'     => $productIds['Cappuccino'],
                'composition_id' => $compositionIds['Susu Cair'],
                'quantity'       => 1,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('product_compositions')->insertBatch($productCompositions);
        
        // 6. Create Initial Stock for Compositions
        $initialStocks = [
            [
                'product_id' => $compositionIds['Tepung Terigu'],
                'quantity'   => 5000,
                'type'       => 'in',
                'buy_price'  => 15000,
                'notes'      => 'Initial stock - Tepung Terigu',
                'date'       => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'product_id' => $compositionIds['Gula Pasir'],
                'quantity'   => 3000,
                'type'       => 'in',
                'buy_price'  => 12000,
                'notes'      => 'Initial stock - Gula Pasir',
                'date'       => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'product_id' => $compositionIds['Mentega'],
                'quantity'   => 2000,
                'type'       => 'in',
                'buy_price'  => 25000,
                'notes'      => 'Initial stock - Mentega',
                'date'       => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'product_id' => $compositionIds['Kopi Bubuk'],
                'quantity'   => 1500,
                'type'       => 'in',
                'buy_price'  => 45000,
                'notes'      => 'Initial stock - Kopi Bubuk',
                'date'       => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'product_id' => $compositionIds['Susu Cair'],
                'quantity'   => 100,
                'type'       => 'in',
                'buy_price'  => 8000,
                'notes'      => 'Initial stock - Susu Cair',
                'date'       => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->db->table('stocks')->insertBatch($initialStocks);
    }
}