<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeleteFields extends Migration
{
    public function up()
    {
        // Add deleted_at to shops table
        $this->forge->addColumn('shops', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at']
        ]);

        // Add deleted_at to categories table
        $this->forge->addColumn('categories', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at']
        ]);

        // Add deleted_at to products table
        $this->forge->addColumn('products', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at']
        ]);

        // Add deleted_at to orders table
        $this->forge->addColumn('orders', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at']
        ]);

        // Note: product_compositions, order_items, dan stocks tidak perlu soft delete
        // karena mereka adalah data relasional/transaksional yang sebaiknya tetap ada
        // untuk keperluan audit dan integritas data
    }

    public function down()
    {
        // Remove deleted_at columns
        $this->forge->dropColumn('users', 'deleted_at');
        $this->forge->dropColumn('shops', 'deleted_at');
        $this->forge->dropColumn('categories', 'deleted_at');
        $this->forge->dropColumn('products', 'deleted_at');
        $this->forge->dropColumn('orders', 'deleted_at');
    }
}