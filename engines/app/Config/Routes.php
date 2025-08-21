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

    // Routes untuk Kategori
    $routes->get('kategori', 'Kategori::index');       // GET /api/kategori
    $routes->get('kategori/(:segment)', 'Kategori::show/$1'); // GET /api/kategori/{id}
    $routes->post('kategori', 'Kategori::create');     // POST /api/kategori
    $routes->put('kategori/(:segment)', 'Kategori::update/$1'); // PUT /api/kategori/{id}
    $routes->delete('kategori/(:segment)', 'Kategori::delete/$1'); // DELETE /api/kategori/{id}
});

