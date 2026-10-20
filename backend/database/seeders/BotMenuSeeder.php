<?php

namespace Database\Seeders;

use App\Models\BotMenu;
use Illuminate\Database\Seeder;

class BotMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = [
            // Catat Pengeluaran
            ['category' => 'Catat Pengeluaran', 'category_icon' => '💸', 'command' => 'keluar 25000 makan siang', 'description' => null, 'is_quick_command' => true, 'sort_order' => 1],
            ['category' => 'Catat Pengeluaran', 'category_icon' => '💸', 'command' => 'beli bensin 50rb via bca', 'description' => null, 'is_quick_command' => false, 'sort_order' => 2],
            ['category' => 'Catat Pengeluaran', 'category_icon' => '💸', 'command' => 'bayar listrik 150.000', 'description' => null, 'is_quick_command' => false, 'sort_order' => 3],
            ['category' => 'Catat Pengeluaran', 'category_icon' => '💸', 'command' => '-35k parkir', 'description' => null, 'is_quick_command' => false, 'sort_order' => 4],

            // Catat Pemasukan
            ['category' => 'Catat Pemasukan', 'category_icon' => '💰', 'command' => 'masuk 500000 gaji', 'description' => null, 'is_quick_command' => true, 'sort_order' => 10],
            ['category' => 'Catat Pemasukan', 'category_icon' => '💰', 'command' => 'terima 250k bonus ke bca', 'description' => null, 'is_quick_command' => false, 'sort_order' => 11],
            ['category' => 'Catat Pemasukan', 'category_icon' => '💰', 'command' => '+2jt freelance website client A', 'description' => null, 'is_quick_command' => false, 'sort_order' => 12],
            ['category' => 'Catat Pemasukan', 'category_icon' => '💰', 'command' => 'jual paket nasi 750rb', 'description' => 'omzet UMKM', 'is_quick_command' => false, 'sort_order' => 13],

            // Dompet & Saldo
            ['category' => 'Dompet & Saldo', 'category_icon' => '👛', 'command' => 'saldo', 'description' => 'cek rincian saldo per dompet', 'is_quick_command' => true, 'sort_order' => 20],
            ['category' => 'Dompet & Saldo', 'category_icon' => '👛', 'command' => 'tambah dompet BCA saldo 1jt', 'description' => null, 'is_quick_command' => false, 'sort_order' => 21],
            ['category' => 'Dompet & Saldo', 'category_icon' => '👛', 'command' => 'transfer 200rb dari Cash ke BCA', 'description' => null, 'is_quick_command' => false, 'sort_order' => 22],

            // Plafon Anggaran (Budget)
            ['category' => 'Plafon Anggaran (Budget)', 'category_icon' => '🎯', 'command' => 'budget makan 1jt', 'description' => null, 'is_quick_command' => false, 'sort_order' => 30],
            ['category' => 'Plafon Anggaran (Budget)', 'category_icon' => '🎯', 'command' => 'cek budget', 'description' => 'status plafon & bar terpakai', 'is_quick_command' => false, 'sort_order' => 31],

            // Langganan & Transaksi Rutin
            ['category' => 'Langganan & Transaksi Rutin', 'category_icon' => '⏰', 'command' => 'langganan Netflix 186rb setiap tanggal 15 via dana', 'description' => null, 'is_quick_command' => false, 'sort_order' => 40],
            ['category' => 'Langganan & Transaksi Rutin', 'category_icon' => '⏰', 'command' => 'daftar langganan', 'description' => null, 'is_quick_command' => false, 'sort_order' => 41],
            ['category' => 'Langganan & Transaksi Rutin', 'category_icon' => '⏰', 'command' => 'hapus langganan Netflix', 'description' => null, 'is_quick_command' => false, 'sort_order' => 42],

            // Target Tabungan (Goals)
            ['category' => 'Target Tabungan (Goals)', 'category_icon' => '🎯', 'command' => 'buat target Mobil 100jt', 'description' => null, 'is_quick_command' => false, 'sort_order' => 50],
            ['category' => 'Target Tabungan (Goals)', 'category_icon' => '🎯', 'command' => 'tabung 1jt untuk Mobil via bca', 'description' => null, 'is_quick_command' => false, 'sort_order' => 51],
            ['category' => 'Target Tabungan (Goals)', 'category_icon' => '🎯', 'command' => 'daftar target', 'description' => null, 'is_quick_command' => false, 'sort_order' => 52],

            // Laporan & Analisis
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'rekap hari ini', 'description' => null, 'is_quick_command' => false, 'sort_order' => 60],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'rekap bulan ini', 'description' => null, 'is_quick_command' => false, 'sort_order' => 61],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'pengeluaran hari ini', 'description' => null, 'is_quick_command' => false, 'sort_order' => 62],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'pemasukan hari ini', 'description' => null, 'is_quick_command' => false, 'sort_order' => 63],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'bulan ini boros gak?', 'description' => 'analisis MoM', 'is_quick_command' => false, 'sort_order' => 64],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'omzet hari ini', 'description' => 'laba UMKM', 'is_quick_command' => false, 'sort_order' => 65],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'rekap freelance', 'description' => 'total pendapatan proyek', 'is_quick_command' => false, 'sort_order' => 66],
            ['category' => 'Laporan & Analisis', 'category_icon' => '📊', 'command' => 'export excel', 'description' => 'unduh sheet laporan', 'is_quick_command' => false, 'sort_order' => 67],

            // Web Dashboard & Pengaturan
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'web', 'description' => 'link dashboard grafik & analitik', 'is_quick_command' => true, 'sort_order' => 70],
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'koreksi 35rb', 'description' => 'ubah nominal transaksi terakhir', 'is_quick_command' => false, 'sort_order' => 71],
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'batal', 'description' => 'batalkan transaksi terakhir', 'is_quick_command' => false, 'sort_order' => 72],
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'set pin 123456', 'description' => null, 'is_quick_command' => false, 'sort_order' => 73],
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'kategori', 'description' => null, 'is_quick_command' => false, 'sort_order' => 74],
            ['category' => 'Web Dashboard & Pengaturan', 'category_icon' => '🌐', 'command' => 'tambah kategori <nama>', 'description' => null, 'is_quick_command' => false, 'sort_order' => 75],

            // Quick Menu item
            ['category' => 'Panduan Lengkap', 'category_icon' => '📚', 'command' => 'menu', 'description' => 'panduan lengkap seluruh fitur', 'is_quick_command' => true, 'sort_order' => 80],
        ];

        BotMenu::truncate();

        foreach ($menus as $menu) {
            BotMenu::create($menu);
        }
    }
}
