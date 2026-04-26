<?php 
require_once __DIR__ . '/config.php';
$page_title = 'Announcements';

// Always use the public website header for this page
require_once __DIR__ . '/partials/header.php';

$pdo = get_db_connection();
$announcements = $pdo->query("SELECT id, title, content, media_path, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
?>

<div class="container py-5">
<section class="py-5" style="min-height: 80vh;">
	<div class="container">
		<!-- Page Header -->
		<div class="text-center mb-5">
			<span class="d-inline-block py-1 px-3 rounded-pill bg-warning bg-opacity-10 text-warning fw-bold text-uppercase small tracking-wide mb-3">
				<i class="fas fa-bullhorn me-1"></i> Announcements
			</span>
			<h1 class="display-5 fw-bold mb-2">Barangay Announcements</h1>
			<p class="text-secondary lead">Stay updated with the latest news and announcements from your barangay.</p>
		</div>

		<?php if (empty($announcements)): ?>
			<!-- Empty State -->
			<div class="text-center py-5">
				<div class="mb-4">
					<i class="fas fa-bullhorn fa-4x text-muted opacity-25"></i>
				</div>
				<h4 class="text-muted fw-semibold">No Announcements Yet</h4>
				<p class="text-secondary">There are currently no announcements. Check back later!</p>
			</div>
		<?php else: ?>
			<div class="row g-4">
				<?php foreach ($announcements as $ann): ?>
					<div class="col-md-6 col-lg-4">
						<div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden announcement-card" 
                             style="transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer;"
                             data-bs-toggle="modal" 
                             data-bs-target="#announcementModal"
                             data-title="<?php echo htmlspecialchars($ann['title']); ?>"
                             data-content="<?php echo htmlspecialchars($ann['content']); ?>"
                             data-media="<?php echo htmlspecialchars($ann['media_path']); ?>"
                             data-date="<?php echo date('M j, Y', strtotime($ann['created_at'])); ?>">
							<?php if ($ann['media_path']): ?>
								<div class="position-relative bg-light d-flex align-items-center justify-content-center" style="height: 220px;">
									<img src="<?php echo htmlspecialchars($ann['media_path']); ?>" alt="" class="card-img-top" style="height: 100%; width: 100%; object-fit: contain; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
									<div class="overlay-details d-flex align-items-center justify-content-center">
										<span class="btn btn-light btn-sm rounded-pill px-3 fw-bold shadow-sm">
											<i class="fas fa-eye me-1"></i>View Details
										</span>
									</div>
								</div>
							<?php endif; ?>
							<div class="card-body p-4">
								<div class="d-flex align-items-center gap-2 mb-3">
									<div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
										<i class="fas fa-bullhorn"></i>
									</div>
									<small class="text-muted">
										<i class="fas fa-calendar-alt me-1"></i>
										<?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
									</small>
								</div>
								<h5 class="fw-bold mb-3 text-truncate-2"><?php echo htmlspecialchars($ann['title']); ?></h5>
								<p class="text-secondary mb-0 text-truncate-3"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div id="modalMediaContainer" class="d-none bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="min-height: 300px;">
                <img id="modalMedia" src="" alt="" class="img-fluid" style="max-height: 500px; width: auto; object-fit: contain;">
            </div>
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <small id="modalDate" class="text-muted fw-semibold"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h3 id="modalTitle" class="fw-bold mb-4 text-dark h4"></h3>
                <div class="bg-light p-4 rounded-4">
                    <p id="modalContent" class="text-secondary mb-0" style="white-space: pre-line; line-height: 1.8;"></p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var announcementModal = document.getElementById('announcementModal');
    announcementModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var title = button.getAttribute('data-title');
        var content = button.getAttribute('data-content');
        var media = button.getAttribute('data-media');
        var date = button.getAttribute('data-date');

        var modalTitle = announcementModal.querySelector('#modalTitle');
        var modalContent = announcementModal.querySelector('#modalContent');
        var modalDate = announcementModal.querySelector('#modalDate');
        var modalMedia = announcementModal.querySelector('#modalMedia');
        var modalMediaContainer = announcementModal.querySelector('#modalMediaContainer');

        modalTitle.textContent = title;
        modalContent.textContent = content;
        modalDate.textContent = date;

        if (media && media !== "") {
            modalMedia.src = media;
            modalMediaContainer.classList.remove('d-none');
        } else {
            modalMediaContainer.classList.add('d-none');
        }
    });
});
</script>

<style>
	.announcement-card:hover {
		transform: translateY(-8px);
		box-shadow: 0 15px 35px rgba(0,0,0,0.12) !important;
	}
    .overlay-details {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .announcement-card:hover .overlay-details {
        opacity: 1;
    }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .text-truncate-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
