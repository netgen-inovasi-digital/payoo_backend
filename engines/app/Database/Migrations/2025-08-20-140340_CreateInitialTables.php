<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialTables extends Migration
{
    public function up()
    {
        // Users
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'password'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'photo'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'role'        => ['type' => 'ENUM', 'constraint' => ['owner', 'employee', 'user'], 'default' => 'user'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('users');

        // Shops
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'user_id'    => ['type' => 'INT'],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'address'    => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['mandiri', 'perusahaan'], 'default' => 'mandiri'],
            'province'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'city'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shops');

        // Categories
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('categories');

        // Compositions
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'shop_id'       => ['type' => 'INT'],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'cost_price'    => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'selling_price' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'unit'          => ['type' => 'ENUM', 'constraint' => ['pcs', 'gr', 'lembar']],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compositions');

        // Products
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'shop_id'       => ['type' => 'INT'],
            'category_id'   => ['type' => 'INT'],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'photo'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cost_price'    => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'selling_price' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('products');

        // Product Compositions
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'product_id'     => ['type' => 'INT'],
            'composition_id' => ['type' => 'INT'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['product_id', 'composition_id']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('composition_id', 'compositions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('product_compositions');

        // Carts
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'user_id'    => ['type' => 'INT'],
            'product_id' => ['type' => 'INT'],
            'quantity'   => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('carts');

        // Orders
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'user_id'    => ['type' => 'INT'],
            'shop_id'    => ['type' => 'INT'],
            'status'     => ['type' => 'ENUM', 'constraint' => ['pending', 'paid', 'shipped', 'completed', 'cancelled'], 'default' => 'pending'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'total'      => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('orders');

        // Order Items
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'order_id'   => ['type' => 'INT'],
            'product_id' => ['type' => 'INT'],
            'quantity'   => ['type' => 'INT'],
            'price'      => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('order_items');

        // Stocks
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'composition_id' => ['type' => 'INT'],
            'quantity'       => ['type' => 'INT'],
            'type'           => ['type' => 'ENUM', 'constraint' => ['in', 'out']],
            'date'           => ['type' => 'DATETIME'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('composition_id', 'compositions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stocks');
    }

    public function down()
    {
        $this->forge->dropTable('stocks');
        $this->forge->dropTable('order_items');
        $this->forge->dropTable('orders');
        $this->forge->dropTable('carts');
        $this->forge->dropTable('product_compositions');
        $this->forge->dropTable('products');
        $this->forge->dropTable('compositions');
        $this->forge->dropTable('categories');
        $this->forge->dropTable('shops');
        $this->forge->dropTable('users');
    }
}
