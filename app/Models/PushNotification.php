<?php


namespace App\Models;


use Exception;
use PDO;

class PushNotification
{
    /**
     * @throws Exception
     */

     private PDO $pdo;


     public function __construct() {
        $dsn = 'mysql:dbname=push;host=localhost';
        $this->pdo = new PDO($dsn, env("DB_USER_NAME"), env("DB_PASSWORD"));
     }

    public static function send(string $title, string $message, string $token): bool
    {
        return random_int(1, 10) > 1;
    }


    
}