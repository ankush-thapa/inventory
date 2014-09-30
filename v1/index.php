<?php
 
require_once '../include/DbHandler.php';
require_once '../include/EncryptPass.php';
require '.././libs/Slim/Slim.php';
 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
$user_id = NULL;
 
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}

$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));
 
            $response = array();
 
            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
 
            // validating email address
            validateEmail($email);
 
            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);
 
            if ($res[0] == 0) {
                $response["error"] = false;
                $response["api_key"] = $res[1];
                $response["message"] = "You are successfully registered";
                echoRespnse(201, $response);
            } else if ($res[0] == 1) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
                echoRespnse(200, $response);
            } else if ($res[0] == 2) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
                echoRespnse(200, $response);
            }
        });
 
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));
 
            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();
 
            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);
 
                if ($user != NULL) {
                    $response["error"] = false;
                    $response['email'] = $user['email'];
                    $response['api_key'] = $user['api_key'];
                    $response['message'] = 'You have been successfully loggedin.';
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Invalid email or password.';
            }
 
            echoRespnse(200, $response);
        });

function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
 
    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
 
        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid api_key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL)
                $user_id = $user["id"];
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

$app->post('/products', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('title'));
 
            $response = array();
            $product = $app->request->post('title');
 
            $db = new DbHandler();
 
            // creating new product 
            $product_id = $db->createProduct($product);
 
            if ($product_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Product Added successfully";
                $response["product_id"] = $product_id;
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to add Product. Please try again";
            }
            echoRespnse(201, $response);
        });

$app->get('/products', 'authenticate', function() {
            $response = array();
            $db = new DbHandler();
            $response = $db->getAllProducts();
            echoRespnse(200, $response);
        });

$app->get('/products/:id', 'authenticate', function($product_id) {
            $response = array();
            $db = new DbHandler();
 
            $result = $db->getProduct($product_id);
 
            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result[0];
                $response["title"] = $result[1];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

$app->put('/products/:id', 'authenticate', function($product_id) use($app) {
            // check for required params
            verifyRequiredParams(array('title'));
 
            $title = $app->request->put('title');
            $db = new DbHandler();
            $response = array();
 
            $result = $db->updateProduct($product_id, $title);
            if ($result) {
                $response["error"] = false;
                $response["message"] = "Product updated successfully";
            } else {
                $response["error"] = true;
                $response["message"] = "Product failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

$app->delete('/products/:id', 'authenticate', function($product_id) use($app) {
            $db = new DbHandler();
            $response = array();
            $result = $db->deleteProduct($product_id);
            if ($result) {
                $response["error"] = false;
                $response["message"] = "Product deleted succesfully";
            } else {
                $response["error"] = true;
                $response["message"] = "Product failed to delete. Please try again!";
            }
            echoRespnse(200, $response);
        });

$app->run();
?>
