<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers',
    'filter' => 'bearerAuth'], function ($routes) {
		
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

