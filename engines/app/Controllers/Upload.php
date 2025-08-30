<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class Upload extends BaseController
{
    // POST /api/upload  (multipart/form-data, field: file)
    public function create()
    {
        // Ambil folder hanya dari query string (?folder=...) bukan dari form field
        $folderInput = (string) ($this->request->getGet('folder') ?? '');
        if ($folderInput !== '') {
            $folderInput = str_replace('\\', '/', $folderInput);
            // Keep only allowed chars (letters, numbers, /, -, _)
            $folderInput = preg_replace('~[^a-zA-Z0-9/_-]+~', '', $folderInput);
            $folderInput = trim($folderInput, '/');
            // Prevent traversal
            if (str_contains($folderInput, '..')) {
                return api_respond_validation_error(['folder' => 'Invalid folder path']);
            }
            // Limit depth to 3 segments
            if ($folderInput !== '' && count(explode('/', $folderInput)) > 3) {
                return api_respond_validation_error(['folder' => 'Folder depth too deep (max 3 levels)']);
            }
        }

        // Ensure request has a file
        $uploadedFile = $this->request->getFile('file');
        if (!$uploadedFile) {
            return api_respond_error('No file uploaded (field name: file)', 400);
        }

        // Validate basic upload status
        if (!$uploadedFile->isValid()) {
            return api_respond_error('Upload error: ' . $uploadedFile->getErrorString(), 400);
        }

        // Limit size (e.g., 5MB)
        $maxBytes = 5 * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxBytes) {
            return api_respond_error('File too large. Max 5MB', 400);
        }

        // Allowed mime / extensions (images + pdf as example)
        $allowedMime  = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf'
        ];
        $allowedExt   = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        $mimeType     = $uploadedFile->getMimeType();
        $extension    = strtolower($uploadedFile->getExtension());
        if (!in_array($mimeType, $allowedMime, true) || !in_array($extension, $allowedExt, true)) {
            return api_respond_error('Invalid file type', 400);
        }

        // Destination directory (writable/uploads/[folder/]) tanpa tahun & bulan
        $subDir = $folderInput; // bisa kosong
        $destPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . ($subDir !== '' ? $subDir : '');
        if (!is_dir($destPath) && !mkdir($destPath, 0775, true) && !is_dir($destPath)) {
            return api_respond_server_error('Failed to create upload directory');
        }

        // Generate safe random filename preserving extension
        $newName = bin2hex(random_bytes(16)) . '.' . $extension;
        try {
            $uploadedFile->move($destPath, $newName, true);
        } catch (\Throwable $t) {
            return api_respond_server_error('Failed to move uploaded file');
        }

        // Build relative + absolute (if base_url configured)
        $relativePath = 'uploads/' . ($subDir !== '' ? str_replace('\\', '/', $subDir . '/') : '') . $newName;
        $absoluteUrl  = function_exists('base_url') ? base_url($relativePath) : $relativePath;

        $responseData = [
            'original_name' => $uploadedFile->getClientName(),
            'stored_name'   => $newName,
            'mime'          => $mimeType,
            'size'          => $uploadedFile->getSize(),
            'folder'        => $folderInput !== '' ? $folderInput : null,
            'path'          => $relativePath,
            'url'           => $absoluteUrl,
        ];

        return api_respond_created($responseData, 'File uploaded');
    }

    // GET /uploads/{...} (public route) - limited to one optional folder segment per current routes config
    public function serve($seg1 = null, $seg2 = null)
    {
        // Determine path relative inside uploads
        $parts = array_filter([$seg1, $seg2], fn($v) => $v !== null);
        if (empty($parts)) {
            return api_respond_not_found('File not specified');
        }
        // Build relative path and sanitize
        $relative = implode('/', $parts);
        if (str_contains($relative, '..')) {
            return api_respond_error('Invalid path', 400);
        }
        // Only allow allowed extensions
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return api_respond_error('Disallowed file type', 400);
        }
        $fullPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($fullPath)) {
            return api_respond_not_found('File not found');
        }
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return $this->response->setHeader('Content-Type', $mime)->setBody(file_get_contents($fullPath));
    }
}
