// Aria AI Chat Assistant - COMPLETE FIX
document.addEventListener('DOMContentLoaded', function() {
    console.log('ARIA: Starting...');
    
    // Get elements
    const orb = document.getElementById('aria-orb');
    const panel = document.getElementById('aria-panel');
    const closeBtn = document.getElementById('aria-close');
    const input = document.getElementById('aria-input');
    const sendBtn = document.getElementById('aria-send');
    const messagesContainer = document.getElementById('aria-messages');
    
    // Remove any existing typing indicator to avoid duplicates
    const existingTyping = document.getElementById('aria-typing');
    if (existingTyping) existingTyping.remove();
    
    // Create typing indicator element
    const typingIndicator = document.createElement('div');
    typingIndicator.id = 'aria-typing';
    typingIndicator.className = 'aria-typing';
    typingIndicator.style.display = 'none';
    typingIndicator.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
    
    // Add typing indicator after messages container
    if (messagesContainer && messagesContainer.parentNode) {
        messagesContainer.parentNode.insertBefore(typingIndicator, messagesContainer.nextSibling);
    }
    
    let isOpen = false;
    
    // Ensure panel starts hidden
    if (panel) {
        panel.style.display = 'none';
    }
    
    // Open/close panel
    if (orb) {
        orb.addEventListener('click', function(e) {
            e.stopPropagation();
            isOpen = !isOpen;
            if (panel) {
                panel.style.display = isOpen ? 'flex' : 'none';
                if (isOpen && input) {
                    setTimeout(function() { input.focus(); }, 100);
                }
            }
        });
    }
    
    // Close panel when X is clicked
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            isOpen = false;
            if (panel) panel.style.display = 'none';
        });
    }
    
    // Prevent panel from closing when clicking inside it
    if (panel) {
        panel.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (isOpen && panel && orb && !panel.contains(e.target) && !orb.contains(e.target)) {
            isOpen = false;
            panel.style.display = 'none';
        }
    });
    
    // Function to add message to chat
    function addMessage(text, sender) {
        if (!messagesContainer) return;
        
        const msgDiv = document.createElement('div');
        msgDiv.className = 'aria-msg ' + sender;
        const bubble = document.createElement('div');
        bubble.className = 'aria-bubble';
        bubble.textContent = text;
        msgDiv.appendChild(bubble);
        messagesContainer.appendChild(msgDiv);
        
        // Scroll to bottom
        setTimeout(function() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 0);
    }
    
    // Show/hide typing indicator
    function showTyping() {
        if (typingIndicator) typingIndicator.style.display = 'flex';
        if (messagesContainer) {
            setTimeout(function() {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 0);
        }
    }
    
    function hideTyping() {
        if (typingIndicator) typingIndicator.style.display = 'none';
    }
    
    // Send message function
    async function sendMessage() {
        if (!input) return;
        
        const message = input.value.trim();
        if (!message) return;
        
        console.log('ARIA: Sending:', message);
        
        // Add user message
        addMessage(message, 'user');
        input.value = '';
        
        // Show typing indicator
        showTyping();
        
        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });
            
            const data = await response.json();
            hideTyping();
            
            if (data.response) {
                addMessage(data.response, 'bot');
                console.log('ARIA: Response received');
            } else if (data.error) {
                addMessage('⚠️ ' + data.error, 'bot');
            } else {
                addMessage('Sorry, I didn\'t understand that.', 'bot');
            }
        } catch (error) {
            console.error('ARIA: Error:', error);
            hideTyping();
            addMessage('🔌 Connection error. Please try again.', 'bot');
        }
    }
    
    // Event listeners
    if (sendBtn) {
        sendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }
    
    if (input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // Image upload
    const uploadBtn = document.getElementById('aria-upload');
    if (uploadBtn) {
        // Remove existing file input if any
        const existingFileInput = document.getElementById('aria-file-input');
        if (existingFileInput) existingFileInput.remove();
        
        const fileInput = document.createElement('input');
        fileInput.id = 'aria-file-input';
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';
        document.body.appendChild(fileInput);
        
        // Remove old event listeners by replacing button
        const newUploadBtn = uploadBtn.cloneNode(true);
        uploadBtn.parentNode.replaceChild(newUploadBtn, uploadBtn);
        
        newUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
        
        fileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            console.log('ARIA: Image selected:', file.name);
            addMessage('📷 Analyzing your image...', 'user');
            showTyping();
            
            const reader = new FileReader();
            reader.onloadend = async function() {
                const base64 = reader.result.split(',')[1];
                
                try {
                    const response = await fetch('/api/chat', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            message: 'What destination does this image show?',
                            image: base64 
                        })
                    });
                    
                    const data = await response.json();
                    hideTyping();
                    
                    if (data.response) {
                        addMessage(data.response, 'bot');
                    } else if (data.error) {
                        addMessage('⚠️ ' + data.error, 'bot');
                    }
                } catch (error) {
                    console.error('ARIA: Image error:', error);
                    hideTyping();
                    addMessage('🔌 Image analysis failed.', 'bot');
                }
            };
            reader.readAsDataURL(file);
            fileInput.value = '';
        });
    }
    
    console.log('ARIA: Ready!');
});