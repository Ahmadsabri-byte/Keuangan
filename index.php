
<?php
session_start();

$file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "data_keuangan.json";

if (!isset($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION["pesan"])) {
    $_SESSION["pesan"] = "";
}

function bacaData($file) {
    if (!file_exists($file)) {
        return [];
    }

    $isi = file_get_contents($file);
    $data = json_decode($isi, true);

    return is_array($data) ? $data : [];
}

function simpanData($file, $data) {
    $json = json_encode(
        array_values($data),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    if (file_put_contents($file, $json, LOCK_EX) === false) {
        return false;
    }

    return true;
}

function aman($teks) {
    return htmlspecialchars(
        (string)$teks,
        ENT_QUOTES,
        "UTF-8"
    );
}

function rupiah($angka) {
    return "Rp " . number_format(
        (float)$angka,
        0,
        ",",
        "."
    );
}

function kembali() {
    header("Location: index.php");
    exit;
}

$data = bacaData($file);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid. Muat ulang halaman.");
    }

    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "tambah") {
        $jenis = $_POST["jenis"] ?? "";
        $keterangan = trim($_POST["keterangan"] ?? "");
        $nominal = $_POST["nominal"] ?? "";
        $tanggal = $_POST["tanggal"] ?? "";

        $tgl = is_string($tanggal)
            ? DateTime::createFromFormat("!Y-m-d", $tanggal)
            : false;

        if (
            !in_array($jenis, ["pemasukan", "pengeluaran"], true) ||
            !is_string($keterangan) ||
            $keterangan === "" ||
            strlen($keterangan) > 150 ||
            !$tgl ||
            $tgl->format("Y-m-d") !== $tanggal ||
            !is_string($nominal) ||
            !ctype_digit($nominal) ||
            (float)$nominal < 1 ||
            (float)$nominal > 999999999999999
        ) {
            $_SESSION["pesan"] = "Data transaksi tidak valid.";
            kembali();
        }

        $data[] = [
            "id" => bin2hex(random_bytes(8)),
            "jenis" => $jenis,
            "keterangan" => $keterangan,
            "nominal" => (float)$nominal,
            "tanggal" => $tanggal
        ];

        if (simpanData($file, $data)) {
            $_SESSION["pesan"] = "Transaksi berhasil ditambahkan!";
        } else {
            $_SESSION["pesan"] = "Gagal menyimpan data.";
        }

        kembali();
    }

    if ($aksi === "hapus") {
        $id = $_POST["id"] ?? "";

        if (!is_string($id)) {
            $_SESSION["pesan"] = "ID transaksi tidak valid.";
            kembali();
        }

        $dataBaru = array_values(array_filter(
            $data,
            fn($item) => ($item["id"] ?? "") !== $id
        ));

        if (count($dataBaru) === count($data)) {
            $_SESSION["pesan"] = "Transaksi tidak ditemukan.";
        } elseif (simpanData($file, $dataBaru)) {
            $_SESSION["pesan"] = "Transaksi berhasil dihapus!";
        } else {
            $_SESSION["pesan"] = "Gagal menghapus transaksi.";
        }

        kembali();
    }
}

$pesan = $_SESSION["pesan"];
$_SESSION["pesan"] = "";

$pemasukan = 0;
$pengeluaran = 0;

foreach ($data as $item) {
    if (($item["jenis"] ?? "") === "pemasukan") {
        $pemasukan += (float)$item["nominal"];
    } elseif (($item["jenis"] ?? "") === "pengeluaran") {
        $pengeluaran += (float)$item["nominal"];
    }
}

$saldo = $pemasukan - $pengeluaran;

usort($data, function ($a, $b) {
    return strcmp($b["tanggal"], $a["tanggal"]);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KeuanganKu - Catatan Keuangan</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    line-height: 1.6;
}

.container {
    max-width: 1100px;
    margin: auto;
    padding: 30px 20px;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}

header h1 {
    font-size: 32px;
    margin: 5px 0;
}

header p {
    color: #64748b;
}

.eyebrow {
    color: #2563eb;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 2px;
}

.icon {
    background: #dbeafe;
    padding: 15px;
    border-radius: 18px;
    font-size: 35px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 23px;
    border-radius: 15px;
    border-left: 5px solid;
    box-shadow: 0 5px 18px #0f172a08;
}

.card p {
    color: #64748b;
    font-size: 14px;
}

.card h2 {
    font-size: clamp(18px, 2vw, 25px);
    margin: 12px 0;
    overflow-wrap: anywhere;
}

.card span {
    color: #64748b;
    font-size: 12px;
}

.green {
    border-color: #16a34a;
}
.green h2 {
    color: #15803d;
}

.red {
    border-color: #dc2626;
}
.red h2 {
    color: #dc2626;
}

.blue {
    border-color: #2563eb;
}
.blue h2 {
    color: #2563eb;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 18px #0f172a08;
}

.panel h2 {
    font-size: 21px;
    margin-bottom: 5px;
}

.muted {
    color: #64748b;
    font-size: 14px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin: 22px 0;
}

label {
    display: block;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 7px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font-size: 14px;
}

input:focus, select:focus {
    outline: 2px solid #93c5fd;
    border-color: #2563eb;
}

.btn {
    padding: 13px 20px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
}

.btn:hover {
    background: #1d4ed8;
}

.alert {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 12px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
}

.table-wrap {
    overflow-x: auto;
    margin-top: 20px;
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.badge.pemasukan {
    background: #dcfce7;
    color: #15803d;
}

.badge.pengeluaran {
    background: #fee2e2;
    color: #b91c1c;
}

.income-text {
    color: #15803d;
    font-weight: bold;
    white-space: nowrap;
}

.expense-text {
    color: #dc2626;
    font-weight: bold;
    white-space: nowrap;
}

.delete-btn {
    background: #fee2e2;
    color: #b91c1c;
    border: none;
    border-radius: 7px;
    padding: 8px 12px;
    cursor: pointer;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #64748b;
}

footer {
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    padding: 15px;
}

@media (max-width: 750px) {
    .cards {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 550px) {
    .container {
        padding: 20px 12px;
    }

    header h1 {
        font-size: 25px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .panel, .card {
        padding: 18px;
    }

    .icon {
        font-size: 25px;
        padding: 10px;
    }
}
</style>
</head>

<body>
<div class="container">

<header>
    <div>
        <div class="eyebrow">MANAJEMEN KEUANGAN</div>
        <h1>KeuanganKu</h1>
        <p>Catat dan kelola keuanganmu dengan mudah.</p>
    </div>
    <div class="icon">💰</div>
</header>

<?php if ($pesan !== ""): ?>
    <div class="alert"><?= aman($pesan) ?></div>
<?php endif; ?>

<div class="cards">
    <div class="card green">
        <p>Total Pemasukan</p>
        <h2><?= rupiah($pemasukan) ?></h2>
        <span>↗ Uang masuk</span>
    </div>

    <div class="card red">
        <p>Total Pengeluaran</p>
        <h2><?= rupiah($pengeluaran) ?></h2>
        <span>↘ Uang keluar</span>
    </div>

    <div class="card blue">
        <p>Saldo Saat Ini</p>
        <h2><?= rupiah($saldo) ?></h2>
        <span>Saldo bersih</span>
    </div>
</div>

<section class="panel">
    <h2>Tambah Transaksi</h2>
    <p class="muted">Masukkan detail pemasukan atau pengeluaran.</p>

    <form method="POST">
        <input type="hidden" name="csrf"
               value="<?= aman($_SESSION["csrf"]) ?>">
        <input type="hidden" name="aksi" value="tambah">

        <div class="form-grid">
            <div>
                <label for="jenis">Jenis Transaksi</label>
                <select name="jenis" id="jenis" required>
                    <option value="pemasukan">Pemasukan</option>
                    <option value="pengeluaran">Pengeluaran</option>
                </select>
            </div>

            <div>
                <label for="tanggal">Tanggal</label>
                <input type="date" name="tanggal" id="tanggal"
                       value="<?= date("Y-m-d") ?>" required>
            </div>

            <div>
                <label for="keterangan">Keterangan</label>
                <input type="text" name="keterangan" id="keterangan"
                       maxlength="150"
                       placeholder="Contoh: Uang saku" required>
            </div>

            <div>
                <label for="nominal">Nominal (Rp)</label>
                <input type="number" name="nominal" id="nominal"
                       min="1" max="999999999999999" step="1"
                       placeholder="50000" required>
            </div>
        </div>

        <button type="submit" class="btn">+ Simpan Transaksi</button>
    </form>
</section>

<section class="panel">
    <h2>Riwayat Transaksi</h2>
    <p class="muted">Daftar pemasukan dan pengeluaran yang tercatat.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if (count($data) > 0): ?>
                <?php foreach ($data as $item): ?>
                    <tr>
                        <td><?= aman($item["tanggal"]) ?></td>
                        <td><?= aman($item["keterangan"]) ?></td>
                        <td>
                            <span class="badge <?= aman($item["jenis"]) ?>">
                                <?= ucfirst(aman($item["jenis"])) ?>
                            </span>
                        </td>
                        <td class="<?= $item["jenis"] === "pemasukan"
                            ? "income-text" : "expense-text" ?>">
                            <?= rupiah($item["nominal"]) ?>
                        </td>
                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Hapus transaksi ini?')">
                                <input type="hidden" name="csrf"
                                       value="<?= aman($_SESSION["csrf"]) ?>">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id"
                                       value="<?= aman($item["id"]) ?>">
                                <button class="delete-btn" type="submit">
                                    Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty">
                        Belum ada transaksi. Silakan tambahkan transaksi pertama.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<footer>
    KeuanganKu &copy; <?= date("Y") ?> — Aplikasi Catatan Keuangan
</footer>

</div>
</body>
</html>