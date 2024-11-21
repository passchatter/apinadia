<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include '../database.php';

function execute_query($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return json_encode($data);
}

$request = $_SERVER['PATH_INFO'] ?? '';
$request = trim($request, '/');
$segments = explode('/', $request);

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
   
    if ($request === '' || $request === 'all') {
        // Mendapatkan parameter halaman dan limit dari request
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Default page 1
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default limit 10

        // Menghitung OFFSET berdasarkan halaman yang diminta
        $offset = ($page - 1) * $limit;

        // Modifikasi query SQL dengan LIMIT dan OFFSET
        $sql = "SELECT * FROM produk LIMIT $limit OFFSET $offset";
        
        // Eksekusi query untuk produk
        $data = json_decode(execute_query($sql), true);
        
        // Query untuk menghitung total produk
        $sql_count = "SELECT COUNT(*) as total FROM produk";
        $result_count = json_decode(execute_query($sql_count), true);
        $total = $result_count[0]['total'];

        // Menghitung total halaman
        $total_pages = ceil($total / $limit);

        // Menambahkan informasi total halaman dan produk ke dalam response
        $response = [
            'data' => $data, // Data produk yang sudah difilter
            'total_pages' => $total_pages, // Total halaman
            'total' => $total, // Total produk
            'current_page' => $page // Halaman saat ini
        ];

        echo json_encode($response);
    } elseif ($segments[0] === "detail" && isset($segments[1])) {
        $id_produk = (int)$segments[1];
        $sql = "SELECT * FROM produk WHERE id = ?";
        echo execute_query($sql, 'i', [$id_produk]);

    } elseif ($segments[0] === "related" && isset($segments[1]) && isset($segments[2])) {
        $category = $segments[1];
        $id_produk = (int)$segments[2];
    
        $sql1 = "SELECT * FROM produk WHERE category = ? AND id < ? ORDER BY id DESC LIMIT 10";
        $result1 = json_decode(execute_query($sql1, 'si', [$category, $id_produk]), true);  // Decode JSON ke array
    
        // Jika hasil kurang dari 10, ambil produk dengan ID lebih besar dari produk saat ini
        if (count($result1) < 10) {
            $limit = 10 - count($result1); // Menghitung jumlah produk yang masih kurang
    
            // Ambil produk yang ID-nya lebih besar dari produk yang sedang dilihat
            $sql2 = "SELECT * FROM produk WHERE category = ? AND id > ? ORDER BY id ASC LIMIT ?";
            $result2 = json_decode(execute_query($sql2, 'sii', [$category, $id_produk, $limit]), true); // Decode JSON ke array
    
            // Gabungkan hasil yang kurang dengan yang baru
            $result1 = array_merge($result1, $result2);
        }
    
        // Menghapus produk yang memiliki ID yang sama dengan produk yang sedang dilihat
        $result1 = array_filter($result1, function($product) use ($id_produk) {
            return $product['id'] != $id_produk;
        });
    
        // Kembalikan hasil sebagai JSON (jangan lupa untuk mereset indeks array setelah filter)
        echo json_encode(array_values($result1));  // Menggunakan array_values() untuk reindex array
    }

    elseif ($segments[0] === 'filter') {
        
        $category = $_GET['category'] ?? '';
        $material = $_GET['material'] ?? '';
        $size = $_GET['size'] ?? '';
        $color = $_GET['color'] ?? '';
        $search = $_GET['search'] ?? '';

        // Mendapatkan parameter halaman dan limit dari request
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Default page 1
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default limit 10
        $offset = ($page - 1) * $limit; // Offset untuk pagination
        
        $sql = "SELECT * FROM produk WHERE 1=1";
        $types = '';
        $params = [];

        if (!empty($category)) {
            $sql .= " AND category = ?";
            $types .= 's';
            $params[] = $category;
        }

        if (!empty($material)) {
            $sql .= " AND material LIKE ?";
            $types .= 's';
            $params[] = "%$material%";
        }

        if (!empty($size)) {
            $sql .= " AND size LIKE ?";
            $types .= 's';
            $params[] = "%$size%";
        }

        if (!empty($color)) {
            $sql .= " AND color LIKE ?";
            $types .= 's';
            $params[] = "%$color%";
        }

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR deskripsi LIKE ?)";
            $types .= 'ss';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Menghitung total produk yang sesuai filter
        $sql_count = "SELECT COUNT(*) as total FROM produk WHERE 1=1";
        if (!empty($category)) {
            $sql_count .= " AND category = ?";
        }
        if (!empty($material)) {
            $sql_count .= " AND material LIKE ?";
        }
        if (!empty($size)) {
            $sql_count .= " AND size LIKE ?";
        }
        if (!empty($color)) {
            $sql_count .= " AND color LIKE ?";
        }
        if (!empty($search)) {
            $sql_count .= " AND (name LIKE ? OR deskripsi LIKE ?)";
        }

        // Query untuk menghitung total produk yang sesuai dengan filter
        $result_count = json_decode(execute_query($sql_count, $types, $params), true);
        $total = $result_count[0]['total'];
        
        // Menghitung total halaman
        $total_pages = ceil($total / $limit);

        // Menambahkan LIMIT dan OFFSET pada query produk
        $sql .= " LIMIT $limit OFFSET $offset";

        // Eksekusi query dan kirimkan hasil
        $data = json_decode(execute_query($sql, $types, $params), true);
        
        // Menambahkan informasi pagination ke dalam response
        $response = [
            'data' => $data,
            'total_pages' => $total_pages,
            'total' => $total,
            'current_page' => $page
        ];

        echo json_encode($response);
    } else {
        echo json_encode(['message' => 'Endpoint tidak ditemukan']);
    }
} else {
    echo json_encode(['message' => 'Metode tidak valid']);
}
?>
