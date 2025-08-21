<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Admin',
                'email' => 'admin@payoo.com',
                'phone' => '081234567890',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'photo' => null,
                'role' => 'owner',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Employee Test',
                'email' => 'employee@payoo.com',
                'phone' => '081234567891',
                'password' => password_hash('employee123', PASSWORD_DEFAULT),
                'photo' => null,
                'role' => 'employee',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'User Test',
                'email' => 'user@payoo.com',
                'phone' => '081234567892',
                'password' => password_hash('user123', PASSWORD_DEFAULT),
                'photo' => null,
                'role' => 'user',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Insert data
        $this->db->table('users')->insertBatch($data);
    }
}
