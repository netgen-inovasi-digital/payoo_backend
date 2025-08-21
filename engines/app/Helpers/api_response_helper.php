<?php

if (! function_exists('api_response_array')) {
    /**
     * Build standardized API response array
     *
     * @param string $status 'success'|'error'
     * @param string|null $message
     * @param mixed|null $data
     * @param array|null $meta
     * @return array
     */
    function api_response_array(string $status = 'success', ?string $message = null, $data = null, ?array $meta = null): array
    {
        $res = [
            'status' => $status,
        ];

        if ($message !== null) $res['message'] = $message;
        if ($data !== null)    $res['data'] = $data;
        if ($meta !== null)    $res['meta'] = $meta;

        return $res;
    }
}

if (! function_exists('api_respond')) {
    /**
     * Send standardized JSON response using CI Response service
     *
     * @param array $payload
     * @param int $httpCode
     * @return \CodeIgniter\HTTP\Response
     */
    function api_respond(array $payload, int $httpCode = 200)
    {
        // service('response') tersedia di CI4
        return service('response')
            ->setJSON($payload)
            ->setStatusCode($httpCode);
    }
}

if (! function_exists('api_respond_success')) {
    function api_respond_success($data = null, string $message = 'Success', int $httpCode = 200, ?array $meta = null)
    {
        $payload = api_response_array('success', $message, $data, $meta);
        return api_respond($payload, $httpCode);
    }
}

if (! function_exists('api_respond_created')) {
    function api_respond_created($data = null, string $message = 'Created', int $httpCode = 201, ?array $meta = null)
    {
        $payload = api_response_array('success', $message, $data, $meta);
        return api_respond($payload, $httpCode);
    }
}

if (! function_exists('api_respond_error')) {
    /**
     * Generic error responder
     *
     * @param string $message
     * @param int $httpCode
     * @param mixed|null $errors    // validation errors or details
     * @return \CodeIgniter\HTTP\Response
     */
    function api_respond_error(string $message = 'Error', int $httpCode = 400, $errors = null)
    {
        $payload = api_response_array('error', $message);
        if ($errors !== null) $payload['errors'] = $errors;
        return api_respond($payload, $httpCode);
    }
}

if (! function_exists('api_respond_validation_error')) {
    function api_respond_validation_error($errors = null, string $message = 'Validation failed')
    {
        return api_respond_error($message, 422, $errors);
    }
}

if (! function_exists('api_respond_not_found')) {
    function api_respond_not_found(string $message = 'Resource not found')
    {
        return api_respond_error($message, 404);
    }
}

if (! function_exists('api_respond_unauthorized')) {
    function api_respond_unauthorized(string $message = 'Unauthorized')
    {
        return api_respond_error($message, 401);
    }
}

if (! function_exists('api_respond_forbidden')) {
    function api_respond_forbidden(string $message = 'Forbidden')
    {
        return api_respond_error($message, 403);
    }
}

if (! function_exists('api_respond_server_error')) {
    function api_respond_server_error(string $message = 'Internal server error')
    {
        return api_respond_error($message, 500);
    }
}

if (! function_exists('api_respond_paginated')) {
    /**
     * Standardized paginated response
     *
     * $pagination array should include at least: current_page, per_page, total, last_page
     * Example meta: ['current_page'=>1, 'per_page'=>10, 'total'=>100, 'last_page'=>10]
     *
     * @param array $items
     * @param array $pagination
     * @param string $message
     * @param int $httpCode
     * @return \CodeIgniter\HTTP\Response
     */
    function api_respond_paginated(array $items, array $pagination, string $message = 'Success', int $httpCode = 200)
    {
        $meta = [
            'pagination' => $pagination,
        ];

        return api_respond_success($items, $message, $httpCode, $meta);
    }
}

// Optional helper: convenience to build pagination array from CodeIgniter pager or custom
if (! function_exists('api_build_pagination')) {
    /**
     * Build pagination meta array
     *
     * @param int $currentPage
     * @param int $perPage
     * @param int $total
     * @return array
     */
    function api_build_pagination(int $currentPage, int $perPage, int $total): array
    {
        $lastPage = (int) ceil($total / max(1, $perPage));
        return [
            'current_page' => $currentPage,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => $lastPage,
        ];
    }
}

// End of helper