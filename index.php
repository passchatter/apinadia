<?php

$host = '62.72.58.198';
$user = 'root';
$pass = 'root';
$dbname = 'nadiaproduk';

$conn = new mysqli($host, $user, $pass, $dbname);
if($conn->connect_error){
    die("koneksi gagal: ". $conn->connect_error);
}

?>