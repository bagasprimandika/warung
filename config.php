<?php
require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/pdo_mysql_session_handler.php';

$forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$isSecureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';

session_set_cookie_params([
    'httponly' => true,
    'secure' => $isSecureRequest,
    'samesite' => $isSecureRequest ? 'None' : 'Lax'
]);
session_set_save_handler(new PdoMySqlSessionHandler($pdo), true);

try {
    if (!session_start()) {
        throw new RuntimeException('PHP session_start returned false.');
    }
} catch (Throwable $error) {
    error_log('Warung session initialization failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Sesi tidak dapat dimulai. Pastikan penyimpanan sesi sudah disiapkan.');
}

header('Cache-Control: no-store, private');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

if (!$isProduction) {
    // Keep local demo setup out of the existing Production database.
    // Migrasi ringan agar database lama tetap bisa memakai fitur baru.
$migrations = [
    'users' => [
        'full_name' => 'ALTER TABLE users ADD full_name VARCHAR(100) NULL AFTER username',
        'email' => 'ALTER TABLE users ADD email VARCHAR(120) NULL UNIQUE AFTER password',
        'phone' => 'ALTER TABLE users ADD phone VARCHAR(30) NULL AFTER email',
        'address' => 'ALTER TABLE users ADD address TEXT NULL AFTER phone',
        'role' => "ALTER TABLE users ADD role VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER address"
    ],
    'products' => [
        'category' => "ALTER TABLE products ADD category VARCHAR(50) NOT NULL DEFAULT 'Umum' AFTER name",
        'description' => 'ALTER TABLE products ADD description VARCHAR(255) NULL AFTER category',
        'image_url' => 'ALTER TABLE products ADD image_url VARCHAR(500) NULL AFTER description'
    ],
    'orders' => [
           'status' => "ALTER TABLE orders ADD status VARCHAR(30) NOT NULL DEFAULT 'Menunggu Pembayaran' AFTER total",
           'note' => 'ALTER TABLE orders ADD note TEXT NULL AFTER address'
    ]
];

foreach ($migrations as $table => $columns) {
    foreach ($columns as $column => $query) {
        $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'")->fetch();
        if (!$check) {
            $pdo->exec($query);
        }

        $pdo->exec("UPDATE orders SET status = CASE status
            WHEN 'Menunggu' THEN 'Menunggu Pembayaran'
            WHEN 'Perjalanan' THEN 'Dikirim'
            WHEN 'Sukses' THEN 'Selesai'
            ELSE status END
            WHERE status IN ('Menunggu', 'Perjalanan', 'Sukses')");
    }
}

// Menjamin dua akun demo tersedia setelah database baru atau migrasi lama.
$adminCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$adminCheck->execute(['admin']);
if (!$adminCheck->fetch()) {
    $statement = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
    $statement->execute(['Administrator Toko', 'admin', 'admin@toko.local', password_hash('password', PASSWORD_DEFAULT), 'admin']);
} else {
    $pdo->prepare("UPDATE users SET role = 'admin' WHERE username = 'admin'")->execute();
}

$customerCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$customerCheck->execute(['pelanggan']);
if (!$customerCheck->fetch()) {
    $statement = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
    $statement->execute(['Pelanggan Demo', 'pelanggan', 'pelanggan@toko.local', password_hash('password123', PASSWORD_DEFAULT), 'customer']);
}

$catalog = [
    ['Kopi Arabika', 'Minuman', 'Kopi arabika 100% dengan aroma kuat dan rasa seimbang.', 25000, 20, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=600&q=80'],
    ['Teh Melati', 'Minuman', 'Teh melati harum dalam kemasan praktis untuk sehari-hari.', 15000, 20, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?auto=format&fit=crop&w=600&q=80'],
    ['Roti Cokelat', 'Makanan', 'Roti lembut dengan isian cokelat manis.', 12000, 20, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80'],
    ['Susu UHT 1 Liter', 'Minuman', 'Susu UHT berkualitas untuk keluarga.', 18000, 15, 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80'],
    ['Air Mineral 600 ml', 'Minuman', 'Air mineral praktis untuk bekal dan perjalanan.', 4000, 50, 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?auto=format&fit=crop&w=600&q=80'],
    ['Kopi Sachet', 'Minuman', 'Kopi sachet manis untuk teman santai.', 2500, 60, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80'],
    ['Beras Premium 5 kg', 'Sembako', 'Beras pulen pilihan untuk kebutuhan keluarga.', 78000, 12, 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80'],
    ['Minyak Goreng 1 Liter', 'Sembako', 'Minyak goreng jernih untuk kebutuhan dapur.', 19000, 18, 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=600&q=80'],
    ['Gula Pasir 1 kg', 'Sembako', 'Gula pasir putih untuk minuman dan masakan.', 17500, 20, 'https://images.unsplash.com/photo-1581441363689-1f3c3b4c1c1?auto=format&fit=crop&w=600&q=80'],
    ['Telur Ayam 1 kg', 'Sembako', 'Telur ayam segar untuk kebutuhan harian.', 30000, 15, 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?auto=format&fit=crop&w=600&q=80'],
    ['Keripik Kentang', 'Makanan', 'Keripik kentang renyah dengan rasa gurih.', 17000, 25, 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=600&q=80'],
    ['Mie Instan Goreng', 'Makanan', 'Mie instan praktis dengan bumbu khas.', 4000, 40, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=600&q=80'],
    ['Biskuit Cokelat', 'Makanan', 'Biskuit renyah dengan rasa cokelat untuk camilan.', 11000, 30, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=600&q=80'],
    ['Sabun Mandi Cair', 'Kebutuhan Rumah', 'Sabun cair lembut dengan aroma segar.', 22000, 15, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=600&q=80'],
    ['Pasta Gigi Herbal', 'Kebutuhan Rumah', 'Pasta gigi herbal untuk perlindungan gigi harian.', 14000, 20, 'https://images.unsplash.com/photo-1559591937-e2c7e9c2c0f1?auto=format&fit=crop&w=600&q=80'],
    ['Sabun Cuci Piring', 'Kebutuhan Rumah', 'Sabun cuci piring untuk membersihkan peralatan dapur.', 13000, 20, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=600&q=80'],
    ['Tumbler Stainless', 'Aksesoris', 'Botol minum stainless steel tahan panas dan dingin.', 65000, 10, 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?auto=format&fit=crop&w=600&q=80'],
    ['Kabel Data USB-C', 'Elektronik', 'Kabel data USB-C untuk pengisian dan transfer data.', 35000, 18, 'https://images.unsplash.com/photo-1587033411391-5d9e51cce126?auto=format&fit=crop&w=600&q=80'],
    ['Headset Bluetooth', 'Elektronik', 'Headset nirkabel dengan suara jernih dan baterai tahan lama.', 145000, 8, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=600&q=80'],
    ['Lampu LED 9 Watt', 'Elektronik', 'Lampu LED hemat energi untuk ruangan rumah.', 28000, 12, 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=600&q=80'],
    ['Tepung Terigu 1 kg', 'Sembako', 'Tepung terigu serbaguna untuk membuat kue dan gorengan.', 13000, 25, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80'],
    ['Garam Dapur 500 g', 'Sembako', 'Garam dapur beryodium untuk kebutuhan memasak sehari-hari.', 5000, 30, 'https://images.unsplash.com/photo-1518110925495-5fe2b50e6a4b?auto=format&fit=crop&w=600&q=80'],
    ['Kecap Manis 600 ml', 'Sembako', 'Kecap manis dengan rasa gurih untuk pelengkap masakan.', 18000, 20, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=600&q=80'],
    ['Saus Sambal 340 ml', 'Sembako', 'Saus sambal pedas untuk melengkapi makanan favorit.', 14000, 18, 'https://images.unsplash.com/photo-1472476443507-c7a5948772fc?auto=format&fit=crop&w=600&q=80'],
    ['Sarden Kaleng', 'Makanan', 'Sarden kaleng praktis dengan saus tomat yang lezat.', 22000, 16, 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=600&q=80'],
    ['Bubur Instan', 'Makanan', 'Bubur instan praktis untuk sarapan cepat dan mengenyangkan.', 9000, 25, 'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=600&q=80'],
    ['Wafer Cokelat', 'Makanan', 'Wafer renyah berlapis cokelat untuk camilan keluarga.', 8000, 35, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=600&q=80'],
    ['Jus Jeruk 1 Liter', 'Minuman', 'Minuman jus jeruk segar untuk menemani waktu makan.', 16000, 20, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?auto=format&fit=crop&w=600&q=80'],
    ['Susu Cokelat Kotak', 'Minuman', 'Susu cokelat siap minum dengan rasa manis yang disukai anak-anak.', 7000, 30, 'https://images.unsplash.com/photo-1576186726115-4d51596775d1?auto=format&fit=crop&w=600&q=80'],
    ['Detergen Bubuk 800 g', 'Kebutuhan Rumah', 'Detergen bubuk untuk mencuci pakaian agar bersih dan harum.', 19000, 22, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=600&q=80'],
    ['Pewangi Pakaian', 'Kebutuhan Rumah', 'Pewangi pakaian dengan aroma segar dan tahan lama.', 17000, 18, 'https://images.unsplash.com/photo-1610557892470-a0e3c9f9e3f0?auto=format&fit=crop&w=600&q=80'],
    ['Tisu Wajah 200 Lembar', 'Kebutuhan Rumah', 'Tisu lembut untuk kebutuhan rumah, kantor, dan perjalanan.', 12000, 30, 'https://images.unsplash.com/photo-1584556812952-905ffd0c611a?auto=format&fit=crop&w=600&q=80'],
    ['Sapu Lantai', 'Kebutuhan Rumah', 'Sapu lantai ringan untuk membersihkan rumah setiap hari.', 25000, 12, 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=600&q=80'],
    ['Payung Lipat', 'Aksesoris', 'Payung lipat praktis untuk perlindungan dari hujan dan panas.', 45000, 10, 'https://images.unsplash.com/photo-1531327431456-837da4b1d562?auto=format&fit=crop&w=600&q=80'],
    ['Dompet Kartu', 'Aksesoris', 'Dompet kecil untuk menyimpan kartu dan uang dengan praktis.', 35000, 14, 'https://images.unsplash.com/photo-1627123424574-724758594e93?auto=format&fit=crop&w=600&q=80'],
    ['Mouse Wireless', 'Elektronik', 'Mouse wireless ergonomis untuk laptop dan komputer.', 55000, 10, 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=600&q=80'],
    ['Charger Fast Charging', 'Elektronik', 'Charger cepat untuk berbagai perangkat USB.', 75000, 9, 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=600&q=80'],
    ['Power Bank 10000 mAh', 'Elektronik', 'Power bank praktis untuk mengisi daya saat bepergian.', 120000, 7, 'https://images.unsplash.com/photo-1609592424537-d2d8d1c6b7b1?auto=format&fit=crop&w=600&q=80'],
    ['Baterai AA Isi 4', 'Elektronik', 'Baterai AA untuk remote, jam, dan perangkat elektronik rumah.', 22000, 20, 'https://images.unsplash.com/photo-1603126857599-f6e157fa2fe6?auto=format&fit=crop&w=600&q=80']
];

$findProduct = $pdo->prepare('SELECT id FROM products WHERE name = ?');
$insertProduct = $pdo->prepare('INSERT INTO products (name, category, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)');
$updateProduct = $pdo->prepare('UPDATE products SET category = ?, description = ?, image_url = ? WHERE id = ?');
foreach ($catalog as $product) {
    $findProduct->execute([$product[0]]);
    $existingProduct = $findProduct->fetch(PDO::FETCH_ASSOC);
    if ($existingProduct) {
        $updateProduct->execute([$product[1], $product[2], $product[5], $existingProduct['id']]);
    } else {
        $insertProduct->execute($product);
    }
}

}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>
