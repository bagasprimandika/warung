<?php
require 'config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = 'alert';
$receiptOrderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_cart') {
        verify_csrf();
        foreach ($_POST['quantity'] ?? [] as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = max(0, (int) $quantity);
            $statement = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
            $statement->execute([$productId]);
            $availableStock = (int) $statement->fetchColumn();
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

    if ($action === 'remove') {
        verify_csrf();
        unset($_SESSION['cart'][(int) ($_POST['product_id'] ?? 0)]);
        $message = 'Produk dihapus dari keranjang.';
        $messageType = 'success';
    }

    if ($action === 'checkout') {
        verify_csrf();
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
                    $receiptOrderId = $orderId;
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

$profileStatement = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$profileStatement->execute([$_SESSION['user']['id']]);
$profile = $profileStatement->fetch(PDO::FETCH_ASSOC);
$cartProducts = [];
$total = 0;
$cartCount = array_sum($_SESSION['cart']);

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang | WARUNG BAGAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="container topbar">
        <a class="brand" href="index.php">WARUNG BAGAS</a>
        <nav>
            <a href="products.php">Produk</a>
            <a href="logout.php">Keluar</a>
        </nav>
    </div>
</header>
<main class="container cart-page">
    <div class="page-heading">
        <div><p class="eyebrow">BELANJA</p><h1>Keranjang Anda</h1><p class="muted">Periksa produk sebelum menyelesaikan pembelian.</p></div>
        <a class="back-link" href="index.php">Kembali ke produk</a>
    </div>
    <?php if ($message): ?><div class="alert <?= $messageType === 'success' ? 'success' : '' ?>"><?= e($message) ?><?php if ($receiptOrderId): ?> <a class="alert-link" href="nota.php?id=<?= $receiptOrderId ?>">Lihat / cetak nota</a><?php endif; ?></div><?php endif; ?>

    <?php if (!$cartProducts): ?>
        <section class="panel empty-cart"><div class="empty-icon">+</div><h2>Keranjang masih kosong</h2><p class="muted">Pilih produk terlebih dahulu agar dapat melakukan checkout.</p><a class="button" href="index.php">Mulai Belanja</a></section>
    <?php else: ?>
        <div class="cart-layout">
            <section class="panel cart-items">
                <div class="section-heading"><h2>Produk Dipilih</h2><span><?= $cartCount ?> barang</span></div>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_cart">
                    <div class="cart-table-wrap"><table class="cart-table"><thead><tr><th>Produk</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th><th></th></tr></thead><tbody>
                    <?php foreach ($cartProducts as $product): $quantity = $_SESSION['cart'][$product['id']]; ?>
                        <tr><td><strong><?= e($product['name']) ?></strong><small><?= e($product['category']) ?></small></td><td>Rp <?= number_format($product['price'], 0, ',', '.') ?></td><td><input class="quantity" type="number" name="quantity[<?= $product['id'] ?>]" value="<?= $quantity ?>" min="0" max="<?= $product['stock'] + $quantity ?>"></td><td><strong>Rp <?= number_format($product['price'] * $quantity, 0, ',', '.') ?></strong></td><td><button class="danger icon-button" formaction="cart.php" name="action" value="remove" type="submit" title="Hapus produk">Hapus</button><input type="hidden" name="product_id" value="<?= $product['id'] ?>"></td></tr>
                    <?php endforeach; ?></tbody></table></div>
                    <div class="cart-actions"><button class="outline" type="submit">Perbarui Keranjang</button><strong>Total: Rp <?= number_format($total, 0, ',', '.') ?></strong></div>
                </form>
            </section>
            <section class="panel checkout-card" id="checkout">
                <p class="eyebrow">LANGKAH TERAKHIR</p><h2>Checkout</h2><p class="muted">Lengkapi detail pengiriman untuk menyelesaikan pesanan.</p>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><label>Nama penerima</label><input type="text" name="name" value="<?= e($profile['full_name'] ?? '') ?>" required><label>Nomor telepon</label><input type="text" name="phone" value="<?= e($profile['phone'] ?? '') ?>" required><label>Alamat lengkap</label><textarea name="address" rows="5" required><?= e($profile['address'] ?? '') ?></textarea><label>Catatan pesanan <span class="muted">(opsional)</span></label><textarea name="note" rows="3" maxlength="500" placeholder="Contoh: Tolong taruh di depan rumah."></textarea><div class="checkout-total"><span>Total pembayaran</span><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></div><button class="checkout-button" type="submit">Checkout Sekarang</button></form>
            </section>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
