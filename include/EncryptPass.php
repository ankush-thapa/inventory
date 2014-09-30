<?php
 
class EncryptPass {
 
    private static $var1 = 'xyz';
    private static $var2 = 'abc';
 
    public static function salt() {
        return substr(sha1(mt_rand()), 0, 22);
    }
 
    public static function hash($password) {
        return crypt($password, self::$var1 .
                self::$var2 .
                '$' . self::salt());
    }

    public static function check_password($hash, $password) {
        $full_salt = substr($hash, 0, 29);
        $new_hash = crypt($password, $full_salt);
        return ($hash == $new_hash);
    }
 
}
 
?>
