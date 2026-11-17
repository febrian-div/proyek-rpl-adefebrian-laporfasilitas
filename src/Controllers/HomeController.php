<?php
namespace App\Controllers;

use App\Models\Category;
use App\Models\Report;

class HomeController {
    public function index(): void {
        $categories = [
            ['name' => 'Jalan & Trotoar', 'icon' => 'fa-road', 'description' => 'Jalan berlubang, aspal amblas, dan trotoar rusak.'],
            ['name' => 'Penerangan Jalan Umum (PJU)', 'icon' => 'fa-lightbulb', 'description' => 'Lampu jalan mati, tiang miring, dan kabel bahaya.'],
            ['name' => 'Drainase & Saluran Air', 'icon' => 'fa-water', 'description' => 'Got mampet, saluran meluap, dan penutup got rusak.'],
            ['name' => 'Taman & Ruang Terbuka', 'icon' => 'fa-tree', 'description' => 'Fasilitas taman rusak dan pohon rawan tumbang.'],
            ['name' => 'Fasilitas Transportasi & Halte', 'icon' => 'fa-bus', 'description' => 'Halte rusak dan rambu lalu lintas roboh.'],
            ['name' => 'Sanitasi & Toilet Umum', 'icon' => 'fa-restroom', 'description' => 'Toilet publik rusak atau pipa air bocor.']
        ];
        $recentReports = Report::getAll(['limit' => 6]);
        
        view('home', [
            'title' => 'LaporFasilitas - Sistem Pelaporan Fasilitas Publik Berbasis AI',
            'categories' => $categories,
            'recentReports' => $recentReports,
        ]);
    }
}
