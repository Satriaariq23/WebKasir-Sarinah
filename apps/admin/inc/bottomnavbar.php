<?php
$levelId = (int)($_settings->userdata('level_id') ?? 0);
$bottomItems = [
    ['Home', 'home', 'bi bi-house', [1, 2, 3]],
    ['Barang', 'barang', 'bi bi-box-seam', [1]],
    ['Pelanggan', 'pelanggan', 'bi bi-person-badge', [1]],
    ['Supplier', 'supplier', 'bi bi-truck', [1]],
    ['Kasir', 'penjualan/kasir', 'bi bi-basket2', [1, 2]],
    ['Sales', 'penjualan', 'bi bi-cart-check', [1, 3]],
];
?>

<nav class="navbar bg-white navbar-expand fixed-bottom d-lg-none d-xl-none p-0">
    <ul class="navbar-nav nav-justified w-100">
        <?php foreach ($bottomItems as [$label, $page, $icon, $roles]): ?>
            <?php if (in_array($levelId, $roles, true) && can_access_admin_page($page)): ?>
                <li class="nav-item">
                    <a href="<?php echo base_url ?>admin/?page=<?php echo $page ?>" class="nav-link text-center">
                        <i class="<?php echo $icon ?>" style="font-size: 2rem;"></i>
                        <span class="d-block"><?php echo $label ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</nav>
