<?php
require 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$orderId = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare('SELECT orders.*, users.username, users.full_name, users.email FROM orders JOIN users ON users.id = orders.user_id WHERE orders.id = ?');
$statement->execute([$orderId]);
$order = $statement->fetch(PDO::FETCH_ASSOC);

$isAdmin = ($_SESSION['user']['role'] ?? 'customer') === 'admin';
if (!$order || (!$isAdmin && (int) $order['user_id'] !== (int) $_SESSION['user']['id'])) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}

$itemStatement = $pdo->prepare('SELECT order_items.*, products.name, products.category FROM order_items JOIN products ON products.id = order_items.product_id WHERE order_items.order_id = ? ORDER BY order_items.id');
$itemStatement->execute([$orderId]);
$items = $itemStatement->fetchAll(PDO::FETCH_ASSOC);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota #<?= $order['id'] ?> | WARUNG BAGAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="receipt-page">
<main class="receipt">
    <div class="receipt-actions no-print"><a class="back-link" href="orders.php">Kembali ke transaksi</a><button type="button" onclick="window.print()">Cetak Nota</button></div>
    <header class="receipt-header"><p class="eyebrow">NOTA PEMBELIAN</p><h1>WARUNG BAGAS</h1><p>Kebutuhan harian untuk keluarga</p></header>
    <div class="receipt-meta"><div><strong>Nota #<?= $order['id'] ?></strong><span><?= e($order['created_at']) ?></span></div><span class="badge"><?= e($order['status']) ?></span></div>
    <div class="receipt-customer"><strong><?= e($order['full_name'] ?: $order['username']) ?></strong><span><?= e($order['email']) ?></span><span><?= e($order['address']) ?></span></div>
    <table class="receipt-table"><thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?><tr><td><?= e($item['name']) ?><small><?= e($item['category']) ?></small></td><td><?= $item['quantity'] ?></td><td>Rp <?= number_format($item['price'], 0, ',', '.') ?></td><td>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php if (trim((string) $order['note']) !== ''): ?><div class="receipt-note"><strong>Catatan pembeli</strong><p><?= nl2br(e($order['note'])) ?></p></div><?php endif; ?>
    <div class="receipt-total"><span>Total pembayaran</span><strong>Rp <?= number_format($order['total'], 0, ',', '.') ?></strong></div>
    <footer>Terima kasih sudah berbelanja di WARUNG BAGAS.</footer>
</main>
</body>
</html>
