				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
	<script>
		// Admin-specific JavaScript
		document.addEventListener('DOMContentLoaded', function() {
			// Add active class to current page in sidebar
			const currentPage = window.location.pathname.split('/').pop();
			const navLinks = document.querySelectorAll('.admin-sidebar .nav-link');
			
			navLinks.forEach(link => {
				const href = link.getAttribute('href');
				if (href === currentPage || (currentPage === '' && href === 'index.php')) {
					link.classList.add('active');
				}
			});
			
			// Auto-hide alerts after 5 seconds
			const alerts = document.querySelectorAll('.alert');
			alerts.forEach(alert => {
				setTimeout(() => {
					alert.style.transition = 'opacity 0.5s';
					alert.style.opacity = '0';
					setTimeout(() => alert.remove(), 500);
				}, 5000);
			});
			
			// Confirm delete actions
			const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
			deleteButtons.forEach(button => {
				button.addEventListener('click', function(e) {
					e.preventDefault();
					const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
					const url = this.getAttribute('href') || this.getAttribute('data-url');
					const formId = this.getAttribute('data-form-id');

					Swal.fire({
						title: 'Confirm Delete',
						text: message,
						icon: 'warning',
						showCancelButton: true,
						confirmButtonColor: '#ef4444',
						cancelButtonColor: '#6c757d',
						confirmButtonText: 'Yes, Delete'
					}).then((result) => {
						if (result.isConfirmed) {
							if (formId) {
								document.getElementById(formId).submit();
							} else if (url) {
								window.location.href = url;
							}
						}
					});
				});
			});
		});
		
		// ── Global Notifications (Topbar) ──────────────────────────
		let pollingTimer = null;
		const NOTIF_API = '../api/admin_notifications.php';
		const iconMap = {
			incident_update    : { icon: 'fa-exclamation-triangle', bg: 'rgba(239,68,68,0.1)',  color: '#ef4444' },
			incident_response  : { icon: 'fa-reply',                bg: 'rgba(239,68,68,0.1)',  color: '#ef4444' },
			request_update     : { icon: 'fa-file-alt',             bg: 'rgba(245,87,108,0.1)', color: '#f5576c' },
			chat_update        : { icon: 'fa-comments',             bg: 'rgba(79,172,254,0.1)', color: '#4facfe' },
			verification_update: { icon: 'fa-id-card',              bg: 'rgba(108,117,125,0.1)',color: '#6c757d' },
			general            : { icon: 'fa-bell',                 bg: 'rgba(20,184,166,0.1)', color: '#14b8a6' },
		};

		function getIconMeta(type) { return iconMap[type] || iconMap['general']; }

		function fetchNotifications() {
			fetch(`${NOTIF_API}?action=list&page=1`)
				.then(r => r.json())
				.then(data => {
					updateNotifBadge(data.unread_count);
					renderNotifDropdown(data.notifications);
				})
				.catch(err => console.error('Notification fetch error:', err));
		}

		function updateNotifBadge(count) {
			const badge = document.getElementById('topbarNotifBadge');
			if (count > 0) {
				badge.textContent = count > 99 ? '99+' : count;
				badge.style.display = 'block';
			} else {
				badge.style.display = 'none';
			}
		}

		function renderNotifDropdown(notifs) {
			const list = document.getElementById('topbarNotifList');
			if (!notifs || notifs.length === 0) {
				list.innerHTML = '<div class="p-4 text-center text-muted small"><i class="fas fa-bell-slash fa-2x mb-2 d-block opacity-25"></i>No notifications yet.</div>';
				return;
			}
			list.innerHTML = notifs.map(n => {
				const meta = getIconMeta(n.type);
				const unreadCls = n.is_read == 0 ? 'bg-light' : '';
				const dot = n.is_read == 0 ? '<span class="position-absolute top-50 end-0 translate-middle-y me-3 badge rounded-pill bg-primary" style="width: 8px; height: 8px; padding: 0;"></span>' : '';
				return `
					<div class="list-group-item list-group-item-action border-0 px-3 py-3 position-relative ${unreadCls}" 
					     onclick="handleNotifClick(${n.id}, '${n.link}')" style="cursor: pointer; transition: background 0.2s;">
						<div class="d-flex gap-2">
							<div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
							     style="width: 35px; height: 35px; background: ${meta.bg}; color: ${meta.color}; font-size: 0.8rem;">
								<i class="fas ${meta.icon}"></i>
							</div>
							<div class="min-width-0 pe-4">
								<div class="d-flex justify-content-between">
									<p class="mb-0 fw-bold small text-truncate">${n.title}</p>
									<small class="text-muted" style="font-size: 0.65rem;">${n.time_ago}</small>
								</div>
								<p class="mb-1 text-muted small text-truncate-2" style="font-size: 0.75rem;">${n.message}</p>
								<div class="text-primary fw-bold" style="font-size: 0.65rem;"><i class="fas fa-eye me-1"></i>View Details</div>
							</div>
						</div>
						${dot}
					</div>
				`;
			}).join('');
		}

		function handleNotifClick(id, link) {
			fetch(`${NOTIF_API}?action=mark_read&id=${id}`)
				.then(() => window.location.href = link)
				.catch(() => window.location.href = link);
		}

		function markAllRead() {
			fetch(`${NOTIF_API}?action=mark_all_read`)
				.then(() => fetchNotifications())
				.catch(err => console.error(err));
		}

		function deleteReadNotifications() {
			Swal.fire({
				title: 'Clear history?',
				text: "This will permanently delete all read notifications.",
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#14b8a6',
				cancelButtonColor: '#6c757d',
				confirmButtonText: 'Yes, clear them'
			}).then((result) => {
				if (result.isConfirmed) {
					fetch(`${NOTIF_API}?action=delete_read`)
						.then(() => fetchNotifications())
						.catch(err => console.error(err));
				}
			});
		}

		function startPolling() {
			fetchNotifications();
			pollingTimer = setInterval(fetchNotifications, 30000);
		}

		// Individual confirmation dialogs
		document.addEventListener('click', function(e) {
			// (no change to click logic)
			// 1. Submit Forms (Generic Confirm)
			var btnSubmit = e.target.closest('.btn-confirm-submit');
			if (btnSubmit) {
				var form = btnSubmit.closest('.admin-confirm-form') || btnSubmit.closest('form');
				if (form) {
					e.preventDefault();
					var actionName = form.dataset.actionName || btnSubmit.title || 'Proceed';
					var confirmColor = actionName.toLowerCase().indexOf('undo') !== -1 ? '#d97706' : '#0f766e';
					
					Swal.fire({
						title: 'Confirm',
						text: 'Are you sure you want to "' + actionName + '"?',
						icon: 'question',
						showCancelButton: true,
						confirmButtonColor: confirmColor,
						cancelButtonColor: '#6c757d',
						confirmButtonText: 'Yes, Proceed'
					}).then((result) => {
						if (result.isConfirmed) {
							form.submit();
						}
					});
					return;
				}
			}

			// 2. Print/Download Links
			var btnPrint = e.target.closest('.btn-confirm-print');
			if (btnPrint) {
				e.preventDefault();
				var url = btnPrint.getAttribute('href');
				Swal.fire({
					title: 'Print Document',
					text: 'Do you want to open and print this document?',
					icon: 'info',
					showCancelButton: true,
					confirmButtonColor: '#0ea5e9',
					cancelButtonColor: '#6c757d',
					confirmButtonText: 'Yes, Open PDF'
				}).then((result) => {
					if (result.isConfirmed) {
						window.open(url, '_blank');
					}
				});
			}
		});

		// Dropdown/Select confirmation (e.g. Incident Status)
		document.addEventListener('change', function(e) {
			var select = e.target.closest('.btn-confirm-submit');
			if (select && select.tagName === 'SELECT') {
				var form = select.closest('form');
				if (form) {
					var originalValue = select.getAttribute('data-original-value') || select.value;
					var actionName = select.title || 'Update';
					
					Swal.fire({
						title: 'Confirm Change',
						text: 'Are you sure you want to "' + actionName + '"?',
						icon: 'question',
						showCancelButton: true,
						confirmButtonColor: '#0f766e',
						cancelButtonColor: '#6c757d',
						confirmButtonText: 'Yes, Update'
					}).then((result) => {
						if (result.isConfirmed) {
							select.setAttribute('data-original-value', select.value);
							form.submit();
						} else {
							// Revert select to original value if cancelled
							select.value = originalValue;
						}
					});
				}
			}
		});

		document.addEventListener('DOMContentLoaded', startPolling);
	</script>
</body>
</html>
