<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') unset($_SESSION['siswa_id']);
redirect('beranda');
