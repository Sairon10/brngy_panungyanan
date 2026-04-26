<?php 
require_once __DIR__ . '/../config.php';
if (!is_admin()) redirect('../index.php');
$page_title = 'Support Chats';
$breadcrumb = [
	['title' => 'Dashboard', 'url' => 'index.php'],
	['title' => 'Support Chats']
];
require_once __DIR__ . '/header.php'; 
?>

<div class="row g-3 g-md-4">
	<!-- Chat List Sidebar -->
	<div class="col-12 col-lg-4" id="chatListContainer">
		<div class="card h-100">
			<div class="card-header pb-2">
				<h5 class="mb-2"><i class="fas fa-comments me-2"></i>Active Chats</h5>
				<div class="input-group input-group-sm mb-2">
					<span class="input-group-text"><i class="fas fa-search"></i></span>
					<input type="text" id="chatSearchInput" class="form-control" placeholder="Search chats...">
				</div>
				<div class="btn-group btn-group-sm w-100" role="group">
					<button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
					<button type="button" class="btn btn-outline-primary" data-filter="waiting">Waiting</button>
					<button type="button" class="btn btn-outline-primary" data-filter="active">Active</button>
					<button type="button" class="btn btn-outline-primary" data-filter="closed">Closed</button>
				</div>
			</div>
			<div class="card-body p-0" style="max-height: 65vh; overflow-y: auto;">
				<div id="chatList" class="list-group list-group-flush">
					<div class="text-center p-4">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Chat Window -->
	<div class="col-12 col-lg-8" id="chatWindowContainer">
		<div class="card h-100 d-flex flex-column">
			<div class="card-header" id="chatHeader">
				<div class="d-flex justify-content-between align-items-start gap-2">
					<div class="flex-grow-1 min-w-0">
						<h5 class="mb-0 text-truncate" id="chatTitle">Select a chat to start</h5>
						<small class="text-muted d-block text-truncate" id="chatSubtitle">Choose a chat from the list</small>
					</div>
					<div id="chatActions" style="display: none;" class="d-flex gap-2 flex-shrink-0">
						<button class="btn btn-sm btn-outline-info" id="viewInfoBtn" title="View user information">
							<i class="fas fa-info-circle d-none d-md-inline"></i> <span class="d-none d-md-inline">Info</span><span class="d-md-none"><i class="fas fa-info-circle"></i></span>
						</button>
						<button class="btn btn-sm btn-outline-danger" id="closeChatBtn" title="Close this chat session">
							<i class="fas fa-times-circle d-none d-md-inline"></i> <span class="d-none d-md-inline">Close</span><span class="d-md-none">×</span>
						</button>
						<button class="btn btn-sm btn-danger" id="deleteChatBtn" title="Delete this chat permanently">
							<i class="fas fa-trash-alt d-none d-md-inline"></i> <span class="d-none d-md-inline">Delete</span><span class="d-md-none"><i class="fas fa-trash-alt"></i></span>
						</button>
						<button class="btn btn-sm btn-outline-secondary d-lg-none" id="backToListBtn" title="Back to chat list">
							<i class="fas fa-arrow-left"></i>
						</button>
					</div>
				</div>
			</div>
			<div class="card-body p-0 d-flex flex-column flex-grow-1" style="min-height: 0;">
				<div id="chatMessages" class="p-3 p-md-4 flex-grow-1" style="min-height: 300px; max-height: 60vh; overflow-y: auto; background: #f8fafc;">
					<div class="text-center text-muted py-5">
						<i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
						<p>Select a chat from the sidebar to view messages</p>
					</div>
				</div>
				<div class="card-footer p-3" id="chatInputContainer" style="display: none;">
					<div class="input-group">
						<input type="text" class="form-control" id="chatInput" placeholder="Type your message...">
						<button class="btn btn-primary" id="sendBtn">
							<i class="fas fa-paper-plane d-none d-md-inline"></i> <span class="d-none d-md-inline">Send</span><span class="d-md-none"><i class="fas fa-paper-plane"></i></span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
let currentChatId = null;
let lastMessageId = 0;
let pollingInterval = null;
let currentFilter = 'all';

// Load chat list
async function loadChatList(filter = 'all') {
	const response = await fetch(`../api/support_chat.php?action=get_chats&status=${filter}`);
	const data = await response.json();
	
	if (data.success) {
		const chatList = document.getElementById('chatList');
		chatList.innerHTML = '';
		
		if (data.chats.length === 0) {
			chatList.innerHTML = '<div class="text-center p-4 text-muted">No chats found</div>';
			return;
		}
		
		data.chats.forEach(chat => {
			const unreadBadge = chat.unread_count > 0 
				? `<span class="badge bg-danger ms-2">${chat.unread_count}</span>` 
				: '';
			
			const statusClass = {
				'waiting': 'warning',
				'active': 'success',
				'closed': 'secondary',
				'open': 'info'
			}[chat.status] || 'secondary';
			
			const chatItem = document.createElement('a');
			chatItem.href = '#';
			chatItem.className = `list-group-item list-group-item-action ${currentChatId == chat.id ? 'active' : ''} ${chat.status === 'closed' ? 'opacity-75' : ''}`;
			chatItem.dataset.chatId = chat.id;
			chatItem.innerHTML = `
				<div class="d-flex justify-content-between align-items-start gap-2">
					<div class="flex-grow-1 min-w-0">
						<div class="d-flex align-items-center gap-2 mb-1">
							<h6 class="mb-0 ${chat.status === 'closed' ? 'text-decoration-line-through' : ''} text-truncate">${escapeHtml(chat.user_name)}</h6>
							<span class="badge bg-${statusClass} flex-shrink-0">${chat.status}</span>
							${unreadBadge}
						</div>
						<p class="mb-1 text-muted small text-truncate">${escapeHtml(chat.last_message || 'No messages yet')}</p>
						<small class="text-muted">
							${formatTime(chat.last_message_at)}
							${chat.closed_at ? ` • Closed ${formatTime(chat.closed_at)}` : ''}
						</small>
					</div>
				</div>
			`;
			chatItem.addEventListener('click', (e) => {
				e.preventDefault();
				if (chat.status !== 'closed') {
					selectChat(chat.id);
				} else {
					// Still allow viewing closed chats, but disable input
					selectChat(chat.id);
				}
			});
			chatList.appendChild(chatItem);
		});
	}
}

// Select a chat
async function selectChat(chatId) {
	currentChatId = chatId;
	lastMessageId = 0;
	
	// Update UI
	document.querySelectorAll('#chatList .list-group-item').forEach(item => {
		item.classList.toggle('active', item.dataset.chatId == chatId);
	});
	
	// Get chat details
	const response = await fetch(`../api/support_chat.php?action=get_chats&status=all`);
	const data = await response.json();
	
	if (data.success) {
		const chat = data.chats.find(c => c.id == chatId);
		if (chat) {
			document.getElementById('chatTitle').textContent = chat.user_name;
			document.getElementById('chatSubtitle').textContent = chat.status === 'closed' 
				? 'Chat Closed' 
				: (chat.status === 'active' ? 'Active Chat' : 'Waiting for response');
			
			// Show/hide input based on status
			if (chat.status === 'closed') {
				document.getElementById('chatInputContainer').style.display = 'none';
			} else {
				document.getElementById('chatInputContainer').style.display = 'block';
			}
		}
	}
	
	document.getElementById('chatActions').style.display = 'block';
	
	// Mobile: Show chat window, hide chat list
	if (window.innerWidth < 992) {
		document.getElementById('chatListContainer').style.display = 'none';
		document.getElementById('chatWindowContainer').style.display = 'block';
	}
	
	// Load messages
	loadMessages();
	startPolling();
}

// Load messages
async function loadMessages() {
	if (!currentChatId) return;
	
	const response = await fetch(`../api/support_chat.php?action=get_messages&chat_id=${currentChatId}&last_message_id=${lastMessageId}`);
	const data = await response.json();
	
	if (data.success) {
		const messagesDiv = document.getElementById('chatMessages');
		
		if (lastMessageId === 0) {
			messagesDiv.innerHTML = '';
		}
		
		if (data.messages.length > 0) {
			data.messages.forEach(msg => {
				addMessageToUI(msg);
				lastMessageId = Math.max(lastMessageId, msg.id);
			});
			messagesDiv.scrollTop = messagesDiv.scrollHeight;
		}
		
		// Update chat header
		updateChatHeader();
	}
}

// Add message to UI
function addMessageToUI(msg) {
	const messagesDiv = document.getElementById('chatMessages');
	const isAdmin = msg.sender_type === 'admin';
	
	const messageDiv = document.createElement('div');
	messageDiv.className = `mb-3 d-flex ${isAdmin ? '' : 'flex-row-reverse'}`;
	messageDiv.innerHTML = `
		<div class="message-bubble ${isAdmin ? 'bg-light' : 'bg-primary text-white'}" style="max-width: 85%; padding: 0.75rem 1rem; border-radius: 12px; word-wrap: break-word;">
			<small class="d-block mb-1 ${isAdmin ? 'text-muted' : 'text-white-50'}">${escapeHtml(msg.full_name)}</small>
			<div style="word-break: break-word;">${escapeHtml(msg.message)}</div>
			<small class="d-block mt-1 ${isAdmin ? 'text-muted' : 'text-white-50'}" style="font-size: 0.75rem;">
				${formatTime(msg.created_at)}
			</small>
		</div>
	`;
	messagesDiv.appendChild(messageDiv);
}

// Send message
async function sendMessage() {
	const input = document.getElementById('chatInput');
	const message = input.value.trim();
	
	if (!message || !currentChatId) return;
	
	const tempId = 'temp-' + Date.now();
	
	// Add to UI immediately with a unique ID and a loading indicator
	const tempMsg = {
		id: tempId,
		sender_id: <?php echo $_SESSION['user_id']; ?>,
		sender_type: 'admin',
		message: message + ' <i class="fas fa-spinner fa-spin ms-1" style="font-size:0.75rem;"></i>',
		full_name: '<?php echo htmlspecialchars($_SESSION['full_name']); ?>',
		created_at: new Date().toISOString()
	};
	addMessageToUI(tempMsg);
	// We need to tag the generated element
	const messagesDiv = document.getElementById('chatMessages');
	const tempDiv = messagesDiv.lastElementChild;
	if (tempDiv) tempDiv.id = 'msg-el-' + tempId;
	
	input.value = '';
	document.getElementById('sendBtn').disabled = true;
	input.disabled = true;
	
	try {
        // Send to server
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('chat_id', currentChatId);
        formData.append('message', message);
        
        const response = await fetch('../api/support_chat.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Remove the specific temp message immediately
        const specificTempDiv = document.getElementById('msg-el-' + tempId);
        if (specificTempDiv) {
            specificTempDiv.remove();
        }
        
        if (data.success) {
            // Load messages handles adding it from DB properly
            await loadMessages();
        } else {
            alert('Error sending message: ' + (data.error || 'Unknown error'));
        }
    } catch {
        const specificTempDiv = document.getElementById('msg-el-' + tempId);
        if (specificTempDiv) specificTempDiv.remove();
    } finally {
        document.getElementById('sendBtn').disabled = false;
        input.disabled = false;
        input.focus();
    }
}

// Start polling
function startPolling() {
	if (pollingInterval) clearInterval(pollingInterval);
	pollingInterval = setInterval(() => {
		loadMessages();
		loadChatList(currentFilter);
	}, 2000);
}

// Stop polling
function stopPolling() {
	if (pollingInterval) {
		clearInterval(pollingInterval);
		pollingInterval = null;
	}
}

// Update chat header
function updateChatHeader() {
	// This would fetch chat details and update header
	// For now, just update the title
}

// Close chat (actually close the chat session)
async function closeChat() {
	if (!currentChatId) return;
	
    const result = await Swal.fire({
        title: 'Close Chat?',
        text: "The user will no longer be able to send messages to this session.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, close it',
        cancelButtonText: 'Keep open'
    });

	if (!result.isConfirmed) return;
	
	try {
		const formData = new FormData();
		formData.append('action', 'close_chat');
		formData.append('chat_id', currentChatId);
		
		const response = await fetch('../api/support_chat.php', {
			method: 'POST',
			body: formData
		});
		
		const data = await response.json();
		
		if (data.success) {
            Swal.fire('Closed!', 'The chat has been closed.', 'success');
			// Add a system message indicating chat was closed
			const messagesDiv = document.getElementById('chatMessages');
			const systemMsg = document.createElement('div');
			systemMsg.className = 'text-center my-3';
			systemMsg.innerHTML = `
				<span class="badge bg-secondary">Chat closed by admin</span>
			`;
			messagesDiv.appendChild(systemMsg);
			
			// Update UI
			document.getElementById('chatSubtitle').textContent = 'Chat Closed';
			document.getElementById('chatInputContainer').style.display = 'none';
			document.getElementById('closeChatBtn').style.display = 'none';
			
			// Reload chat list to reflect closed status
			loadChatList(currentFilter);
			
			// Stop polling
			stopPolling();
		} else {
			Swal.fire('Error', data.error || 'Unknown error', 'error');
		}
	} catch (error) {
		console.error('Error closing chat:', error);
		Swal.fire('Error', 'Error closing chat. Please try again.', 'error');
	}
}

// Delete chat permanently
async function deleteChat() {
	if (!currentChatId) return;
	
    const result = await Swal.fire({
        title: 'Delete Chat Permanently?',
        text: "All messages and data will be lost. This cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete everything',
        cancelButtonText: 'Cancel'
    });

	if (!result.isConfirmed) return;
	
	try {
		const formData = new FormData();
		formData.append('action', 'delete_chat');
		formData.append('chat_id', currentChatId);
		
		const response = await fetch('../api/support_chat.php', {
			method: 'POST',
			body: formData
		});
		
		const data = await response.json();
		
		if (data.success) {
            Swal.fire('Deleted!', 'The chat has been permanently removed.', 'success');
			// Clear UI and reload list
			closeChatView();
			loadChatList(currentFilter);
		} else {
			Swal.fire('Error', data.error || 'Unknown error', 'error');
		}
	} catch (error) {
		console.error('Error deleting chat:', error);
		Swal.fire('Error', 'Error deleting chat. Please try again.', 'error');
	}
}

// Show User Information Modal
async function showUserInfo() {
    if (!currentChatId) return;
    
    const modal = new bootstrap.Modal(document.getElementById('userInfoModal'));
    const body = document.getElementById('userInfoBody');
    body.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();
    
    try {
        const response = await fetch(`../api/support_chat.php?action=get_chats&status=all`);
        const data = await response.json();
        
        if (data.success) {
            const chat = data.chats.find(c => c.id == currentChatId);
            if (chat) {
                let html = `
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">
                            <small class="text-muted d-block">Full Name</small>
                            <span class="fw-bold">${escapeHtml(chat.user_name)}</span>
                        </div>
                        <div class="list-group-item">
                            <small class="text-muted d-block">Contact Info</small>
                            <span>${escapeHtml(chat.user_email || 'Not provided')}</span>
                        </div>
                        <div class="list-group-item">
                            <small class="text-muted d-block">Registration Status</small>
                            <span class="badge bg-${chat.user_id ? 'success' : 'secondary'}">
                                ${chat.user_id ? 'Registered Resident' : 'Guest User'}
                            </span>
                        </div>
                        <div class="list-group-item">
                            <small class="text-muted d-block">Chat Status</small>
                            <span class="badge bg-info text-capitalize">${chat.status}</span>
                        </div>
                        <div class="list-group-item">
                            <small class="text-muted d-block">Started At</small>
                            <span>${formatTime(chat.created_at)}</span>
                        </div>
                    </div>
                `;
                body.innerHTML = html;
            }
        }
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger">Error loading user info.</div>';
    }
}

// Close chat view (just close the UI, don't close the chat)
function closeChatView() {
	currentChatId = null;
	lastMessageId = 0;
	stopPolling();
	
	document.getElementById('chatMessages').innerHTML = `
		<div class="text-center text-muted py-5">
			<i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
			<p>Select a chat from the sidebar to view messages</p>
		</div>
	`;
	document.getElementById('chatInputContainer').style.display = 'none';
	document.getElementById('chatActions').style.display = 'none';
	document.getElementById('chatTitle').textContent = 'Select a chat to start';
	document.getElementById('chatSubtitle').textContent = 'Choose a chat from the list';
	
	document.querySelectorAll('#chatList .list-group-item').forEach(item => {
		item.classList.remove('active');
	});
	
	// Mobile: Show chat list, hide chat window
	if (window.innerWidth < 992) {
		document.getElementById('chatListContainer').style.display = 'block';
		document.getElementById('chatWindowContainer').style.display = 'none';
	}
}

// Update admin activity
async function updateActivity() {
	await fetch('../api/support_chat.php?action=update_activity', { method: 'POST' });
}

// Utility functions
function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

function formatTime(timestamp) {
	const date = new Date(timestamp);
	const now = new Date();
	const diff = now - date;
	
	if (diff < 60000) return 'Just now';
	if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
	if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
	return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Event listeners
document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keydown', (e) => {
	if (e.key === 'Enter') sendMessage();
});

document.getElementById('closeChatBtn').addEventListener('click', closeChat);
document.getElementById('deleteChatBtn').addEventListener('click', deleteChat);
document.getElementById('viewInfoBtn').addEventListener('click', showUserInfo);
document.getElementById('backToListBtn')?.addEventListener('click', closeChatView);

// Handle window resize
window.addEventListener('resize', () => {
	if (window.innerWidth >= 992) {
		// Desktop: Show both
		document.getElementById('chatListContainer').style.display = 'block';
		document.getElementById('chatWindowContainer').style.display = 'block';
	} else if (currentChatId) {
		// Mobile with active chat: Show only chat window
		document.getElementById('chatListContainer').style.display = 'none';
		document.getElementById('chatWindowContainer').style.display = 'block';
	} else {
		// Mobile without active chat: Show only chat list
		setInitialView();
	}
});

// Filter buttons
document.querySelectorAll('[data-filter]').forEach(btn => {
	btn.addEventListener('click', () => {
		document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
		btn.classList.add('active');
		currentFilter = btn.dataset.filter;
		loadChatList(currentFilter);
	});
});

// Chat search
document.getElementById('chatSearchInput').addEventListener('input', function() {
	const query = this.value.toLowerCase();
	document.querySelectorAll('#chatList .list-group-item').forEach(function(item) {
		const text = item.textContent.toLowerCase();
		item.style.display = text.includes(query) ? '' : 'none';
	});
});

// Initialize
loadChatList();
updateActivity();
setInterval(updateActivity, 30000); // Update every 30 seconds

// Set initial mobile/desktop view
function setInitialView() {
	if (window.innerWidth < 992) {
		// Mobile: Show chat list, hide chat window
		document.getElementById('chatListContainer').style.display = 'block';
		document.getElementById('chatWindowContainer').style.display = 'none';
	} else {
		// Desktop: Show both
		document.getElementById('chatListContainer').style.display = 'block';
		document.getElementById('chatWindowContainer').style.display = 'block';
	}
}

// Set initial view on load
setInitialView();

// Cleanup
window.addEventListener('beforeunload', stopPolling);
</script>

<!-- Resident Info Modal -->
<div class="modal fade" id="userInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userInfoBody">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

