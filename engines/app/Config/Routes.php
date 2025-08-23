<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Public Auth Routes (tanpa filter)
$routes->group('api/auth', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->post('register', 'Auth::register');   // POST /api/auth/register
    $routes->post('login', 'Auth::login');         // POST /api/auth/login
});

// Protected Routes (dengan filter bearerAuth)
$routes->group('api', ['namespace' => 'App\Controllers', 'filter' => 'bearerAuth'], function ($routes) {
    
    // Auth Protected Routes
    $routes->get('account/profile', 'Auth::profile'); // GET /api/auth/profile
    
    // Routes untuk Produk
    $routes->get('produk', 'Produk::index');       // GET /api/produk
    $routes->get('produk/(:segment)', 'Produk::show/$1'); // GET /api/produk/{id}
    $routes->post('produk', 'Produk::create');     // POST /api/produk
    $routes->put('produk/(:segment)', 'Produk::update/$1'); // PUT /api/produk/{id}
    $routes->delete('produk/(:segment)', 'Produk::delete/$1'); // DELETE /api/produk/{id}

    // Routes untuk Category (baru)
    $routes->get('categories', 'Category::index');                 // GET /api/categories
    $routes->get('categories/(:segment)', 'Category::show/$1');    // GET /api/categories/{id}
    $routes->post('categories', 'Category::create');               // POST /api/categories
    $routes->put('categories/(:segment)', 'Category::update/$1');  // PUT /api/categories/{id}
    $routes->delete('categories/(:segment)', 'Category::delete/$1'); // DELETE /api/categories/{id}

    // Routes untuk Shops
    $routes->get('shops', 'Shop::index');                 // GET /api/shops
    $routes->get('shops/(:segment)', 'Shop::show/$1');    // GET /api/shops/{id}
    $routes->post('shops', 'Shop::create');               // POST /api/shops
    $routes->put('shops/(:segment)', 'Shop::update/$1');  // PUT /api/shops/{id}
    $routes->delete('shops/(:segment)', 'Shop::delete/$1'); // DELETE /api/shops/{id}
});

