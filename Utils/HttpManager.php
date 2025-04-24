<?php

namespace LBPool\Utils;


class HttpManager {

    // SUCCESS
    public static function handle_200($message = NULL, $payload = NULL, bool $skipUserContext = false) {
        http_response_code(200);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        if ($skipUserContext) {
            echo json_encode([
                'message' => $message ?? 'Query executed successfully'
            ]);
        } else {
            $userContext = UserContext::getInstance();
            echo json_encode([
                'message' => $message ?? 'Query executed successfully',
                'access_token' => $userContext->getAccessToken() ?? '',
                'refresh_token' => $userContext->getRefreshToken() ?? '',
			  	'payload' => $payload ?? ''
            ]);
        }
        exit;
    }

    // NO CONTENT
    public static function handle_204($message = NULL) {
        http_response_code(204);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'info' => 'No content',
            'message' => $message ?? 'Query returned no content'
        ]);
        exit;
    }

    // INVALID PARAMETERS
    public static function handle_400($message = NULL, $remaining_attempts = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(400);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Invalid parameters',
            'message' => $message ?? 'The parameters used in this request are too few, too many, or unrecognized',
            'attemps' => $remaining_attempts ?? ''
        ]);
        exit;
    }

    // UNAUTHORIZED
    public static function handle_401($message = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(401);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => $message ?? 'You do not have permission to log in this server'
        ]);
        exit;
    }

    // FORBIDDEN
    public static function handle_403($message = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(403);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Forbidden',
            'message' => $message ?? 'You do not have access to this ressource'
        ]);
        exit;
    }

    // NOT FOUND
    public static function handle_404($message = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(404);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Not found',
            'message' => $message ?? 'The ressource specified does not exist on this server'
        ]);
        exit;
    }

    // METHOD NOT ALLOWED
    public static function handle_405($message = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(405);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Method not allowed',
            'message' => $message ?? 'The HTTP method used is not allowed for this endpoint'
        ]);
        exit;
    }

    // INTERNAL SERVER ERROR
    public static function handle_500($message = NULL) {
        if ($message != NULL) error_log($message);
        http_response_code(500);
	  	header('Content-Type: application/json');
	  	header('Access-Control-Allow-Origin: *');
	  	header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	  	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit;
		}
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $message ?? 'Unable to proceed with the request'
        ]);
        exit;
    }

}