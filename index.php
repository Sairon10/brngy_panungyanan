<?php
require_once __DIR__ . '/config.php';

// Check if logged in user needs RBI profile completion or ID verification
if (is_logged_in() && $_SESSION['role'] === 'resident') {
	$pdo = get_db_connection();
	$stmt = $pdo->prepare('SELECT verification_status, is_rbi_completed FROM residents WHERE user_id = ? LIMIT 1');
	$stmt->execute([$_SESSION['user_id']]);
	$resident = $stmt->fetch();

	if ($resident) {
		if (!$resident['is_rbi_completed']) {
			redirect('rbi_form.php');
		}
	}
}
// Fetch stats for the landing page
$sys_stats = ['residents' => 0, 'documents' => 0, 'incidents' => 0];
try {
	$stat_pdo = get_db_connection();
	$sys_stats['residents'] = (int)$stat_pdo->query("
		SELECT 
			(SELECT COUNT(*) FROM users WHERE role = 'resident') +
			(SELECT COUNT(*) FROM family_members) +
			(SELECT COUNT(*) FROM resident_records rr 
			 WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.role = 'resident' AND (u.email = rr.email OR u.full_name = rr.full_name)))
	")->fetchColumn();
	$sys_stats['documents'] = $stat_pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
	$sys_stats['incidents'] = $stat_pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
} catch (Exception $e) {
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
	<div class="container hero-content">
		<div class="row align-items-center min-vh-75">
			<div class="col-lg-6 order-lg-1 order-2 animate__animated animate__fadeInLeft">
				<div
					class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-light border border-secondary border-opacity-10 mb-4">
					<span class="d-inline-block width-2 height-2 rounded-circle bg-success"></span>
					<small class="fw-bold text-success text-uppercase tracking-wider">Official Barangay Portal</small>
				</div>
				<h1 class="display-4 fw-extrabold mb-4 lh-tight text-dark">
					Barangay <span class="text-primary">Panungyanan</span><br>
					Digital Services
				</h1>
				<p class="lead mb-5 text-dark pe-lg-5">
					Streamlined public services for a connected community. Request documents, report concerns, and stay
					updated all in one secure platform.
				</p>
				<div class="mt-5 d-flex gap-4 text-dark small">
					<div class="d-flex align-items-center gap-2">
						<i class="fas fa-check-circle text-success"></i> Fast Processing
					</div>
					<div class="d-flex align-items-center gap-2">
						<i class="fas fa-check-circle text-success"></i> Secure Data
					</div>
					<div class="d-flex align-items-center gap-2">
						<i class="fas fa-check-circle text-success"></i> 24/7 Access
					</div>
				</div>
			</div>
			<div class="col-lg-6 order-lg-2 order-1 text-center mb-5 mb-lg-0 animate__animated animate__fadeInRight">
				<div class="position-relative d-inline-block">
					<img src="public/img/barangaylogo.png" alt="Barangay Panungyanan Logo"
						class="img-fluid hero-logo position-relative z-1">
				</div>
			</div>
		</div>
	</div>
</section>


<!-- Statistics Section -->
<section class="py-5 bg-white border-bottom border-light">
	<div class="container">
		<div class="row text-center g-4 justify-content-center">
			<div class="col-md-3 col-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
				<div class="p-3">
					<h3 class="display-5 fw-bold text-dark mb-1"><?php echo number_format($sys_stats['residents']); ?>
					</h3>
					<p class="text-dark small fw-bold text-uppercase tracking-wide mb-0">Residents</p>
				</div>
			</div>
			<div class="col-md-3 col-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
				<div class="p-3">
					<h3 class="display-5 fw-bold text-dark mb-1"><?php echo number_format($sys_stats['documents']); ?>
					</h3>
					<p class="text-dark small fw-bold text-uppercase tracking-wide mb-0">Documents</p>
				</div>
			</div>
			<div class="col-md-3 col-6 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
				<div class="p-3">
					<h3 class="display-5 fw-bold text-dark mb-1"><?php echo number_format($sys_stats['incidents']); ?>
					</h3>
					<p class="text-dark small fw-bold text-uppercase tracking-wide mb-0">Incident Reports</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Features Section -->
<section id="services" class="py-6 bg-light">
	<div class="container py-5">
		<div class="row mb-5">
			<div class="col-lg-6 mx-auto text-center animate__animated animate__fadeInUp">
				<h2 class="mb-3">Essential Services</h2>
				<p class="lead text-dark">Everything you need to interact with your barangay, simplified.</p>
			</div>
		</div>
		<div class="row g-4">
			<div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
				<div class="feature-card">
					<div class="feature-icon text-success bg-success-subtle">
						<i class="fas fa-file-contract"></i>
					</div>
					<h4>Document Processing</h4>
					<p class="text-dark mb-4">Request and track certificates, clearances, and permits from the
						comfort of your home.</p>
					<ul class="list-unstyled mb-0 small text-dark">
						<li class="mb-2"><i class="fas fa-check text-primary me-2"></i>Barangay Clearance</li>
						<li class="mb-2"><i class="fas fa-check text-primary me-2"></i>Indigency Certificate</li>
						<li><i class="fas fa-check text-primary me-2"></i>Certificate of Residency</li>
					</ul>
				</div>
			</div>
			<div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
				<div class="feature-card">
					<div class="feature-icon text-warning bg-warning-subtle">
						<i class="fas fa-shield-alt"></i>
					</div>
					<h4>Incident Reporting</h4>
					<p class="text-dark mb-4">Direct line to barangay safety officers for emergencies and community
						concerns.</p>
					<ul class="list-unstyled mb-0 small text-dark">
						<li class="mb-2"><i class="fas fa-check text-warning me-2"></i>24/7 Monitoring</li>
						<li class="mb-2"><i class="fas fa-check text-warning me-2"></i>GPS Location Tagging</li>
						<li><i class="fas fa-check text-warning me-2"></i>Real-time Updates</li>
					</ul>
				</div>
			</div>
			<div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
				<div class="feature-card">
					<div class="feature-icon text-info bg-info-subtle">
						<i class="fas fa-id-card"></i>
					</div>
					<h4>Digital Identity</h4>
					<p class="text-dark mb-4">Secure digital verification system for all registered residents of
						the barangay.</p>
					<ul class="list-unstyled mb-0 small text-dark">
						<li class="mb-2"><i class="fas fa-check text-info me-2"></i>QR Code Access</li>
						<li class="mb-2"><i class="fas fa-check text-info me-2"></i>Instant Verification</li>
						<li><i class="fas fa-check text-info me-2"></i>Profile Management</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- About Us Section -->
<section id="about" class="py-6 bg-light">
	<div class="container py-5">
		<div class="row mb-5">
			<div class="col-lg-8 mx-auto text-center animate__animated animate__fadeInUp">
				<span
					class="d-inline-block py-1 px-3 rounded-pill bg-secondary bg-opacity-10 text-primary fw-bold text-uppercase small tracking-wide mb-3">About
					Us</span>
				<h2 class=" fw-extrabold mb-3">Barangay Panungyanan</h2>
				<p class="lead text-dark">Our commitment to serving the community with excellence and integrity</p>
			</div>
		</div>

		<div class="row g-4">
			<!-- Mission -->
			<div class="col-lg-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
				<div class="card border-0 shadow-sm h-100 rounded-4">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-3 mb-4">
							<div
								class="width-12 height-12 rounded-3 bg-secondary bg-opacity-10 text-primary d-flex align-items-center justify-content-center">
								<i class="fas fa-bullseye fa-lg"></i>
							</div>
							<h3 class="fw-bold mb-0 text-dark">MISSION</h3>
						</div>
						<p class="text-dark mb-0 lh-lg">
							To provide efficient, transparent, and responsive public service; maintain peace and order;
							promote social justice and community participation; and implement sustainable programs that
							improve the quality of life of all residents.
						</p>
					</div>
				</div>
			</div>

			<!-- Vision -->
			<div class="col-lg-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
				<div class="card border-0 shadow-sm h-100 rounded-4">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-3 mb-4">
							<div
								class="width-12 height-12 rounded-3 bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center">
								<i class="fas fa-eye fa-lg"></i>
							</div>
							<h3 class="fw-bold mb-0 text-dark">VISION</h3>
						</div>
						<p class="text-dark mb-0 lh-lg">
							A peaceful, progressive, and united community with empowered citizens, effective leadership,
							sustainable development, and a high quality of life for all.
						</p>
					</div>
				</div>
			</div>

			<!-- Service Pledge -->
			<div class="col-lg-12 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
				<div class="card border-0 shadow-sm rounded-4">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-3 mb-4">
							<div
								class="width-12 height-12 rounded-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center">
								<i class="fas fa-handshake fa-lg"></i>
							</div>
							<h3 class="fw-bold mb-0 text-dark">SERVICE PLEDGE</h3>
						</div>
						<p class="text-dark mb-0 lh-lg">
							We commit to serving our community with honesty, fairness, transparency, and compassion,
							ensuring the efficient and respectful delivery of basic services, maintaining peace and
							order, listening and responding to the needs of our residents, promoting unity and active
							community participation, upholding the law and good governance, and continuously working
							toward a safer, cleaner, and more progressive barangay for all.
						</p>
					</div>
				</div>
			</div>

			<!-- On Vicinity -->
			<div class="col-lg-12 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
				<div class="card border-0 shadow-sm rounded-4 bg-light">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-3 mb-4">
							<div
								class="width-12 height-12 rounded-3 bg-dark text-white d-flex align-items-center justify-content-center">
								<i class="fas fa-building fa-lg"></i>
							</div>
							<h3 class="fw-bold mb-0 text-dark">ON VICINITY</h3>
						</div>
						<p class="text-dark mb-0 lh-lg">
							The Barangay shall maintain a clean, safe, organized, and environmentally friendly vicinity
							within and around the barangay hall to ensure the comfort, health, and security of all
							residents, employees, and visitors. Smoking is strictly prohibited, proper waste segregation
							must be observed, and designated parking areas shall be used responsibly.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>


<?php if (!is_logged_in()): ?>
	<!-- Call to Action Section -->
	<section class="py-6 position-relative overflow-hidden"
		style="background: #064e4b; background: linear-gradient(135deg, #064e4b 0%, #0f766e 100%);">
		<div class="container position-relative z-1 py-4">
			<div class="row align-items-center">
				<div class="col-lg-8 mb-4 mb-lg-0 animate__animated animate__fadeInLeft text-center text-lg-start">
					<h2 class="display-6 fw-bold mb-3 text-white" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Ready
						to Go Digital?</h2>
					<p class="lead mb-0 text-white fw-medium">Join thousands of residents who are already enjoying the
						convenience of our digital platform.</p>
				</div>
				<div class="col-lg-4 text-center text-lg-end animate__animated animate__fadeInRight">
					<a href="register.php"
						class="btn btn-light text-success btn-md px-5 shadow-lg fw-bold hover-lift">Register
						Now</a>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<!-- Footer -->
<footer id="contact" class="pt-5 pb-4 text-white bg-dark">
	<div class="container">
		<div class="row g-4 mb-5">
			<div class="col-lg-4">
				<div class="d-flex align-items-center gap-2 mb-4">
					<img src="public/img/barangaylogo.png" alt="Logo" style="width: 40px; height: 40px;"
						class="rounded-circle bg-white p-1">
					<h5 class="fw-bold mb-0 text-white">Brgy. Panungyanan</h5>
				</div>
				<p class="text-white-50 mb-4">Dedicated to serving our community with transparency, efficiency, and
					modern digital solutions for a better tomorrow.</p>

			</div>
			<div class="col-lg-2 col-6">
				<h6 class="text-white fw-bold mb-4">Quick Links</h6>
				<ul class="list-unstyled text-white-50 mb-0 d-flex flex-column gap-2">
					<li><a href="index.php"
							class="text-white-50 text-decoration-none hover-white transition-colors">Home</a></li>
					<li><a href="index.php#about" class="text-white-50 text-decoration-none hover-white transition-colors">About
							Us</a></li>
					<li><a href="index.php#services"
							class="text-white-50 text-decoration-none hover-white transition-colors">Services</a></li>
				</ul>
			</div>
			<div class="col-lg-2 col-6">
				<h6 class="text-white fw-bold mb-4">Services</h6>
				<ul class="list-unstyled text-white-50 mb-0 d-flex flex-column gap-2">
					<li><a href="#"
							class="text-white-50 text-decoration-none hover-white transition-colors">Clearances</a></li>
					<li><a href="#" class="text-white-50 text-decoration-none hover-white transition-colors">Permits</a>
					</li>
					<li><a href="#"
							class="text-white-50 text-decoration-none hover-white transition-colors">Indigency</a></li>
					<li><a href="#"
							class="text-white-50 text-decoration-none hover-white transition-colors">Residency</a></li>
				</ul>
			</div>
			<div class="col-lg-4">
				<h6 class="text-white fw-bold mb-4">Contact Info</h6>
				<ul class="list-unstyled text-white-50 mb-0 d-flex flex-column gap-3">
					<li class="d-flex gap-3">
						<i class="fas fa-map-marker-alt mt-1 text-success"></i>
						<span>General Trias, Cavite</span>
					</li>
					<li class="d-flex gap-3">
						<i class="fas fa-phone mt-1 text-success"></i>
						<span>(046) 123-4567</span>
					</li>
					<li class="d-flex gap-3">
						<i class="fas fa-envelope mt-1 text-success"></i>
						<span>info@panungyanan.gov.ph</span>
					</li>
					<li class="d-flex gap-3">
						<i class="fas fa-clock mt-1 text-success"></i>
						<span>Mon-Fri: 8:00 AM - 5:00 PM</span>
					</li>
				</ul>
			</div>
		</div>
		<hr class="border-secondary opacity-25 mb-4">
		<div class="row align-items-center">
			<div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
				<small class="text-white-50">&copy; <?php echo date('Y'); ?> Barangay Panungyanan. All rights
					reserved.</small>
			</div>
			<div class="col-md-6 text-center text-md-end">
				<div class="d-flex justify-content-center justify-content-md-end gap-4">
					<a href="#" class="text-white-50 text-decoration-none small hover-white">Privacy Policy</a>
					<a href="#" class="text-white-50 text-decoration-none small hover-white">Terms of Service</a>
				</div>
			</div>
		</div>
	</div>
</footer>

<?php require_once __DIR__ . '/partials/footer.php'; ?>