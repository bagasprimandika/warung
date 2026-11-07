<?php
require 'config.php';

$message = '';
$messageType = 'alert';
$view = $_GET['view'] ?? 'shop';
$allowedStatuses = ['Menunggu Pembayaran', 'Diproses', 'Dikemas', 'Dikirim', 'Selesai', 'Dibatalkan'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $message = 'Password minimal 8 karakter.';
        } else {
            $statement = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $statement->execute([$username]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'] ?? $user['username'],
                    'role' => $user['role'] ?? 'customer'
                ];
                header('Location: ' . (($user['role'] ?? 'customer') === 'admin' ? 'index.php?view=admin' : 'index.php'));
                exit;
            }
            $message = 'Username atau password salah.';
        }
    }

    if ($action === 'register') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';
        $view = 'register';

        if ($fullName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Nama, username, dan email wajib diisi dengan benar.';
        } elseif (strlen($password) < 8) {
            $message = 'Password minimal 8 karakter.';
        } elseif ($password !== $passwordConfirmation) {
            $message = 'Konfirmasi password tidak sama.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $message = 'Username atau email sudah digunakan.';
            } else {
                $statement = $pdo->prepare('INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)');
                $statement->execute([$fullName, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $message = 'Registrasi berhasil. Silakan login dengan akun baru.';
                $messageType = 'success';
                $view = 'login';
            }
        }
    }

    if (isset($_SESSION['user']) && $action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $statement = $pdo->prepare('SELECT id, stock FROM products WHERE id = ?');
        $statement->execute([$productId]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);
        $currentQuantity = $_SESSION['cart'][$productId] ?? 0;
        if ($product && $currentQuantity < $product['stock']) {
            $_SESSION['cart'][$productId] = $currentQuantity + 1;
            $message = 'Produk berhasil masuk ke keranjang.';
            $messageType = 'success';
        } else {
            $message = 'Stok produk tidak mencukupi.';
        }
    }

    if (isset($_SESSION['user']) && $action === 'update_cart') {
        foreach ($_POST['quantity'] ?? [] as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = max(0, (int) $quantity);
            $stock = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
            $stock->execute([$productId]);
            $availableStock = (int) $stock->fetchColumn();
            $quantity = min($quantity, $availableStock);
            if ($quantity === 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = $quantity;
            }
        }
        $message = 'Keranjang berhasil diperbarui.';
        $messageType = 'success';
    }

    if (isset($_SESSION['user']) && $action === 'remove') {
        unset($_SESSION['cart'][(int) ($_POST['product_id'] ?? 0)]);
        $message = 'Produk dihapus dari keranjang.';
        $messageType = 'success';
    }

    if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? 'customer') === 'admin' && $action === 'update_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($orderId < 1 || !in_array($status, $allowedStatuses, true)) {
            $message = 'Status atau nomor pesanan tidak valid.';
        } else {
            $statement = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $statement->execute([$status, $orderId]);
            $message = "Status pesanan #$orderId diubah menjadi $status.";
            $messageType = 'success';
        }
        $view = 'admin';
    }

    if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? 'customer') === 'admin' && $action === 'delete_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        if ($orderId < 1) {
            $message = 'Nomor transaksi tidak valid.';
        } else {
            try {
                $pdo->beginTransaction();
                $deleteItems = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
                $deleteItems->execute([$orderId]);
                $deleteOrder = $pdo->prepare('DELETE FROM orders WHERE id = ?');
                $deleteOrder->execute([$orderId]);
                $pdo->commit();
                $message = $deleteOrder->rowCount() ? "Transaksi #$orderId berhasil dihapus permanen." : 'Transaksi tidak ditemukan.';
                $messageType = $deleteOrder->rowCount() ? 'success' : 'alert';
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Transaksi gagal dihapus. Data tetap dipertahankan.';
            }
        }
        $view = 'admin';
    }

    if (isset($_SESSION['user']) && $action === 'save_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$email, $_SESSION['user']['id']]);
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Nama dan email wajib diisi dengan benar.';
        } elseif ($check->fetch()) {
            $message = 'Email sudah digunakan akun lain.';
        } else {
            $statement = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?');
            $statement->execute([$fullName, $email, $phone, $address, $_SESSION['user']['id']]);
            $_SESSION['user']['full_name'] = $fullName;
            $message = 'Profil berhasil disimpan.';
            $messageType = 'success';
        }
        $view = 'profile';
    }

    if (isset($_SESSION['user']) && $action === 'checkout') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if (count($_SESSION['cart']) === 0) {
            $message = 'Checkout tidak dapat dilakukan karena keranjang masih kosong.';
        } elseif ($name === '' || $phone === '' || $address === '') {
            $message = 'Nama, nomor telepon, dan alamat lengkap wajib diisi.';
        } else {
            $ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
            $statement->execute($ids);
            $productsForOrder = $statement->fetchAll(PDO::FETCH_ASSOC);
            $productMap = [];
            $total = 0;
            $stockEnough = count($productsForOrder) === count($ids);
            foreach ($productsForOrder as $product) {
                $quantity = (int) $_SESSION['cart'][$product['id']];
                $productMap[$product['id']] = $product;
                $total += $product['price'] * $quantity;
                if ($quantity > $product['stock']) {
                    $stockEnough = false;
                }
            }
            if (!$stockEnough) {
                $message = 'Stok salah satu produk tidak mencukupi.';
            } else {
                $pdo->beginTransaction();
                try {
                    $fullAddress = "$name - $phone - $address";
                    $order = $pdo->prepare('INSERT INTO orders (user_id, address, note, total, status) VALUES (?, ?, ?, ?, ?)');
                    $order->execute([$_SESSION['user']['id'], $fullAddress, $note, $total, 'Menunggu Pembayaran']);
                    $orderId = $pdo->lastInsertId();
                    $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
                    $stock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                    foreach ($_SESSION['cart'] as $productId => $quantity) {
                        $product = $productMap[$productId];
                        $item->execute([$orderId, $productId, $quantity, $product['price']]);
                        $stock->execute([$quantity, $productId, $quantity]);
                    }
                    $pdo->commit();
                    $_SESSION['cart'] = [];
                    $message = "Checkout berhasil. Nomor pesanan #$orderId.";
                    $messageType = 'success';
                } catch (Exception $error) {
                    $pdo->rollBack();
                    $message = 'Checkout gagal. Silakan coba lagi.';
                }
            }
        }
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isLoggedIn = isset($_SESSION['user']);
$profile = null;
$products = [];
$groupedProducts = [];
$categories = [];
$cartProducts = [];
$orders = [];
$orderItems = [];
$adminStats = [];
$adminOrders = [];
$total = 0;
$cartCount = array_sum($_SESSION['cart']);
$categoryDescriptions = [
    'Sembako' => 'Bahan pokok untuk kebutuhan dapur harian.',
    'Minuman' => 'Minuman praktis untuk menemani aktivitas sehari-hari.',
    'Makanan' => 'Camilan dan makanan cepat saji untuk keluarga.',
    'Kebutuhan Rumah' => 'Perlengkapan kebersihan dan kebutuhan rumah.',
    'Aksesoris' => 'Barang pelengkap yang praktis digunakan.',
    'Elektronik' => 'Perangkat kecil untuk kebutuhan digital dan rumah.',
    'Umum' => 'Produk pilihan dari warung kami.'
];

if ($isLoggedIn) {
    $profileStatement = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $profileStatement->execute([$_SESSION['user']['id']]);
    $profile = $profileStatement->fetch(PDO::FETCH_ASSOC);
    $categories = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    $search = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $query = 'SELECT * FROM products WHERE 1=1';
    $parameters = [];
    if ($search !== '') {
        $query .= ' AND (name LIKE ? OR category LIKE ?)';
        $parameters[] = "%$search%";
        $parameters[] = "%$search%";
    }
    if ($category !== '') {
        $query .= ' AND category = ?';
        $parameters[] = $category;
    }
    $statement = $pdo->prepare($query . ' ORDER BY id DESC');
    $statement->execute($parameters);
    $products = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $product) {
        $groupedProducts[$product['category']][] = $product;
    }
    if (count($_SESSION['cart']) > 0) {
        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $statement->execute($ids);
        $cartProducts = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cartProducts as $product) {
            $total += $product['price'] * $_SESSION['cart'][$product['id']];
        }
    }
    if ($view === 'orders') {
        $statement = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $statement->execute([$_SESSION['user']['id']]);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($orders) {
            $orderIds = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $statement = $pdo->prepare("SELECT order_items.*, products.name FROM order_items JOIN products ON products.id = order_items.product_id WHERE order_items.order_id IN ($placeholders) ORDER BY order_items.id");
            $statement->execute($orderIds);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $orderItems[$item['order_id']][] = $item;
            }
        }
    }

    if (($_SESSION['user']['role'] ?? 'customer') === 'admin' && $view === 'admin') {
        $adminStats = $pdo->query("SELECT
            (SELECT COUNT(*) FROM orders) AS total_orders,
            (SELECT COALESCE(SUM(total), 0) FROM orders) AS total_revenue,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') AS total_customers,
            (SELECT COUNT(*) FROM products) AS total_products")->fetch(PDO::FETCH_ASSOC);
        $adminOrders = $pdo->query('SELECT orders.*, users.username, users.full_name FROM orders JOIN users ON users.id = orders.user_id ORDER BY orders.created_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WARUNG BAGAS | Kebutuhan Harian</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><div class="container topbar"><a class="brand" href="index.php">WARUNG BAGAS</a><?php if ($isLoggedIn): ?><nav><?php if (($_SESSION['user']['role'] ?? 'customer') === 'admin'): ?><a href="admin.php">Dashboard Admin</a><?php else: ?><a href="products.php">Produk</a><a href="orders.php">Pesanan</a><a href="profile.php">Profil</a><a class="cart-link" href="cart.php">Keranjang <span><?= $cartCount ?></span></a><?php endif; ?><a href="logout.php">Keluar</a></nav><?php endif; ?></div></header>
<main class="container">
<?php if (!$isLoggedIn): ?>
    <section class="login-box">
    <?php if ($view === 'register'): ?>
        <h1>Buat Akun Baru</h1>
        <?php if ($message): ?><div class="alert <?= $messageType === 'success' ? 'success' : '' ?>"><?= e($message) ?></div><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="register"><label>Nama lengkap</label><input type="text" name="full_name" required><label>Username</label><input type="text" name="username" minlength="3" required><label>Email</label><input type="email" name="email" required><label>Password (minimal 8 karakter)</label><input type="password" name="password" minlength="8" required><label>Ulangi password</label><input type="password" name="password_confirmation" minlength="8" required><button type="submit">Daftar Sekarang</button></form>
        <p>Sudah punya akun? <a href="login.php">Login</a></p>
    <?php else: ?>
        <h1>Login WARUNG BAGAS</h1>
        <?php if ($message): ?><div class="alert <?= $messageType === 'success' ? 'success' : '' ?>"><?= e($message) ?></div><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="login"><label>Username</label><input type="text" name="username" required><label>Password (minimal 8 karakter)</label><input type="password" name="password" minlength="8" required><button type="submit">Login</button></form>
        <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p><p class="muted">Demo: admin / password</p>
    <?php endif; ?>
    </section>
<?php else: ?>
    <?php if ($message): ?><div class="alert <?= $messageType === 'success' ? 'success' : '' ?>"><?= e($message) ?></div><?php endif; ?>
    <?php if ($view === 'admin' && ($_SESSION['user']['role'] ?? 'customer') === 'admin'): ?>
        <section class="hero panel"><div><p class="eyebrow">PANEL ADMINISTRASI</p><h1>Dashboard Penjualan</h1><p>Ringkasan seluruh transaksi dari semua pelanggan.</p></div><div><span class="badge admin-badge">Administrator</span><a class="button secondary" href="admin_products.php">Kelola Produk</a></div></section>
        <section class="stats-grid"><article class="stat-card"><span>Total pembelian</span><strong><?= $adminStats['total_orders'] ?></strong><small>Pesanan masuk</small></article><article class="stat-card"><span>Total omzet</span><strong>Rp <?= number_format($adminStats['total_revenue'], 0, ',', '.') ?></strong><small>Akumulasi semua pesanan</small></article><article class="stat-card"><span>Total pelanggan</span><strong><?= $adminStats['total_customers'] ?></strong><small>Akun customer</small></article><article class="stat-card"><span>Total produk</span><strong><?= $adminStats['total_products'] ?></strong><small>Produk di katalog</small></article></section>
        <section class="panel"><div class="section-heading"><h2>Pesanan Terbaru</h2><span>10 transaksi terakhir</span></div><?php if (!$adminOrders): ?><p>Belum ada transaksi.</p><?php else: ?><table><tr><th>Nomor</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Status admin</th><th>Nota</th><th>Aksi</th></tr><?php foreach ($adminOrders as $order): ?><tr><td>#<?= $order['id'] ?></td><td><?= e($order['full_name'] ?: $order['username']) ?></td><td><?= e($order['created_at']) ?></td><td>Rp <?= number_format($order['total'], 0, ',', '.') ?></td><td><form method="post" class="status-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><select name="status"><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><button type="submit">Simpan</button></form></td><td><a class="button secondary" href="nota.php?id=<?= (int) $order['id'] ?>">Lihat</a></td><td><form method="post" onsubmit="return confirm('Hapus transaksi #<?= (int) $order['id'] ?> secara permanen?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_order"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button class="danger" type="submit">Hapus</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></section>
    <?php elseif ($view === 'profile'): ?>
        <section class="panel narrow"><h1>Profil Saya</h1><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile"><label>Nama lengkap</label><input type="text" name="full_name" value="<?= e($profile['full_name'] ?? '') ?>" required><label>Username</label><input type="text" value="<?= e($profile['username']) ?>" disabled><label>Email</label><input type="email" name="email" value="<?= e($profile['email'] ?? '') ?>" required><label>Nomor telepon</label><input type="text" name="phone" value="<?= e($profile['phone'] ?? '') ?>"><label>Alamat default</label><textarea name="address" rows="4"><?= e($profile['address'] ?? '') ?></textarea><button type="submit">Simpan Profil</button></form></section>
    <?php elseif ($view === 'orders'): ?>
        <section class="panel"><div class="section-heading"><div><h1>Riwayat Transaksi</h1><p class="muted">Daftar ini menjadi bukti bahwa produk sudah tercatat dibeli.</p></div><span><?= count($orders) ?> transaksi</span></div><?php if (!$orders): ?><p>Belum ada transaksi. Pesanan yang berhasil checkout akan muncul di sini.</p><?php else: ?><?php foreach ($orders as $order): ?><article class="transaction"><div class="transaction-header"><div><strong>Transaksi #<?= $order['id'] ?></strong><p class="muted">Tanggal: <?= e($order['created_at']) ?></p></div><span class="badge status-<?= strtolower(str_replace(' ', '-', e($order['status']))) ?>"><?= e($order['status']) ?></span></div><table><tr><th>Produk</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th></tr><?php foreach ($orderItems[$order['id']] ?? [] as $item): ?><tr><td><?= e($item['name']) ?></td><td>Rp <?= number_format($item['price'], 0, ',', '.') ?></td><td><?= $item['quantity'] ?></td><td>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></td></tr><?php endforeach; ?></table><div class="transaction-footer"><span>Alamat: <?= e($order['address']) ?></span><strong>Total pembayaran: Rp <?= number_format($order['total'], 0, ',', '.') ?></strong></div><a class="button outline receipt-link" href="nota.php?id=<?= $order['id'] ?>">Lihat / cetak nota</a></article><?php endforeach; ?><?php endif; ?></section>
    <?php else: ?>
        <section class="hero panel"><div><p class="eyebrow">WARUNG KEBUTUHAN HARIAN</p><h1>Belanja mudah di WARUNG BAGAS</h1><p>Halo, <?= e($profile['full_name'] ?: $profile['username']) ?>. Pilih sembako, makanan, dan kebutuhan rumah, lalu checkout dengan alamat tersimpan.</p></div><a class="button secondary" href="?view=profile">Lengkapi profil</a></section>
        <section class="panel search-panel"><form method="get" class="search-form"><input type="search" name="q" placeholder="Cari nama atau kategori produk..." value="<?= e($_GET['q'] ?? '') ?>"><select name="category"><option value="">Semua kategori</option><?php foreach ($categories as $item): ?><option value="<?= e($item) ?>" <?= ($_GET['category'] ?? '') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><button type="submit">Cari Produk</button></form></section>
        <nav class="category-nav" aria-label="Kategori produk"><span class="category-label">Pilih kategori:</span><a class="category-chip <?= $category === '' ? 'active' : '' ?>" href="index.php">Semua</a><?php foreach ($categories as $item): ?><a class="category-chip <?= $category === $item ? 'active' : '' ?>" href="?category=<?= urlencode($item) ?>"><?= e($item) ?></a><?php endforeach; ?></nav>
        <?php if (!$groupedProducts): ?><section class="panel empty-cart"><h2>Produk tidak ditemukan</h2><p class="muted">Coba gunakan kata kunci atau kategori lain.</p></section><?php endif; ?>
        <?php foreach ($groupedProducts as $groupName => $groupProducts): ?><section class="category-section"><div class="category-heading"><div><span class="eyebrow">ETALASE WARUNG BAGAS</span><h2><?= e($groupName) ?></h2><p class="muted"><?= e($categoryDescriptions[$groupName] ?? 'Produk pilihan untuk kebutuhan Anda.') ?></p></div><span class="category-count"><?= count($groupProducts) ?> produk</span></div><section class="grid"><?php foreach ($groupProducts as $product): ?><article class="product"><a href="product.php?id=<?= (int) $product['id'] ?>"><img class="product-image" src="<?= e($product['image_url'] ?? '') ?>" alt="<?= e($product['name']) ?>" loading="lazy"></a><div class="product-content"><span class="badge"><?= e($product['category']) ?></span><h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3><p class="product-description"><?= e($product['description'] ?? 'Produk berkualitas pilihan untuk kebutuhan Anda.') ?></p><p class="price">Rp <?= number_format($product['price'], 0, ',', '.') ?></p><p class="muted">Stok tersedia: <?= $product['stock'] ?></p><?php if ($product['stock'] > 0): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><button type="submit">Tambah ke keranjang</button></form><?php else: ?><p class="muted">Stok habis.</p><?php endif; ?></div></article><?php endforeach; ?></section></section><?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
