<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Initialize the database connection (improved error handling)
try {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'],
        'database' => $_ENV['DB_DATABASE'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to connect to database', 'details' => $e->getMessage()]);
    http_response_code(500);
    exit;
}

// Get authorization code from query parameters
$authorization_code = $_GET['code'] ?? '';

if (!$authorization_code) {
    echo json_encode(['error' => 'Authorization code not provided']);
    http_response_code(400);
    exit;
}

// Get QuickBooks settings from environment variables
$clientId = $_ENV['QB_CLIENT_ID'];
$clientSecret = $_ENV['QB_CLIENT_SECRET'];
$redirectUri = $_ENV['QB_REDIRECT_URI'];
$tokenUrl = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer'; // Ensure this is correct



$client = new Client();

// Prepare data for token request
$data = [
    'grant_type' => 'authorization_code',
    'code' => $authorization_code,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'client_secret' => $clientSecret, 
];

// Request access token with error handling
try {
    $response = $client->post($tokenUrl, [
        'form_params' => $data,
    ]);

    if ($response->getStatusCode() == 200) {
        $tokens = json_decode($response->getBody(), true);
        $access_token = $tokens['access_token'];
        $refresh_token = $tokens['refresh_token'];
        $expires_in = $tokens['expires_in'];
        
        // Calculate access token expiry time
        // Create a DateTime object set to the current time in UTC
        $expiryDateTime = new DateTime('now', new DateTimeZone('UTC'));

        // Add the value of expires_in to the DateTime object
        $expiryDateTime->add(new DateInterval('PT' . $expires_in . 'S'));

        // Add 3 hours to the DateTime object
        $expiryDateTime->add(new DateInterval('PT3H'));

        // Format the DateTime object as a string in the desired format
        $access_token_expiry = $expiryDateTime->format('Y-m-d H:i:s');
        // Store tokens in the database
        Capsule::table('tokens')->insert([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'access_token_expiry' => $access_token_expiry,
        ]);

        echo json_encode(['message' => 'Tokens stored successfully']);
        http_response_code(200);
    } else {
        echo json_encode(['error' => 'Failed to obtain tokens', 'details' => $response->getBody()->getContents()]);
        http_response_code(500);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to obtain tokens', 'details' => $e->getMessage()]);
    http_response_code(500);
}
