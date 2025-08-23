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
    // $routes->get('produk', 'Produk::index');       // GET /api/produk
    // $routes->get('produk/(:segment)', 'Produk::show/$1'); // GET /api/produk/{id}
    // $routes->post('produk', 'Produk::create');     // POST /api/produk
    // $routes->put('produk/(:segment)', 'Produk::update/$1'); // PUT /api/produk/{id}
    // $routes->delete('produk/(:segment)', 'Produk::delete/$1'); // DELETE /api/produk/{id}

    $routes->get('products', 'Product::index');                 // GET /api/products
    $routes->get('products/(:segment)', 'Product::show/$1');    // GET /api/products/{id}
    $routes->post('products', 'Product::create');               // POST /api/products
    $routes->put('products/(:segment)', 'Product::update/$1');  // PUT /api/products/{id}
    $routes->delete('products/(:segment)', 'Product::delete/$1'); // DELETE /api/products/{id}

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

    // Routes untuk Compositions
    $routes->get('compositions', 'Composition::index');                 // GET /api/compositions
    $routes->get('compositions/(:segment)', 'Composition::show/$1');    // GET /api/compositions/{id}
    $routes->post('compositions', 'Composition::create');               // POST /api/compositions
    $routes->put('compositions/(:segment)', 'Composition::update/$1');  // PUT /api/compositions/{id}
    $routes->delete('compositions/(:segment)', 'Composition::delete/$1'); // DELETE /api/compositions/{id}

    // Routes untuk Stocks (Create & Read only)
    $routes->get('stocks/(:segment)', 'Stock::show/$1');    // GET /api/stocks/{composition_id}
    $routes->post('stocks', 'Stock::create');               // POST /api/stocks
});
