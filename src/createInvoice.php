<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$realmId = $_ENV['REALM_ID'];
$baseURL = $_ENV['QUICKBOOKS_API_BASE_URL'];


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

function getValidAccessToken() {

    $token = Capsule::table('tokens')->first();
  
    if (empty($token) || strtotime($token->access_token_expiry) < time()) {
      // Access token is empty or expired, refresh it
      $newToken = refreshAccessToken(); // Call a new function to refresh the token
      return $newToken;
    } else {
        
      return $token->access_token; // Return existing valid token
    }
}

// echo getValidAccessToken();

// $token = Capsule::table('tokens')->first();
  
// if (empty($token) || strtotime($token->access_token_expiry) < time()) {
//   // Access token is empty or expired, refresh it
//   $newToken = refreshAccessToken(); // Call a new function to refresh the token
//   echo $newToken;
// } else {
    
//   echo($token->access_token);
// }


// Function to get a valid access token
function refreshAccessToken() {
    $token = Capsule::table('tokens')->first();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No tokens found']);
        exit;
    }

    date_default_timezone_set("Africa/Nairobi");
    $currentDateTime = Date('Y-m-d H:i:s');
    $accessTokenExpiry = Date('Y-m-d H:i:s', strtotime($token->access_token_expiry));

    echo($currentDateTime);
    echo(" - ".$accessTokenExpiry);
    
    #echo json_encode($token->access_token_expiry);

    if ($currentDateTime >= $accessTokenExpiry) {
        
        $clientId = $_ENV['QB_CLIENT_ID'];
        $clientSecret = $_ENV['QB_CLIENT_SECRET'];
        $refreshToken = $token->refresh_token;
        $tokenUrl =  $_ENV['TOKEN_URL']; 

        $authHeader = base64_encode("$clientId:$clientSecret");
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . $authHeader,
        ];

        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $client = new Client();
        try {
            $response = $client->post($tokenUrl, [
                'headers' => $headers,
                'form_params' => $data,
            ]);

            if ($response->getStatusCode() == 200) {
                $tokens = json_decode($response->getBody(), true);
                $access_token = $tokens['access_token'];
                $refresh_token = $tokens['refresh_token'] ?? $refreshToken;
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
                // $access_token_expiry= $currentDateTime->add(new DateInterval('PT' . $expires_in . 'S')); 

                Capsule::table('tokens')->update([
                    'access_token' => $access_token,
                    'refresh_token' => $refresh_token,
                    'access_token_expiry' => $access_token_expiry,
                ]);

            

                return $access_token;
            } else {
                echo json_encode(['error' => 'Failed to refresh access token', 'details' => $response->getBody()->getContents()]);
                http_response_code($response->getStatusCode());
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to refresh access token', 'details' => $e->getMessage()]);
            http_response_code(500);
            exit;
        }
    }
    

    return $token->access_token;

    
}


function checkCustomerExists( $customerName) {
    // Retrieve a valid access token using getValidAccessToken

    $realmId = $_ENV['REALM_ID'];
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        echo("No access tokens found");
      return false;
    }
  
    $url = "https://sandbox-quickbooks.api.intuit.com/v3/company/{$realmId}/query";
    
    $query = "select * from Customer where DisplayName='{$customerName}'";

    $headers = [
      'Authorization' => 'Bearer ' . $accessToken,
      'Content-Type' => 'application/text',
    ];
  
    $client = new Client();
  
    try {
        $response = $client->post($url, [
          'headers' => $headers,
          'body' => $query,
        ]);
    
        if ($response->getStatusCode() == 200 && $response->getBody()) {
            // Parse the XML response
            $xmlData = simplexml_load_string($response->getBody());

            // Check for customer existence based on the XML structure
            if (isset($xmlData->QueryResponse->Customer)) {
                $customer = $xmlData->QueryResponse->Customer;
                return (string) $customer->Id; // Return customer ID
                print_r((string) $customer->Id);
            } else {
                return false;
            }
          } else {
            // Handle non-200 status code (e.g., log the error)
            echo("Error: " . $response->getStatusCode());
            return false;
        }
      } catch (Exception $e) {
        // Handle exceptions during the request (e.g., log the error)
        return false;
      }
}
///////////////////////////////////For debugging chek customer exists function//////////////////////////////////
  //Process the request
//   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     try {
//       // Read and decode JSON request data
//       $data = json_decode(file_get_contents('php://input'), true);
  
//       // Validate customerName presence
//       if (!isset($data['customerName'])) {
//         throw new Exception('Missing required field: customerName');
//       }
  
//       $customerName = $data['customerName'];
  
//       // Check customer existence
//       $customerExists = checkCustomerExists($customerName);
  
//       // Respond with success or error
//       $response = [
//         'customerExists' => $customerExists,
//       ];
//       echo json_encode($response);
//     } catch (Exception $e) {
//       // Handle errors
//       http_response_code(400); // Bad Request
//       echo json_encode(['error' => $e->getMessage()]);
//     }
//   } else {
//     // Handle non-POST requests
//     http_response_code(405); // Method Not Allowed
//     echo json_encode(['error' => 'Invalid request method: Only POST allowed']);
//   }
  

// Function to create a customer

function create_customer($customerName) {
    $realmId=$_ENV['REALM_ID'];
    
    $existingCustomerName= checkCustomerExists($customerName);
    if ($existingCustomerName) {
        http_response_code(409); // Conflict status code
        return json_encode(['error' => 'Customer already exists']);
    }

    $customerData = [
        "DisplayName" => $customerName,
        "GivenName" => $customerName,
        "FamilyName" => "",
    ];

    $url = "https://sandbox-quickbooks.api.intuit.com/v3/company/$realmId/customer";

    $accessToken = getValidAccessToken();

    $headers = [
        'Authorization' =>"Bearer $accessToken",
        'Content-Type' => 'application/json',
    ];



    $client = new Client();

    try {
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $customerData,
        ]);

        if ($response->getStatusCode() == 200) {
            $responseData = simplexml_load_string($response->getBody());
            $jsondata = json_encode($responseData);
        } else {
            error_log("Failed to create customer: $responseData");
            http_response_code($response->getStatusCode());
            echo json_encode(['error' => 'Customer Already Exists']);
            exit;
        }
    } catch (Exception $e) {
        error_log('Failed to create customer: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create customer']);
        exit;
    }
}
///////////////////////////////////For debugging check create customer function//////////////////////////////////
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $data = json_decode(file_get_contents('php://input'), true);

//     if (isset($data['customerName'])) {
//         $customerName = $data['customerName'];

//         try {
//             $customerId = create_customer($customerName);
//             echo json_encode(['customerName' => $customerId]);
//         } catch (Exception $e) {
//             echo json_encode(['error' => $e->getMessage()]);
//             http_response_code(500);
//         }
//     } else {
//         echo json_encode(['error' => 'customerName is required']);
//         http_response_code(400);
//     }
// } else {
//     echo json_encode(['error' => 'Invalid request method']);
//     http_response_code(405);
// }

// Function to check if an item exists
function checkItemExists($itemName) {
    $realmId = $_ENV['REALM_ID'];

    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        echo("No access tokens found");
        return false;
    }

    $url = "https://sandbox-quickbooks.api.intuit.com/v3/company/{$realmId}/query";
    $query = "select * from Item where Name='{$itemName}'";

    $accessToken = getValidAccessToken();
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/text',
    ];

    $client = new Client();

    try {
        $response = $client->post($url, [
            'headers' => $headers,
            'body' => $query,
        ]);

        if ($response->getStatusCode() == 200 && $response->getBody()) {
            // Parse the XML response
            $xmlData = simplexml_load_string($response->getBody());
            // Check for item existence based on the XML structure
            $itemExists = (isset($xmlData->QueryResponse->Item)); // Assuming Item element indicates existence
            return $itemExists;
      
        } else {
            // Handle non-200 status code (e.g., log the error)
            echo("Error: " . $response->getStatusCode());
            return false;
        }
    } catch (Exception $e) {
        // Handle exceptions during the request (e.g., log the error)
        return false;
    }
}

// Function to create an item
function createItem($itemName) {
    $realmId = $_ENV['REALM_ID'];

    $existingItemName= checkItemExists($itemName);
    if ($existingItemName) {
        http_response_code(409); // Conflict status code
        return json_encode(['error' => 'Item already exists']);
    }

    $itemData = [
        "Name" => $itemName, 
        "Type" => "Inventory",
        "TrackQtyOnHand" => true, 
        "QtyOnHand" => 0, 
        "IncomeAccountRef" => [
            "name" => "Sales of Product Income", 
            "value" => "79" // Replace with actual account ID
        ],
        "AssetAccountRef" => [
            "name" => "Inventory Asset", 
            "value" => "81" // Replace with actual account ID
        ],
        "ExpenseAccountRef" => [
            "name" => "Cost of Goods Sold", 
            "value" => "80" // Replace with actual account ID
        ],
        "InvStartDate" => "2024-01-01" // Example start date
           
    ];

    $url = "https://sandbox-quickbooks.api.intuit.com/v3/company/{$realmId}/item";
    $accessToken = getValidAccessToken();
    $headers = [
        'Authorization' => "Bearer $accessToken",
        'Content-Type' => 'application/json',
    ];
    $client = new Client();
    try {
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $itemData,
        ]);

        print_r($response);
        if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {

            $responseData = simplexml_load_string($response->getBody());
            $jsondata = json_encode($responseData);
            print_r( $jsondata);

            // $responseData = json_decode($response->getBody(), true);
            // return $responseData; // Return the created item data
        } else {
            $responseBody = $response->getBody()->getContents();
            error_log("Failed to create item: $responseBody");
            return ['error' => 'Failed to create item', 'details' => $responseBody];
        }
    } catch (Exception $e) {
        // Log and handle the error
        error_log('Failed to create item: ' . $e->getMessage());
        return ['error' => 'Failed to create item'];
    }
}

// Function to create invoice
function create_invoice(array $invoiceData) {
    try {
       
        $customerName = isset($invoiceData['customerRef']['name']) ? $invoiceData['customerRef']['name'] : null;
        // print_r($customerName);
        $customerId = checkCustomerExists($customerName);

        // // echo json_encode($customerExists);

        if (!$customerId) {
            $customerCreationResult = create_customer($customerName);
            // Check if customer creation was successful
            if (isset($customerCreationResult['error'])) {
                // If customer creation failed, return error
                return $customerCreationResult;
            }
            // Assuming create_customer returns the new customer's ID
            $customerId = checkCustomerExists($customerName);
        }
    

          // Check if each item in the invoice exists, and create it if not
        foreach ($invoiceData['Line'] as $line) {
            $itemName = $line['SalesItemLineDetail']['ItemRef']['name'];
            // print_r($itemName);
            $itemExists = checkItemExists($itemName);

            if (!$itemName) {
                return ['error' => 'Item name is required'];
            }


            if (!$itemExists) {

                $itemCreationResult = createItem($itemName);
                // echo json_encode($itemCreationResult);

                // Check if item creation was successful
                if (isset($itemCreationResult['error'])) {
                    // If item creation failed, return error
                    return $itemCreationResult;
                }
            }
        }
        
        // Prepare invoice data
        $invoiceData = [
            'CustomerRef' => [
                'value' =>  $customerId,
            ],
            'TxnDate' => $invoiceData['TxnDate'],
            'DueDate' => $invoiceData['DueDate'],
            'Line' => $invoiceData['Line'],
        ];

    

        // Create invoice using Guzzle
        $client = new Client();

        $realmId= $_ENV['REALM_ID'];

        $url = "https://sandbox-quickbooks.api.intuit.com/v3/company/{$realmId}/invoice?minorversion=70";

        $accessToken = getValidAccessToken();

        $headers = [
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ];

        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $invoiceData,
        ]);

        // print_r($response);

        if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
            $responseData = simplexml_load_string($response->getBody());
            $jsondata = json_encode($responseData);
            return $jsondata;
        } else {
            $responseBody = $response->getBody()->getContents();
            error_log("Failed to create invoice: $responseBody");
            return ['error' => 'Failed to create invoice', 'details' => $responseBody];
        }
    } catch (Exception $e) {
        error_log('Failed to create invoice: ' . $e->getMessage());
        return ['error' => 'Failed to create invoice'];
    }
}
      
// Example usage within a request handling context
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Define invoiceData
    $invoiceData = [
        'customerRef' => [
            'name' => isset($data['customerRef']['value']) ? $data['customerRef']['value'] : null,
        ],
        'TxnDate' => isset($data['TxnDate']) ? $data['TxnDate'] : null,
        'DueDate' => isset($data['DueDate']) ? $data['DueDate'] : null,
        'Line' => isset($data['Line']) ? $data['Line'] : null,
        // Add more invoice data fields as needed
    ];

   // Validate invoiceData
   if (!empty($invoiceData['customerRef']['name']) && !empty($invoiceData['TxnDate']) && !empty($invoiceData['DueDate']) && !empty($invoiceData['Line'])) {
    try {
        // Create the invoice
        $invoiceResult = create_invoice($invoiceData);
        echo json_encode($invoiceResult);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        http_response_code(500);
    }
} else {
    echo json_encode(['error' => 'Invalid request data']);
    http_response_code(400);
}
} else {
echo json_encode(['error' => 'Invalid request method']);
http_response_code(405);
}