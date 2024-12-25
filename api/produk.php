<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include '../index.php';

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

// Mengambil parameter request
$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    if ($request === '' || $request === 'all') {
        // Handle endpoint 'all'
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM produk LIMIT $limit OFFSET $offset";
        $data = json_decode(execute_query($sql), true);

        $sql_count = "SELECT COUNT(*) as total FROM produk";
        $result_count = json_decode(execute_query($sql_count), true);
        $total = $result_count[0]['total'];
        $total_pages = ceil($total / $limit);

        echo json_encode([
            'data' => $data,
            'total_pages' => $total_pages,
            'total' => $total,
            'current_page' => $page
        ]);
    } elseif ($request === "detail" && isset($_GET['id'])) {
        // Handle endpoint 'detail'
        $id_produk = (int)$_GET['id'];
        $sql = "SELECT * FROM produk WHERE id = ?";
        echo execute_query($sql, 'i', [$id_produk]);
    } elseif ($request === "related") {
        // Handle endpoint 'related'
        $category = $_GET['category'] ?? '';
        $id_produk = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $sql = "SELECT * FROM produk WHERE category = ? AND id < ? ORDER BY id DESC LIMIT 10";
        echo execute_query($sql, 'is', [$category, $id_produk]);
        // ... (logika related tetap sama)
    } elseif ($request === 'filter') {
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
