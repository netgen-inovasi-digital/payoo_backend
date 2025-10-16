<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;

class VueApp extends Controller
{
    public function index()
    {
        return $this->serveVueApp();
    }

    public function catchAll()
    {
        return $this->serveVueApp();
    }

    public function serveAsset($file)
    {
        $filePath = FCPATH . 'app/assets/' . $file;
        
        if (!file_exists($filePath)) {
            throw new PageNotFoundException();
        }

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'map' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        if (isset($mimeTypes[$ext])) {
            $this->response->setContentType($mimeTypes[$ext]);
        }

        // Set cache headers for static assets
        $this->response->setHeader('Cache-Control', 'public, max-age=31536000');
        $this->response->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        return $this->response->setBody(file_get_contents($filePath));
    }

    public function serveImage($path = null)
    {
        // Handle nested image paths
        $segments = $this->request->getUri()->getSegments();
        $imagePath = '';
        
        // Remove 'app' and 'images' from segments
        if (count($segments) >= 3) {
            $imagePath = implode('/', array_slice($segments, 2));
        } else {
            $imagePath = $path;
        }
        
        $filePath = FCPATH . 'app/images/' . $imagePath;
        
        if (!file_exists($filePath)) {
            throw new PageNotFoundException();
        }

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webp' => 'image/webp'
        ];
        
        if (isset($mimeTypes[$ext])) {
            $this->response->setContentType($mimeTypes[$ext]);
        }

        // Set cache headers for images
        $this->response->setHeader('Cache-Control', 'public, max-age=31536000');
        $this->response->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        return $this->response->setBody(file_get_contents($filePath));
    }

    public function serveFavicon()
    {
        $filePath = FCPATH . 'app/favicon.ico';
        
        if (!file_exists($filePath)) {
            throw new PageNotFoundException();
        }

        $this->response->setContentType('image/x-icon');
        $this->response->setHeader('Cache-Control', 'public, max-age=31536000');
        
        return $this->response->setBody(file_get_contents($filePath));
    }

    private function serveVueApp()
    {
        $filePath = FCPATH . 'app/index.html';
        
        if (!file_exists($filePath)) {
            throw new PageNotFoundException('Vue app not found');
        }

        $this->response->setContentType('text/html');
        return $this->response->setBody(file_get_contents($filePath));
    }
}