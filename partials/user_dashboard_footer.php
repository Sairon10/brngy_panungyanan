				</div> <!-- End admin-content -->
			</div> <!-- End content-col -->
		</div> <!-- End row -->
	</div> <!-- End container-fluid -->

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
	<style>
		@media (max-width: 768px) {
			.chatbot-panel {
				left: 10px !important;
				right: 10px !important;
				width: auto !important;
				max-width: none !important;
				height: calc(100vh - 85px) !important;
				top: 75px !important;
			}
			.chatbot-header, .chatbot-input-container, .chatbot-suggestions {
				flex-shrink: 0 !important;
				z-index: 10 !important;
			}
			.chatbot-messages {
				flex: 1 1 auto !important;
				overflow-y: auto !important;
			}
		}
	</style>
	<div id="chatbot-root" data-user-logged-in="true"></div>
	<script src="public/js/chatbot.js?v=3"></script>
	<script>
		// Global Notifications (Topbar) for Residents
		let pollingTimer = null;
		const NOTIF_API = 'api/notifications.php';
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
					if (data.error) return;
					updateNotifBadge(data.unread_count);
					renderNotifDropdown(data.notifications);
				})
				.catch(err => console.error('Notification fetch error:', err));
		}

		function updateNotifBadge(count) {
			const badge = document.getElementById('topbarNotifBadge');
			if (!badge) return;
			if (count > 0) {
				badge.textContent = count > 99 ? '99+' : count;
				badge.style.display = 'block';
			} else {
				badge.style.display = 'none';
			}
		}

		function renderNotifDropdown(notifs) {
			const list = document.getElementById('topbarNotifList');
			if (!list) return;
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
								<p class="mb-1 text-muted small text-truncate-2" style="font-size: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${n.message}</p>
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

		document.addEventListener('DOMContentLoaded', startPolling);
	</script>
</body>
</html>
