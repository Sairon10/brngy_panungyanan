(function(){
	const root = document.getElementById('chatbot-root');
	if(!root) return;

	// Comprehensive FAQ Knowledge Base
	const faqDatabase = {
		// Document Requests
		'clearance': {
			keywords: ['clearance', 'barangay clearance', 'certificate clearance', 'clearance certificate'],
			response: 'A Barangay Clearance is an official document certifying that you are a resident in good standing. You can request it through the Documents page. The process typically takes 2-3 business days. You\'ll need to provide valid identification and proof of residency.',
			related: ['How to request documents?', 'What documents are available?', 'How long does processing take?']
		},
		'indigency': {
			keywords: ['indigency', 'certificate of indigency', 'indigent', 'indigency certificate'],
			response: 'A Certificate of Indigency is issued to residents who need financial assistance documentation. You can request this through the Documents page. You\'ll need to provide proof of income and household information. Processing usually takes 2-3 business days.',
			related: ['How to request documents?', 'What documents are available?', 'What are the requirements?']
		},
		'residency': {
			keywords: ['residency', 'certificate of residency', 'residence certificate', 'proof of residency'],
			response: 'A Certificate of Residency confirms your address within Barangay Panungyanan. Request it through the Documents page. You\'ll need valid ID and proof of address. Processing takes 2-3 business days.',
			related: ['How to request documents?', 'What documents are available?']
		},
		'documents': {
			keywords: ['document', 'documents', 'request document', 'how to request', 'what documents'],
			response: 'You can request various documents including Barangay Clearance, Certificate of Indigency, and Certificate of Residency. Go to the Documents page (or Requests page) to submit a request. You\'ll need to be logged in and verified. Processing typically takes 2-3 business days.',
			related: ['What is a barangay clearance?', 'What is an indigency certificate?', 'How long does processing take?']
		},
		'requirements': {
			keywords: ['requirement', 'requirements', 'what do i need', 'what documents needed', 'prerequisites'],
			response: 'To request documents, you need: 1) A registered account, 2) Completed ID verification, 3) Valid identification (government-issued ID), and 4) Proof of residency if required. Make sure your profile is complete and verified before submitting requests.',
			related: ['How to verify my ID?', 'How to register?', 'What is ID verification?']
		},
		'processing time': {
			keywords: ['processing', 'how long', 'duration', 'time', 'when will i get', 'processing time'],
			response: 'Most document requests are processed within 2-3 business days. You\'ll receive notifications about the status of your request. Once approved, you can download or print your document. For urgent requests, you may contact the barangay office directly.',
			related: ['How to track my request?', 'What is the status of my request?']
		},
		
		// ID Verification
		'id verification': {
			keywords: ['id verification', 'verify id', 'verification', 'verify my id', 'id verification status'],
			response: 'ID Verification is required to access document services. Upload a clear photo of a valid government-issued ID (driver\'s license, passport, SSS ID, etc.) through the ID Verification page. The verification process usually takes 1-2 business days. You\'ll receive a notification once verified.',
			related: ['How to upload ID?', 'What IDs are accepted?', 'Why do I need verification?']
		},
		'id card': {
			keywords: ['id card', 'barangay id', 'resident id', 'digital id', 'print id'],
			response: 'Your Barangay Digital ID is available after ID verification. You can view and print it from the ID Card page. The digital ID includes a QR code for instant verification. Make sure your ID verification is approved first.',
			related: ['How to verify my ID?', 'What is ID verification?']
		},
		
		// Incident Reporting
		'incident': {
			keywords: ['incident', 'report incident', 'report', 'emergency', 'report problem', 'report issue'],
			response: 'You can report incidents, emergencies, or community concerns through the Report page. Include details, location (GPS tagging available), and photos if applicable. Reports are monitored 24/7 and you\'ll receive updates on the status. For emergencies, call the barangay hotline immediately.',
			related: ['How to report an emergency?', 'What can I report?', 'How to track my report?']
		},
		'emergency': {
			keywords: ['emergency', 'urgent', 'immediate', 'help', 'assistance'],
			response: 'For emergencies, please call the barangay hotline at (046) 123-4567 immediately. You can also use the Report page for non-urgent concerns. The system provides 24/7 monitoring and rapid response for safety issues.',
			related: ['How to report incidents?', 'What is the contact number?']
		},
		
		// Registration & Account
		'register': {
			keywords: ['register', 'sign up', 'create account', 'new account', 'registration'],
			response: 'To register, click "Get Started" or go to the Register page. You\'ll need to provide: full name, email (optional), phone number, address, and create a password. After registration, complete your profile and verify your ID to access all services.',
			related: ['How to verify my ID?', 'What information do I need?']
		},
		'login': {
			keywords: ['login', 'sign in', 'log in', 'access account', 'forgot password'],
			response: 'To log in, use your registered email or phone number and password. If you forgot your password, use the "Forgot Password" link on the login page. You\'ll receive a reset link via email or SMS.',
			related: ['How to reset password?', 'I forgot my password']
		},
		'profile': {
			keywords: ['profile', 'update profile', 'edit profile', 'my information', 'change details'],
			response: 'You can update your profile information including name, address, contact details, and profile picture from the Profile page. Keep your information updated to ensure smooth document processing. Some changes may require re-verification.',
			related: ['How to change my address?', 'How to upload profile picture?']
		},
		
		// General Information
		'contact': {
			keywords: ['contact', 'phone', 'email', 'address', 'location', 'office', 'where'],
			response: 'Barangay Panungyanan is located in General Trias, Cavite. Contact us at: Phone: (046) 123-4567 | Email: info@panungyanan.gov.ph | Office Hours: Monday-Friday, 8:00 AM - 5:00 PM. You can also visit us in person or use this portal for online services.',
			related: ['What are the office hours?', 'Where is the barangay office?']
		},
		'hours': {
			keywords: ['hours', 'office hours', 'when open', 'schedule', 'time'],
			response: 'The barangay office is open Monday through Friday from 8:00 AM to 5:00 PM. However, you can access online services 24/7 through this portal. For urgent matters outside office hours, use the incident reporting feature.',
			related: ['How to contact the barangay?', 'What services are available?']
		},
		'services': {
			keywords: ['services', 'what services', 'available services', 'what can i do', 'features'],
			response: 'Our services include: 1) Document Processing (Clearances, Indigency, Residency certificates), 2) Incident Reporting (24/7 monitoring), 3) Digital Resident ID (QR code verification), and 4) Profile Management. All services are accessible after registration and ID verification.',
			related: ['How to request documents?', 'How to report incidents?', 'What is digital ID?']
		},
		'help': {
			keywords: ['help', 'support', 'assistance', 'i need help', 'how can i'],
			response: 'I\'m here to help! You can ask me about: document requests, ID verification, incident reporting, registration, account management, or general information about Barangay Panungyanan. What would you like to know?\n\nIf you need to speak with a live support agent, just say "contact support" or "customer support".',
			related: ['What services are available?', 'How to get started?', 'Contact customer support']
		},
		'customer support': {
			keywords: ['customer support', 'contact support', 'live support', 'speak to agent', 'talk to admin', 'human support', 'real person', 'agent'],
			response: 'SUPPORT_REQUEST', // Special flag to trigger live chat
			related: []
		},
		'status': {
			keywords: ['status', 'check status', 'my request', 'track', 'where is'],
			response: 'To check the status of your requests, go to the Documents or Requests page. You\'ll see all your submitted requests with their current status (Pending, Processing, Approved, Rejected). You\'ll also receive email/SMS notifications when status changes.',
			related: ['How long does processing take?', 'How to request documents?']
		},
		
		// Barangay Panungyanan Specific Information
		'panungyanan': {
			keywords: ['panungyanan', 'barangay panungyanan', 'about panungyanan', 'what is panungyanan', 'panungyanan barangay'],
			response: 'Barangay Panungyanan is one of the barangays in General Trias, Cavite, Philippines. We serve over 2,800 residents with modern digital services. Our barangay is committed to providing efficient public services, community programs, and maintaining peace and order. We offer various services including document processing, incident reporting, and community assistance programs.',
			related: ['Where is Barangay Panungyanan?', 'What services does the barangay offer?', 'How many residents are there?']
		},
		'location': {
			keywords: ['where is', 'location', 'address', 'where located', 'find barangay', 'directions', 'where is barangay panungyanan', 'where is panungyanan', 'barangay panungyanan location', 'panungyanan location', 'map', 'show map', 'get directions'],
			response: 'MAP_LOCATION', // Special flag to show map
			related: ['What is the contact number?', 'What are the office hours?', 'How to get there?']
		},
		'general trias': {
			keywords: ['general trias', 'cavite', 'municipality', 'city', 'province'],
			response: 'Barangay Panungyanan is part of General Trias, Cavite. General Trias is a first-class municipality in the province of Cavite, Philippines. It is known for its growing economy, modern infrastructure, and progressive governance. Our barangay is proud to be part of this vibrant community.',
			related: ['Where is Barangay Panungyanan?', 'What is the population?']
		},
		'population': {
			keywords: ['population', 'how many residents', 'number of residents', 'residents count', 'people'],
			response: 'Barangay Panungyanan serves over 2,800 registered residents. Our community continues to grow, and we are committed to providing quality services to all residents. The barangay maintains updated records of all residents through our digital portal.',
			related: ['How to register as a resident?', 'What services are available?']
		},
		'officials': {
			keywords: ['officials', 'barangay captain', 'captain', 'kagawad', 'council', 'leaders', 'who is the', 'barangay officials'],
			response: 'Barangay Panungyanan is led by elected officials including the Barangay Captain and Barangay Councilors (Kagawad). For information about current officials, their roles, and how to contact them, please visit the barangay office or contact us at (046) 123-4567. You can also check official announcements on our portal.',
			related: ['How to contact barangay officials?', 'What is the contact number?']
		},
		'purok': {
			keywords: ['purok', 'zone', 'area', 'sector', 'purok number', 'which purok'],
			response: 'Barangay Panungyanan is divided into several puroks (zones) for better organization and service delivery. When registering or updating your profile, you can select your purok. This helps us organize community programs and services more effectively. Check your profile to see or update your purok assignment.',
			related: ['How to update my profile?', 'What is my purok?']
		},
		'community programs': {
			keywords: ['programs', 'community programs', 'activities', 'events', 'what programs', 'community activities'],
			response: 'Barangay Panungyanan offers various community programs including: Health Services (medical missions, vaccination programs), Education Support (scholarships, learning materials), Safety & Security (24/7 monitoring, neighborhood watch), and Social Services (assistance for indigent families). Check announcements on our portal or contact the barangay office for upcoming programs.',
			related: ['What services are available?', 'How to join programs?', 'Health services']
		},
		'health services': {
			keywords: ['health', 'medical', 'clinic', 'health center', 'vaccination', 'medicine', 'healthcare'],
			response: 'Barangay Panungyanan provides health services through our barangay health center. Services include: medical consultations, vaccination programs, health education, maternal and child care, and referrals to hospitals. For health concerns or to schedule appointments, contact the barangay health center or visit during office hours.',
			related: ['What are the office hours?', 'How to contact the barangay?', 'Community programs']
		},
		'education': {
			keywords: ['education', 'school', 'scholarship', 'student', 'learning', 'academic'],
			response: 'Barangay Panungyanan supports education through scholarship programs, learning material assistance, and youth development initiatives. We provide educational support to qualified students. For scholarship applications or educational assistance, visit the barangay office or check our portal for announcements and requirements.',
			related: ['How to apply for scholarship?', 'What programs are available?', 'Community programs']
		},
		'safety': {
			keywords: ['safety', 'security', 'peace and order', 'tanod', 'barangay tanod', 'police', 'crime'],
			response: 'Barangay Panungyanan maintains peace and order through our Barangay Tanod (watchmen) and coordination with local police. We have 24/7 monitoring and rapid response systems. You can report incidents, emergencies, or security concerns through our Report page or call the barangay hotline at (046) 123-4567 for immediate assistance.',
			related: ['How to report incidents?', 'Emergency contact', 'What is incident reporting?']
		},
		'business': {
			keywords: ['business', 'permit', 'business permit', 'license', 'establish business', 'commercial'],
			response: 'For business permits and licenses in Barangay Panungyanan, you need to coordinate with the General Trias Municipal Office. The barangay can provide clearance certificates required for business registration. Request a Barangay Clearance through our Documents page, which is often needed for business permit applications.',
			related: ['How to request clearance?', 'What documents are needed?', 'How to contact the municipality?']
		},
		'waste': {
			keywords: ['waste', 'garbage', 'trash', 'collection', 'waste management', 'disposal'],
			response: 'Barangay Panungyanan coordinates waste collection with the General Trias Municipal Government. Garbage collection schedules are typically posted in the barangay. For waste management concerns, improper disposal, or to report issues, use our incident reporting feature or contact the barangay office.',
			related: ['How to report issues?', 'What is incident reporting?']
		},
		'water': {
			keywords: ['water', 'water supply', 'utilities', 'electricity', 'power', 'meralco', 'maynilad'],
			response: 'Water and electricity services in Barangay Panungyanan are provided by utility companies (Maynilad for water, Meralco for electricity). For service applications, disconnections, or billing concerns, contact the respective utility companies. The barangay can assist with documentation needed for utility applications.',
			related: ['What documents are needed?', 'How to request clearance?']
		},
		'events': {
			keywords: ['events', 'festival', 'celebration', 'fiesta', 'activities', 'upcoming events', 'schedule'],
			response: 'Barangay Panungyanan organizes various community events throughout the year including fiestas, cultural celebrations, and community gatherings. Check our portal announcements or contact the barangay office for upcoming events and schedules. Residents are encouraged to participate in community activities.',
			related: ['How to contact the barangay?', 'What programs are available?', 'Community programs']
		},
		'facilities': {
			keywords: ['facilities', 'barangay hall', 'health center', 'court', 'playground', 'building', 'infrastructure'],
			response: 'Barangay Panungyanan has various facilities including the Barangay Hall (main office), health center, covered court, and community spaces. These facilities are available for community use. For reservations or inquiries about facility use, contact the barangay office during business hours.',
			related: ['What are the office hours?', 'How to contact the barangay?']
		},
		'history': {
			keywords: ['history', 'background', 'origin', 'established', 'when founded', 'about barangay'],
			response: 'Barangay Panungyanan is a progressive barangay in General Trias, Cavite. While specific historical details may vary, the barangay has evolved to serve its growing community with modern services. We continue to preserve our cultural heritage while embracing digital transformation for better service delivery.',
			related: ['Where is Barangay Panungyanan?', 'What services are available?']
		},
		'tourism': {
			keywords: ['tourism', 'tourist', 'visit', 'attractions', 'places to visit', 'landmarks'],
			response: 'Barangay Panungyanan is part of General Trias, Cavite, which offers various attractions. While the barangay itself is primarily residential, nearby areas in General Trias and Cavite province offer historical sites, parks, and recreational areas. For information about local attractions, contact the General Trias Tourism Office.',
			related: ['Where is Barangay Panungyanan?', 'What is General Trias?']
		},
		'residents': {
			keywords: ['residents', 'how to become resident', 'new resident', 'moving in', 'transfer'],
			response: 'To become a registered resident of Barangay Panungyanan, register on our portal and complete your profile with your address. You\'ll need to verify your ID and provide proof of residency. Once registered, you can access all barangay services including document requests and community programs.',
			related: ['How to register?', 'What is ID verification?', 'How to update profile?']
		},
		'complaints': {
			keywords: ['complaint', 'concern', 'problem', 'issue', 'grievance', 'file complaint'],
			response: 'You can file complaints or report concerns through our incident reporting system on the Report page. For formal complaints, you can also visit the barangay office during business hours. All reports are handled with confidentiality and appropriate action. For urgent matters, call (046) 123-4567.',
			related: ['How to report incidents?', 'What is the contact number?', 'Office hours']
		},
		'assistance': {
			keywords: ['assistance', 'help', 'aid', 'financial assistance', 'support', 'need help'],
			response: 'Barangay Panungyanan provides various forms of assistance including financial aid for qualified indigent families, medical assistance, educational support, and emergency relief. To apply for assistance, visit the barangay office with required documents. You may need a Certificate of Indigency, which you can request through our Documents page.',
			related: ['What is indigency certificate?', 'What documents are needed?', 'How to request documents?']
		},
		'volunteer': {
			keywords: ['volunteer', 'volunteering', 'help community', 'join', 'participate', 'contribute'],
			response: 'Barangay Panungyanan welcomes volunteers for community programs and activities. You can volunteer for health programs, education initiatives, safety patrols, or community events. To volunteer, contact the barangay office or check announcements on our portal for volunteer opportunities.',
			related: ['What programs are available?', 'How to contact the barangay?', 'Community programs']
		},
		'announcements': {
			keywords: ['announcements', 'news', 'updates', 'notices', 'information', 'latest'],
			response: 'Important announcements from Barangay Panungyanan are posted on our portal and communicated through various channels. Check the homepage and your account notifications for updates. You can also visit the barangay office or contact us at (046) 123-4567 for the latest information.',
			related: ['How to contact the barangay?', 'What services are available?']
		},
		'certificate purpose': {
			keywords: ['purpose', 'what for', 'why need', 'certificate purpose', 'use of certificate'],
			response: 'Barangay certificates serve various purposes: Clearance (employment, business permits, travel), Indigency (financial assistance, scholarships, medical aid), and Residency (school enrollment, government transactions). When requesting, specify the purpose as it may affect processing requirements.',
			related: ['How to request documents?', 'What documents are available?', 'What are the requirements?']
		},
		'fees': {
			keywords: ['fee', 'fees', 'cost', 'price', 'payment', 'how much', 'charge'],
			response: 'Document fees in Barangay Panungyanan vary by document type. Some services may have minimal fees while others are free for indigent residents. Check the Documents page when making a request to see applicable fees. Payment can usually be made at the barangay office upon approval.',
			related: ['How to request documents?', 'What documents are available?', 'What is indigency certificate?']
		},
		'online services': {
			keywords: ['online', 'digital', 'portal', 'website', 'internet', 'online services'],
			response: 'Barangay Panungyanan offers comprehensive online services through this digital portal. You can: request documents, report incidents, manage your profile, verify your ID, and access your digital resident ID - all online! Services are available 24/7. Just register, verify your ID, and start using our services.',
			related: ['How to register?', 'What services are available?', 'How to get started?']
		}
	};

	// Initialize chatbot UI
	root.innerHTML = `
		<button class="chatbot-toggle" id="chatbotToggle" aria-label="Open chatbot">
			<i class="fas fa-comments"></i>
			<span class="chatbot-badge" id="chatbotBadge" style="display: none;">1</span>
		</button>
		<div class="chatbot-panel" id="chatbotPanel">
			<div class="chatbot-header">
				<div class="d-flex align-items-center gap-2">
					<div class="chatbot-avatar">
						<i class="fas fa-robot"></i>
					</div>
					<div>
						<h6 class="mb-0 fw-bold text-white">Barangay Assistant</h6>
						<small class="text-muted">Online • Ready to help</small>
					</div>
				</div>
				<button class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">
					<i class="fas fa-times"></i>
				</button>
			</div>
			<div class="chatbot-messages" id="chatbotMessages">
				<div class="message bot-message">
					<div class="message-avatar">
						<i class="fas fa-robot"></i>
					</div>
					<div class="message-content">
						<p class="mb-0">Hello! I'm your Barangay Panungyanan Assistant. Welcome to our digital portal! I can help you with:</p>
						<ul class="mb-0 mt-2 small">
							<li><b>Document Services:</b> Clearance, Indigency, Residency certificates</li>
							<li><b>ID Verification:</b> Digital resident ID and verification process</li>
							<li><b>Incident Reporting:</b> Report emergencies and community concerns</li>
							<li><b>Barangay Info:</b> Location, services, programs, contact details</li>
							<li><b>Community Programs:</b> Health, education, safety, and social services</li>
							<li><b>Account Help:</b> Registration, login, profile management</li>
						</ul>
						<p class="mb-0 mt-2 small">Ask me anything about Barangay Panungyanan!</p>
					</div>
				</div>
			</div>
			<div class="chatbot-suggestions" id="chatbotSuggestions">
				<button class="suggestion-btn" data-query="Where is Barangay Panungyanan?">Location</button>
				<button class="suggestion-btn" data-query="How to request documents?">Request Documents</button>
				<button class="suggestion-btn" data-query="What community programs are available?">Programs</button>
				<button class="suggestion-btn" data-query="What is ID verification?">ID Verification</button>
			</div>
			<div class="chatbot-input-container">
				<input type="text" class="chatbot-input" id="chatbotInput" placeholder="Type your question here..." autocomplete="off">
				<button class="chatbot-send" id="chatbotSend" aria-label="Send message">
					<i class="fas fa-paper-plane"></i>
				</button>
			</div>
		</div>
	`;

	const panel = document.getElementById('chatbotPanel');
	const toggle = document.getElementById('chatbotToggle');
	const closeBtn = document.getElementById('chatbotClose');
	const input = document.getElementById('chatbotInput');
	const sendBtn = document.getElementById('chatbotSend');
	const messages = document.getElementById('chatbotMessages');
	const suggestions = document.getElementById('chatbotSuggestions');
	const badge = document.getElementById('chatbotBadge');
	
	// Support chat state
	let supportChatMode = false;
	let currentChatId = null;
	let lastMessageId = 0;
	let pollingInterval = null;
	let guestInfo = null; // Store guest info: {name, contact}
	let displayedMessageIds = new Set();

	
	// Check if user is logged in (check for session or any auth indicator)
	function isUserLoggedIn() {
		// Try to get from a hidden element or check session
		// For now, we'll check if there's a user session by trying to access a protected endpoint
		// Or we can add a data attribute to the chatbot root
		const rootData = root.getAttribute('data-user-logged-in');
		return rootData === 'true' || rootData === '1';
	}
	
	// Get guest info from localStorage
	function getGuestInfo() {
		const stored = localStorage.getItem('support_chat_guest_info');
		if (stored) {
			try {
				return JSON.parse(stored);
			} catch (e) {
				return null;
			}
		}
		return null;
	}
	
	// Save guest info to localStorage
	function saveGuestInfo(name, contact) {
		guestInfo = { name, contact };
		localStorage.setItem('support_chat_guest_info', JSON.stringify(guestInfo));
	}

	// Fuzzy matching function
	function findBestMatch(query) {
		const lowerQuery = query.toLowerCase().trim();
		let bestMatch = null;
		let bestScore = 0;

		// Priority check: location-related queries should match location first
		const locationKeywords = ['where is', 'location', 'address', 'where located', 'find barangay', 'directions', 'map', 'show map'];
		const isLocationQuery = locationKeywords.some(keyword => lowerQuery.includes(keyword));
		
		// If it's a location query, prioritize location entry
		if (isLocationQuery && lowerQuery.includes('panungyanan')) {
			const locationEntry = faqDatabase['location'];
			if (locationEntry) {
				return locationEntry;
			}
		}

		for (const [key, data] of Object.entries(faqDatabase)) {
			for (const keyword of data.keywords) {
				const keywordLower = keyword.toLowerCase();
				// Exact phrase match gets highest score
				if (lowerQuery.includes(keywordLower) || keywordLower.includes(lowerQuery)) {
					// Longer keywords get higher priority, especially if they match the full phrase
					const matchLength = Math.min(keywordLower.length, lowerQuery.length);
					const score = matchLength / Math.max(lowerQuery.length, 1);
					if (score > bestScore) {
						bestScore = score;
						bestMatch = data;
					}
				}
			}
		}

		// If no good match, check for partial word matches
		if (!bestMatch || bestScore < 0.3) {
			const words = lowerQuery.split(/\s+/);
			for (const [key, data] of Object.entries(faqDatabase)) {
				for (const keyword of data.keywords) {
					const keywordLower = keyword.toLowerCase();
					for (const word of words) {
						if (word.length > 3 && (keywordLower.includes(word) || word.includes(keywordLower))) {
							const score = 0.3 + (word.length / keywordLower.length) * 0.2;
							if (score > bestScore) {
								bestScore = score;
								bestMatch = data;
							}
						}
					}
				}
			}
		}

		return bestMatch;
	}

	// Add message to chat
	function addMessage(text, isBot = false, senderName = null) {
		const messageDiv = document.createElement('div');
		messageDiv.className = `message ${isBot ? 'bot-message' : 'user-message'}`;
		
		const icon = supportChatMode && isBot ? 'fas fa-user-headset' : (isBot ? 'fas fa-robot' : 'fas fa-user');
		const displayName = senderName ? `<small class="d-block mb-1 fw-bold">${senderName}</small>` : '';
		
		if (isBot) {
			messageDiv.innerHTML = `
				<div class="message-avatar">
					<i class="${icon}"></i>
				</div>
				<div class="message-content">
					${displayName}
					<p class="mb-0">${text}</p>
				</div>
			`;
		} else {
			messageDiv.innerHTML = `
				<div class="message-content">
					${displayName}
					<p class="mb-0">${text}</p>
				</div>
				<div class="message-avatar">
					<i class="${icon}"></i>
				</div>
			`;
		}
		
		messages.appendChild(messageDiv);
		messages.scrollTop = messages.scrollHeight;
		
		// Hide suggestions after first user message
		if (!isBot) {
			suggestions.style.display = 'none';
		}
	}

	// Show guest info form
	function showGuestForm() {
		messages.innerHTML = '';
		const formHtml = `
			<div class="message bot-message">
				<div class="message-avatar">
					<i class="fas fa-robot"></i>
				</div>
				<div class="message-content">
					<p class="mb-3"><b>Welcome to Support Chat!</b></p>
					<p class="mb-3">To start chatting with our support team, please provide your information:</p>
					<form id="guestInfoForm" class="mt-3">
						<div class="mb-3">
							<label class="form-label small fw-bold">Your Name *</label>
							<input type="text" class="form-control form-control-sm" id="guestName" required placeholder="Enter your full name">
						</div>
						<div class="mb-3">
							<label class="form-label small fw-bold">Email or Phone Number *</label>
							<input type="text" class="form-control form-control-sm" id="guestContact" required placeholder="email@example.com or +1234567890">
							<small class="text-muted">We'll use this to contact you if needed</small>
						</div>
						<button type="submit" class="btn btn-primary btn-sm w-100">
							<i class="fas fa-paper-plane me-2"></i>Start Chat
						</button>
					</form>
				</div>
			</div>
		`;
		messages.innerHTML = formHtml;
		
		// Handle form submission
		document.getElementById('guestInfoForm').addEventListener('submit', async (e) => {
			e.preventDefault();
			const name = document.getElementById('guestName').value.trim();
			const contact = document.getElementById('guestContact').value.trim();
			
			if (!name || !contact) {
				alert('Please fill in all required fields');
				return;
			}
			
			// Validate contact format
			const isEmail = contact.includes('@');
			const isPhone = /^[0-9+\-\s()]+$/.test(contact);
			
			if (!isEmail && !isPhone) {
				alert('Please provide a valid email address or phone number');
				return;
			}
			
			// Save guest info
			saveGuestInfo(name, contact);
			
			// Initialize chat with guest info
			await initSupportChat(name, contact);
		});
	}
	
	// Initialize support chat
	async function initSupportChat(guestName = null, guestContact = null) {
		try {
			// Check if user is logged in or has guest info
			const loggedIn = isUserLoggedIn();
			
			if (!loggedIn && !guestName && !guestContact) {
				// Try to get from localStorage
				const stored = getGuestInfo();
				if (stored) {
					guestName = stored.name;
					guestContact = stored.contact;
					guestInfo = stored;
				} else {
					// Show form
					showGuestForm();
					return;
				}
			}
			
			const formData = new FormData();
			formData.append('action', 'create_chat');
			
			if (!loggedIn && guestName && guestContact) {
				formData.append('guest_name', guestName);
				formData.append('guest_contact', guestContact);
			}
			
			const response = await fetch('/api/support_chat', {
				method: 'POST',
				body: formData
			});
			
			const data = await response.json();
			
			if (data.success) {
				supportChatMode = true;
				currentChatId = data.chat_id;
				lastMessageId = 0;
				
				// Update UI for support mode
				const header = panel.querySelector('.chatbot-header h6');
				const status = panel.querySelector('.chatbot-header small');
				if (header) header.textContent = 'Live Support';
				if (status) {
					status.textContent = data.assigned_admin_id 
						 ? 'Connected to Agent • Active'
						: 'Waiting for Administrator...';
					status.className = data.assigned_admin_id 
						? 'text-success' 
						: 'text-warning';
				}
				
				// Clear messages and show welcome
				messages.innerHTML = '';
				addMessage(
					data.assigned_admin_id
						? 'You\'ve been connected to a support agent. How can we help you today?'
						: 'Your support request has been received. An admin will be with you shortly.',
					true
				);
				
				// Start polling for messages
				startPolling();
				
				// Load existing messages
				loadMessages();
			} else if (data.error && data.error.includes('required')) {
				// Show form if info is required
				showGuestForm();
				addMessage('Please provide your information to continue.', true);
			} else if (data.error) {
				addMessage('Error connecting to support: ' + data.error, true);
			}
		} catch (error) {
			console.error('Error initializing support chat:', error);
			addMessage('Sorry, there was an error connecting to support. Please try again later.', true);
		}
	}
	
	// Load messages from server
	async function loadMessages() {
		if (!currentChatId) return;
		
		try {
			let url = `/api/support_chat?action=get_messages&chat_id=${currentChatId}&last_message_id=${lastMessageId}`;
			
			// Add guest contact if not logged in
			if (!isUserLoggedIn() && guestInfo) {
				url += `&guest_contact=${encodeURIComponent(guestInfo.contact)}`;
			}
			
			const response = await fetch(url);
			const data = await response.json();
			
			if (data.success && data.messages.length > 0) {
				data.messages.forEach(msg => {
                    const msgId = parseInt(msg.id);
                    if (displayedMessageIds.has(msgId)) {
                        lastMessageId = Math.max(lastMessageId, msgId);
                        return;
                    }
                    
					const isBot = msg.sender_type === 'admin';
                    displayedMessageIds.add(msgId);
					addMessage(msg.message, isBot, msg.full_name);
					lastMessageId = Math.max(lastMessageId, msgId);
				});
			}
			
			// Check chat status
			try {
				let statusUrl = `/api/support_chat?action=get_chat_status&chat_id=${currentChatId}`;
				if (!isUserLoggedIn() && guestInfo) {
					statusUrl += `&guest_contact=${encodeURIComponent(guestInfo.contact)}`;
				}
				
				const statusResponse = await fetch(statusUrl);
				const statusData = await statusResponse.json();
				if (statusData.success && statusData.status === 'closed') {
					// Disable input and show closed message
					input.disabled = true;
					input.placeholder = 'This chat has been closed';
					sendBtn.disabled = true;
					
					// Update header status
					const status = panel.querySelector('.chatbot-header small');
					if (status) {
						status.textContent = 'Chat Closed';
						status.className = 'text-secondary';
					}
					
					// Add closed notification if not already shown
					const messagesDiv = messages;
					const hasClosedMsg = Array.from(messagesDiv.children).some(child => 
						child.textContent.includes('Chat has been closed')
					);
					if (!hasClosedMsg) {
						const closedMsg = document.createElement('div');
						closedMsg.className = 'message bot-message';
						closedMsg.innerHTML = `
							<div class="message-avatar">
								<i class="fas fa-info-circle"></i>
							</div>
							<div class="message-content">
								<p class="mb-0"><b>Chat has been closed</b><br>This support chat has been closed by an administrator. If you need further assistance, please start a new chat.</p>
							</div>
						`;
						messagesDiv.appendChild(closedMsg);
						messagesDiv.scrollTop = messagesDiv.scrollHeight;
					}
				}
			} catch (error) {
				// Silently fail status check
				console.error('Error checking chat status:', error);
			}
		} catch (error) {
			console.error('Error loading messages:', error);
		}
	}
	
	// Start polling for new messages
	function startPolling() {
		if (pollingInterval) clearInterval(pollingInterval);
		pollingInterval = setInterval(loadMessages, 2000); // Poll every 2 seconds
	}
	
	// Stop polling
	function stopPolling() {
		if (pollingInterval) {
			clearInterval(pollingInterval);
			pollingInterval = null;
		}
	}
	
	// Show location map
	function showLocationMap() {
		// Generate unique ID for this map instance
		const mapId = 'barangayMap_' + Date.now();
		
		const mapContainer = document.createElement('div');
		mapContainer.className = 'location-map-container';
		mapContainer.innerHTML = `
			<div class="mb-2">
				<p class="mb-2"><b>Barangay Panungyanan Location</b></p>
				<p class="mb-2 small text-muted">General Trias, Cavite, Philippines</p>
			</div>
			<div id="${mapId}" style="width: 100%; height: 250px; border-radius: 8px; overflow: hidden; margin-bottom: 1rem;"></div>
			<div class="d-flex gap-2 flex-wrap">
				<a href="https://www.google.com/maps?q=14.235558660429684,120.92183621889586" target="_blank" class="btn btn-sm btn-outline-primary">
					<i class="fab fa-google me-1"></i> Open in Google Maps
				</a>
				<a href="https://waze.com/ul?ll=14.235558660429684,120.92183621889586&navigate=yes" target="_blank" class="btn btn-sm btn-outline-success">
					<i class="fab fa-waze me-1"></i> Open in Waze
				</a>
			</div>
		`;
		
		// Add to messages
		const messageDiv = document.createElement('div');
		messageDiv.className = 'message bot-message';
		messageDiv.innerHTML = `
			<div class="message-avatar">
				<i class="fas fa-robot"></i>
			</div>
			<div class="message-content">
				${mapContainer.outerHTML}
			</div>
		`;
		messages.appendChild(messageDiv);
		messages.scrollTop = messages.scrollHeight;
		
		// Initialize map after a short delay to ensure container is rendered
		setTimeout(() => {
			if (typeof L !== 'undefined') {
				const mapElement = document.getElementById(mapId);
				if (mapElement) {
					try {
						const map = L.map(mapId).setView([14.235558660429684, 120.92183621889586], 15);
						
						L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
							attribution: '© OpenStreetMap contributors',
							maxZoom: 19
						}).addTo(map);
						
						// Add marker
						const marker = L.marker([14.235558660429684, 120.92183621889586]).addTo(map);
						marker.bindPopup('<b>Barangay Panungyanan</b><br>General Trias, Cavite, Philippines').openPopup();
						
						console.log('Map initialized successfully');
					} catch (error) {
						console.error('Error initializing map:', error);
					}
				} else {
					console.error('Map element not found:', mapId);
				}
			} else {
				console.error('Leaflet library not loaded. Make sure Leaflet is included before chatbot.js');
			}
		}, 200);
	}
	
	// Generate response
	function generateResponse(query) {
		const match = findBestMatch(query);
		
		// Debug: log the match
		if (match) {
			console.log('Matched:', match.response, 'for query:', query);
		}
		
		if (match && match.response === 'SUPPORT_REQUEST') {
			// Trigger support chat
			initSupportChat();
			return null; // Don't add message, initSupportChat will handle it
		}
		
		if (match && match.response === 'MAP_LOCATION') {
			// Show location map
			console.log('Showing location map');
			showLocationMap();
			return null; // Don't add text message, map is shown
		}
		
		if (match) {
			let response = match.response;
			
			// Add related questions if available
			if (match.related && match.related.length > 0) {
				response += '\n\n<b>Related questions:</b>\n';
				match.related.forEach((q, i) => {
					response += `${i + 1}. ${q}\n`;
				});
			}
			
			return response;
		}
		
		// Default response for unmatched queries
		return `I understand you're asking about "${query}". Let me help you with information about Barangay Panungyanan. You can ask me about:\n\n• <b>Barangay Information:</b> Location, population, officials, history\n• <b>Document Services:</b> Clearance, Indigency, Residency certificates\n• <b>Community Programs:</b> Health, education, safety, social services\n• <b>ID Verification:</b> Digital resident ID and verification process\n• <b>Incident Reporting:</b> Report emergencies and concerns\n• <b>Account Management:</b> Registration, login, profile updates\n• <b>Contact & Hours:</b> Office location, phone, email, business hours\n\nTry asking: "Where is Barangay Panungyanan?" or "What community programs are available?" or "How to request documents?"\n\nNeed to speak with someone? Say "contact support" to connect with a live agent.`;
	}

	// Handle sending message
	async function handleSend() {
		const query = input.value.trim();
		if (!query) return;
		
		// If in support chat mode, send to server
		if (supportChatMode && currentChatId) {
			// Check if input is disabled (chat closed)
			if (input.disabled) {
				addMessage('This chat has been closed. Please start a new chat for further assistance.', true);
				return;
			}
			
			// Show temporary message state
			input.value = '';
			input.disabled = true;
			sendBtn.disabled = true;

			const tempDiv = document.createElement('div');
			tempDiv.className = 'message user-message temp-msg';
			tempDiv.innerHTML = `
				<div class="message-content">
					<p class="mb-0 text-muted">${String(query).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')} <i class="fas fa-spinner fa-spin ms-1" style="font-size: 0.7rem;"></i></p>
				</div>
				<div class="message-avatar">
					<i class="fas fa-user-clock"></i>
				</div>
			`;
			messages.appendChild(tempDiv);
			messages.scrollTop = messages.scrollHeight;
			
			try {
				const formData = new FormData();
				formData.append('action', 'send_message');
				formData.append('chat_id', currentChatId);
				formData.append('message', query);
				
				// Add guest contact if not logged in
				if (!isUserLoggedIn() && guestInfo) {
					formData.append('guest_contact', guestInfo.contact);
				}
				
				const response = await fetch('/api/support_chat', {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
                    // Real message will be loaded via loadMessages
					await loadMessages();
                } else {
					if (data.error && data.error.includes('closed')) {
						input.disabled = true;
						input.placeholder = 'This chat has been closed';
						sendBtn.disabled = true;
						addMessage('This chat has been closed by an administrator. Please start a new chat for further assistance.', true);
					} else if (data.error && data.error.includes('required')) {
						// Guest info might be missing, show form again
						showGuestForm();
						addMessage('Please provide your information to continue.', true);
					} else {
						addMessage('Sorry, there was an error sending your message. Please try again.', true);
					}
				}
			} catch (error) {
				console.error('Error sending message:', error);
				addMessage('Sorry, there was an error sending your message. Please try again.', true);
			} finally {
				if (tempDiv && tempDiv.parentNode) {
					tempDiv.remove();
				}
				if (!input.placeholder.includes('closed')) {
					input.disabled = false;
					sendBtn.disabled = false;
					input.focus();
				}
			}
			return;
		}
		
		// Normal FAQ mode
		addMessage(query, false);
		input.value = '';
		
		// Show typing indicator
		const typingDiv = document.createElement('div');
		typingDiv.className = 'message bot-message typing-indicator';
		typingDiv.innerHTML = `
			<div class="message-avatar">
				<i class="fas fa-robot"></i>
			</div>
			<div class="message-content">
				<div class="typing-dots">
					<span></span><span></span><span></span>
				</div>
			</div>
		`;
		messages.appendChild(typingDiv);
		messages.scrollTop = messages.scrollHeight;
		
		// Generate and show response after short delay
		setTimeout(() => {
			typingDiv.remove();
			const response = generateResponse(query);
			if (response !== null) {
				addMessage(response, true);
			}
		}, 800);
	}

	// Event listeners
	toggle.addEventListener('click', () => {
		panel.classList.add('active');
		toggle.style.display = 'none';
		badge.style.display = 'none';
		if (panel.classList.contains('active')) {
			input.focus();
		}
	});

	closeBtn.addEventListener('click', () => {
		panel.classList.remove('active');
		toggle.style.display = 'flex';
		stopPolling();
	});

	sendBtn.addEventListener('click', handleSend);

	input.addEventListener('keydown', (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	});

	// Suggestion buttons
	document.querySelectorAll('.suggestion-btn').forEach(btn => {
		btn.addEventListener('click', () => {
			input.value = btn.dataset.query;
			handleSend();
		});
	});

	// Close on outside click (optional)
	document.addEventListener('click', (e) => {
		if (panel.classList.contains('active') && 
			!panel.contains(e.target) && 
			!toggle.contains(e.target)) {
			panel.classList.remove('active');
			toggle.style.display = 'flex';
			stopPolling();
		}
	});
	
	// Cleanup on page unload
	window.addEventListener('beforeunload', () => {
		stopPolling();
	});
})();
