<?php
session_start();
include 'koneksi.php';

// --- Pengecekan Akses ---
// Hanya admin, manajer, atau kasir yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manajer', 'kasir'])) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['role'];
$user_id_toko = isset($_SESSION['id_toko']) ? intval($_SESSION['id_toko']) : 0; // ID toko pengguna saat ini

$filter_toko_id = isset($_GET['filter_toko_id']) ? intval($_GET['filter_toko_id']) : 0;
$filter_nama_barang = isset($_GET['filter_nama_barang']) ? mysqli_real_escape_string($conn, $_GET['filter_nama_barang']) : '';

// Jika pengguna adalah kasir, otomatis filter berdasarkan toko mereka dan nonaktifkan pilihan toko lain
if ($user_role === 'kasir') {
    $filter_toko_id = $user_id_toko;
}

// Query untuk mengambil data toko (untuk dropdown filter)
// Jika kasir, hanya tampilkan toko mereka sendiri
$sql_query_toko = "SELECT id_toko, nama_toko FROM toko";
if ($user_role === 'kasir') {
    $sql_query_toko .= " WHERE id_toko = $user_id_toko";
}
$sql_query_toko .= " ORDER BY nama_toko ASC";
$query_toko = mysqli_query($conn, $sql_query_toko);


// Query untuk mengambil data stok barang
// Gabungkan tabel barang, stoktoko, dan toko untuk mendapatkan nama barang, nama toko, jumlah stok, dan tanggal update
// Menggunakan 'st.tgl_update_stok' sesuai dengan struktur database yang diberikan
$sql_stok = "
    SELECT 
        b.id_barang,
        b.nama_barang,
        t.nama_toko,
        st.jumlah_stok,
        st.tgl_update_stok as tanggal_update_stok_terakhir
    FROM 
        barang b
    JOIN 
        stoktoko st ON b.id_barang = st.id_barang
    JOIN 
        toko t ON st.id_toko = t.id_toko
";

$conditions = [];

// Tambahkan kondisi WHERE jika ada filter toko (untuk admin/manajer) atau otomatis untuk kasir
if ($filter_toko_id > 0) {
    $conditions[] = "st.id_toko = $filter_toko_id";
}

// Tambahkan kondisi WHERE jika ada filter nama barang
if (!empty($filter_nama_barang)) {
    $conditions[] = "b.nama_barang LIKE '%$filter_nama_barang%'";
}

if (count($conditions) > 0) {
    $sql_stok .= " WHERE " . implode(" AND ", $conditions);
}

$sql_stok .= " ORDER BY b.nama_barang ASC, t.nama_toko ASC";

$result_stok = mysqli_query($conn, $sql_stok);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Stok Barang Per Toko</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 960px; }
        .card { border-radius: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .table thead th { background-color: #343a40; color: white; }
        .table tbody tr:hover { background-color: #f2f2f2; }
        .form-select, .form-control { border-radius: 8px; }
        .btn-outline-secondary { border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify_content-between align-items-center mb-4">
            <h2 class="mb-0">Data Stok Barang Per Toko</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">← Kembali ke Dashboard</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="stok_toko.php" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label for="filter_toko_id" class="form-label">Filter Toko:</label>
                        <select name="filter_toko_id" id="filter_toko_id" class="form-select" <?= ($user_role === 'kasir') ? 'disabled' : '' ?> onchange="this.form.submit()">
                            <?php if ($user_role !== 'kasir'): // Opsi 'Semua Toko' hanya untuk Admin/Manajer ?>
                                <option value="0">-- Semua Toko --</option>
                            <?php endif; ?>
                            <?php while ($toko = mysqli_fetch_assoc($query_toko)): ?>
                                <option value="<?= $toko['id_toko'] ?>" <?= ($filter_toko_id == $toko['id_toko']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($toko['nama_toko']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($user_role === 'kasir'): ?>
                            <input type="hidden" name="filter_toko_id" value="<?= $user_id_toko ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label for="filter_nama_barang" class="form-label">Filter Nama Barang:</label>
                        <input type="text" name="filter_nama_barang" id="filter_nama_barang" class="form-control" placeholder="Cari Nama Barang..." value="<?= htmlspecialchars($filter_nama_barang) ?>">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary mt-4">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Barang</th>
                            <th>Nama Barang</th>
                            <th>Nama Toko</th>
                            <th>Jumlah Stok</th>
                            <th>Tanggal Update Terakhir</th>
                            <?php if (in_array($user_role, ['admin', 'manajer'])): ?>
                            <th style="width:150px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_stok) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_stok)): ?>
                            <tr>
                                <td><?= $row['id_barang'] ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_toko']) ?></td>
                                <td><?= $row['jumlah_stok'] ?></td>
                                <td><?= $row['tanggal_update_stok_terakhir'] ?? 'N/A' ?></td>
                                <?php if (in_array($user_role, ['admin', 'manajer'])): ?>
                                <td>
                                    <a href="barang_edit.php?id=<?= $row['id_barang'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="barang_hapus.php?id=<?= $row['id_barang'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus barang ini?')">Hapus</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= (in_array($user_role, ['admin', 'manajer'])) ? '6' : '5' ?>" class="text-center">Tidak ada data stok ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
