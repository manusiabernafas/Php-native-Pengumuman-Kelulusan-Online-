<?php
// Dipakai hitung mundur untuk menyamakan jam perangkat siswa dengan jam server
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['server' => round(microtime(true) * 1000), 'buka' => strtotime(setelan('waktu_buka')) * 1000, 'status' => status_pengumuman()]);
