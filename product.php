<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$statement = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$statement->execute([$productId]);
$product = $statement->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $currentQuantity = (int) ($_SESSION['cart'][$productId] ?? 0);
    if ($currentQuantity + $quantity <= (int) $product['stock']) {
        $_SESSION['cart'][$productId] = $currentQuantity + $quantity;
        $message = 'Produk berhasil masuk ke keranjang.';
    } else {
        $message = 'Jumlah melebihi stok yang tersedia.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($product['name']) ?> | WARUNG BAGAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><div class="container topbar"><a class="brand" href="index.php">WARUNG BAGAS</a><nav><a href="products.php">Produk</a><a href="orders.php">Pesanan</a><a href="profile.php">Profil</a><a href="cart.php">Keranjang <span><?= array_sum($_SESSION['cart']) ?></span></a><a href="logout.php">Keluar</a></nav></div></header>
<main class="container">
    <p><a class="back-link" href="products.php">Kembali ke katalog</a></p>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?> <a href="cart.php">Buka keranjang</a></div><?php endif; ?>
    <section class="panel product-detail">
        <div class="two-columns">
            <img class="product-image" src="<?= e($product['image_url'] ?? '') ?>" alt="<?= e($product['name']) ?>">
            <div>
                <span class="badge"><?= e($product['category']) ?></span>
                <h1><?= e($product['name']) ?></h1>
                <p class="product-description"><?= e($product['description'] ?? 'Produk berkualitas pilihan WARUNG BAGAS.') ?></p>
                <p class="price">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                <p class="muted">Stok tersedia: <?= (int) $product['stock'] ?></p>
                <?php if ((int) $product['stock'] > 0): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><label for="quantity">Jumlah</label><input id="quantity" class="quantity" type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>"><button type="submit">Tambah ke keranjang</button></form>
                <?php else: ?><p class="muted">Stok habis.</p><?php endif; ?>
            </div>
        </div>
    </section>
</main>
</body>
</html>
