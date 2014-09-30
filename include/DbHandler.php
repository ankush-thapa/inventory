<?php
class DbHandler {
 
    private $conn;
 
    function __construct() {
        require_once 'DbConnect.php';
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
 
    // create new user
    public function createUser($name, $email, $password) {
        require_once 'EncryptPass.php';
        $response = array();
 
        // First check if user already existed in db
        if (!$this->UserExists($email)) {
            $encrypted_password = EncryptPass::hash($password);
            $api_key = $this->createApiKey();
 
            // insert into table
            $query = $this->conn->prepare("INSERT INTO users(name, email, password_hash, api_key, status) values(?, ?, ?, ?, 1)");
            $query->bind_param("ssss", $name, $email, $encrypted_password, $api_key);
            $result = $query->execute();
            $query->close();
 
            if ($result) {
                return array(0, $api_key);
            } else {
                return array(1, $api_key);
            }
        } else {
            return array(2, '');
        }
        return $response;
    }
 
    public function checkLogin($email, $password) {
        $query = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");
        $query->bind_param("s", $email);
        $query->execute();
        $query->bind_result($password_hash);
        $query->store_result();
 
        if ($query->num_rows > 0) {
	    // found users
            $query->fetch();
            $query->close();
 
            if (EncryptPass::check_password($password_hash, $password)) {
		// password matched
                return TRUE;
            } else {
		// password mismatch
                return FALSE;
            }
        } else {
            $query->close();
            // user not found
            return FALSE;
        }
    }
 
    private function UserExists($email) {
        $query = $this->conn->prepare("SELECT id from users WHERE email = ?");
        $query->bind_param("s", $email);
        $query->execute();
        $query->store_result();
        $num_rows = $query->num_rows;
        $query->close();
        return $num_rows > 0;
    }
 
    public function getUserByEmail($email) {
        $query = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $query->bind_param("s", $email);
        if ($query->execute()) {
	    $query->bind_result($name, $email, $api_key, $status, $created_at);
	    mysqli_stmt_fetch($query);
	    $user['name'] = $name;
	    $user['email'] = $email;
	    $user['api_key'] = $api_key;
	    $user['created_at'] = $created_at;
	    $query->close();
	    return $user;
	    } 
	else {
	    return null;
	    }
	}
 
    public function getApiKeyById($user_id) {
        $query = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $query->bind_param("i", $user_id);
        if ($query->execute()) {
            $api_key = $query->get_result()->fetch_assoc();
            $query->close();
            return $api_key;
        } else {
            return NULL;
        }
    }
 
    public function getUserId($api_key) {
        $query = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $query->bind_param("s", $api_key);
        if ($query->execute()) {
	    $query->bind_result($user_id);
            $query->close();
            return $user_id;
        } else {
            return NULL;
        }
    }		

    public function isValidApiKey($api_key) {
        $query = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $query->bind_param("s", $api_key);
        $query->execute();
        $query->store_result();
        $num_rows = $query->num_rows;
        $query->close();
        return $num_rows > 0;
    }
 
    private function createApiKey() {
        return md5(uniqid(rand(), true));
    }
 
    public function createProduct($product) {       
        $query = $this->conn->prepare("INSERT INTO products VALUES(Null, ?)");
        $query->bind_param("s", $product);
        $result = $query->execute();
        $query->close();
 
        if ($result) {
            $new_product_id = $this->conn->insert_id;
	    return $new_product_id;
        } else {
            return NULL;
        }
    }
 
    public function getProduct($product_id) {
        $query = $this->conn->prepare("SELECT p.id, p.title from products p WHERE p.id = ?");
        $query->bind_param("s", $product_id);
        if ($query->execute()) {
            $query->bind_result($id, $product);
	    $query->fetch();
            $query->close();
            return array($id, $product);
        } else {
            return NULL;
        }
    }
 
    public function getAllProducts() {
	$response = array();
        $response["error"] = false;
        $response["products"] = array();
        $query = $this->conn->prepare("SELECT * FROM products");
        $query->execute();
	$query->bind_result($id, $products);
	while($query->fetch()){
	    $tmp = array();
	    $tmp["id"] = $id;
	    $tmp["title"] = $products;
	    array_push($response["products"], $tmp);
	}
        return $response;
    }
 
    public function updateProduct($product_id, $title) {
        $query = $this->conn->prepare("UPDATE products p SET p.title = ? where p.id = ?");
        $query->bind_param("ss", $title, $product_id);
        $query->execute();
        $num_affected_rows = $query->affected_rows;
        $query->close();
        return $num_affected_rows > 0;
    }
 
    public function deleteProduct($product_id) {
        $query = $this->conn->prepare("DELETE p FROM products p WHERE p.id = ?");
        $query->bind_param("s", $product_id);
        $query->execute();
        $num_affected_rows = $query->affected_rows;
        $query->close();
        return $num_affected_rows > 0;
    }
 
}
 
?>
