<?php
require_once 'config.php';
$pdo = get_db_connection();
$officials = $pdo->query('SELECT * FROM barangay_officials WHERE is_active = 1 ORDER BY rank ASC')->fetchAll();

$page_title = 'Barangay Officials';
require_once __DIR__ . '/partials/user_dashboard_header.php';
?>

<div class="admin-content p-4">
    <div class="text-center mb-5">
        <span class="d-inline-block py-1 px-3 rounded-pill fw-bold text-uppercase small tracking-wide mb-3" style="background: rgba(20, 184, 166, 0.1); color: #14b8a6;">
            <i class="fas fa-user-shield me-1"></i> Governance
        </span>
        <h1 class="display-5 fw-bold mb-2">Barangay Officials</h1>
        <p class="text-muted lead">Dedicated leaders serving the community of Barangay Panungyanan.</p>
    </div>

    <!-- CAPTAIN -->
    <?php 
    $captain = null;
    $councilors = [];
    foreach ($officials as $o) {
        if ($o['position'] === 'Barangay Captain') $captain = $o;
        else $councilors[] = $o;
    }
    ?>

    <?php if ($captain): ?>
    <div class="official-item mb-5 text-center">
        <div class="photo-wrapper captain-photo">
            <?php $c_img = $captain['image_path'] ?: 'public/img/barangaylogo.png'; ?>
            <img src="<?php echo $c_img; ?>" class="w-100 h-100 object-fit-contain" alt="Captain">
        </div>
        <h5 class="official-name"><?php echo htmlspecialchars($captain['name']); ?></h5>
        <div class="official-pos"><?php echo htmlspecialchars($captain['position']); ?></div>
    </div>
    <?php endif; ?>

    <!-- COUNCILORS & OTHERS -->
    <div class="council-grid">
        <?php foreach ($councilors as $c): ?>
            <div class="official-item council-item text-center">
                <div class="photo-wrapper member-photo">
                    <?php $o_img = $c['image_path'] ?: 'public/img/barangaylogo.png'; ?>
                    <img src="<?php echo $o_img; ?>" class="w-100 h-100 object-fit-contain" alt="Official">
                </div>
                <h6 class="official-name"><?php echo htmlspecialchars($c['name']); ?></h6>
                <div class="official-pos"><?php echo htmlspecialchars($c['position']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0" style="max-width: 500px; margin: 0 auto;">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1060; width: 0.5rem; height: 0.5rem;"></button>
                <img id="modalFullImage" src="" class="img-fluid rounded-3 shadow-lg" style="max-height: 70vh; width: auto; border: 6px solid #fff;">
                <div class="mt-3 py-2 px-3 rounded-pill d-inline-block" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);">
                    <h5 id="modalOfficialName" class="text-white fw-bold mb-0" style="font-size: 1rem;"></h5>
                    <p id="modalOfficialPos" class="text-teal fw-bold text-uppercase mb-0" style="font-size: 0.65rem; letter-spacing: 1.5px;"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .official-item { margin-bottom: 2rem; transition: transform 0.3s ease; cursor: pointer; }
    .official-item:hover { transform: scale(1.05); }
    
    .photo-wrapper { margin: 0 auto 1rem; border: 4px solid #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.1); background: #f8fafc; overflow: hidden; border-radius: 8px; }
    .captain-photo { width: 220px; height: 280px; }
    .member-photo { width: 140px; height: 180px; }
    
    .official-name { font-weight: 800; margin-bottom: 0.2rem; font-size: 0.95rem; text-transform: uppercase; color: #1e293b; }
    .official-pos { font-size: 0.7rem; font-weight: 700; color: #475569; opacity: 0.9; letter-spacing: 1px; text-transform: uppercase; }

    .council-grid { max-width: 900px; margin: 3rem auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 2rem; }
    .council-item { width: calc(25% - 2rem); min-width: 180px; flex-shrink: 0; padding: 15px; border-radius: 15px; background: #fff; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .text-teal { color: #475569 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
    const modalImage = document.getElementById('modalFullImage');
    const modalName = document.getElementById('modalOfficialName');
    const modalPos = document.getElementById('modalOfficialPos');

    document.querySelectorAll('.official-item').forEach(item => {
        item.addEventListener('click', function() {
            const imgSrc = this.querySelector('img').src;
            const name = this.querySelector('.official-name').textContent;
            const pos = this.querySelector('.official-pos').textContent;

            modalImage.src = imgSrc;
            modalName.textContent = name;
            modalPos.textContent = pos;
            photoModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/partials/user_dashboard_footer.php'; ?>
