<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();

$message = '';
$messageType = 'alert';
$editProduct = null;
$categories = ['Sembako', 'Minuman', 'Makanan', 'Kebutuhan Rumah', 'Aksesoris', 'Elektronik', 'Umum'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'delete') {
        if ($productId < 1) {
            $message = 'Produk tidak valid.';
        } else {
            try {
                $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
                $statement->execute([$productId]);
                $deleted = $statement->rowCount();
                $message = $deleted ? 'Produk berhasil dihapus.' : 'Produk tidak ditemukan.';
                $messageType = $deleted ? 'success' : 'alert';
            } catch (PDOException $error) {
                $message = 'Produk yang sudah masuk pesanan tidak dapat dihapus. Ubah stok menjadi 0 jika ingin menonaktifkannya.';
            }
        }
    } elseif ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

        if ($name === '' || $category === '' || $price === false || $price < 0 || $stock === false || $stock < 0) {
            $message = 'Nama, kategori, harga, dan stok harus diisi dengan benar.';
        } elseif ($productId > 0) {
            $statement = $pdo->prepare('UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, image_url = ? WHERE id = ?');
            $statement->execute([$name, $category, $description, $price, $stock, $imageUrl, $productId]);
            $message = 'Produk berhasil diperbarui.';
            $messageType = 'success';
        } else {
            $statement = $pdo->prepare('INSERT INTO products (name, category, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([$name, $category, $description, $price, $stock, $imageUrl]);
            $message = 'Produk berhasil ditambahkan.';
            $messageType = 'success';
        }
    }
}

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $statement->execute([(int) $_GET['edit']]);
    $editProduct = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
}

$products = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk | WARUNG BAGAS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><div class="container topbar"><a class="brand" href="index.php">WARUNG BAGAS</a><nav><a href="admin.php">Dashboard</a><a href="logout.php">Keluar</a></nav></div></header>
<main class="container">
    <div class="page-heading"><div><p class="eyebrow">ADMINISTRASI</p><h1>Kelola Produk</h1><p class="muted">Tambah, ubah, dan hapus katalog WARUNG BAGAS.</p></div><a class="back-link" href="admin.php">Kembali ke dashboard</a></div>
    <?php if ($message): ?><div class="alert <?= $messageType === 'success' ? 'success' : '' ?>"><?= e($message) ?></div><?php endif; ?>
    <section class="panel">
        <h2><?= $editProduct ? 'Edit Produk' : 'Tambah Produk' ?></h2>
        <form method="post" class="product-form"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="product_id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
            <div class="two-columns"><div><label>Nama produk</label><input name="name" value="<?= e($editProduct['name'] ?? '') ?>" required><label>Kategori</label><select name="category" required><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>" <?= ($editProduct['category'] ?? '') === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select><label>Deskripsi</label><textarea name="description" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea></div><div><label>Harga</label><input type="number" name="price" min="0" value="<?= (int) ($editProduct['price'] ?? 0) ?>" required><label>Stok</label><input type="number" name="stock" min="0" value="<?= (int) ($editProduct['stock'] ?? 0) ?>" required><label>URL gambar</label><input type="url" name="image_url" value="<?= e($editProduct['image_url'] ?? '') ?>" placeholder="https://..."></div></div>
            <button type="submit"><?= $editProduct ? 'Simpan Perubahan' : 'Tambah Produk' ?></button><?php if ($editProduct): ?> <a class="button secondary" href="admin_products.php">Batal</a><?php endif; ?>
        </form>
    </section>
    <section class="panel"><div class="section-heading"><h2>Daftar Produk</h2><span><?= count($products) ?> produk</span></div><div class="cart-table-wrap"><table><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr><?php foreach ($products as $product): ?><tr><td><strong><?= e($product['name']) ?></strong></td><td><?= e($product['category']) ?></td><td>Rp <?= number_format($product['price'], 0, ',', '.') ?></td><td><?= (int) $product['stock'] ?></td><td><a class="button secondary" href="?edit=<?= (int) $product['id'] ?>">Edit</a><form method="post" class="inline-form" onsubmit="return confirm('Hapus produk ini?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="danger" type="submit">Hapus</button></form></td></tr><?php endforeach; ?></table></div></section>
</main>
</body>
</html>
