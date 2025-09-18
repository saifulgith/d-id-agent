// D-ID Agent Integration for WordPress
// This script handles the frontend integration with the D-ID SDK

class DIDAgentIntegration {
    constructor() {
        this.agentInstances = new Map();
        this.init();
    }
    
    async init() {
        // Wait for D-ID SDK to load
        if (typeof window.createAgentManager === 'undefined') {
            console.error('D-ID SDK not loaded');
            return;
        }
        
        // Initialize all agent containers on the page
        const containers = document.querySelectorAll('.did-agent-container');
        for (const container of containers) {
            await this.initializeAgent(container);
        }
    }
    
    async initializeAgent(container) {
        const agentId = container.dataset.agentId;
        const theme = container.dataset.theme || 'default';
        
        if (!agentId) {
            this.showError(container, 'Agent ID is required');
            return;
        }
        
        try {
            // Show loading state
            this.showLoading(container);
            
            // Get client key from backend
            const clientKey = await this.getClientKey(agentId);
            
            if (!clientKey) {
                throw new Error('Failed to get client key from backend');
            }
            
            // Initialize the agent
            await this.createAgent(container, agentId, clientKey, theme);
            
        } catch (error) {
            console.error('Failed to initialize agent:', error);
            this.showError(container, error.message);
        }
    }
    
    async getClientKey(agentId) {
        try {
            const response = await fetch(`${didAgentConfig.backendUrl}/api/client-key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    agentId: agentId,
                    allowed_origins: [window.location.origin]
                })
            });
            
            if (!response.ok) {
                throw new Error(`Backend error: ${response.status}`);
            }
            
            const data = await response.json();
            return data.clientKey || data.key || data.token;
            
        } catch (error) {
            console.error('Error getting client key:', error);
            throw new Error('Failed to connect to backend server');
        }
    }
    
    async createAgent(container, agentId, clientKey, theme) {
        const auth = { type: 'key', clientKey: clientKey };
        
        // Get DOM elements for this specific agent
        const elements = this.getAgentElements(container, agentId);
        
        // Define callbacks
        const callbacks = {
            onSrcObjectReady: (value) => {
                console.log('SrcObject Ready for agent:', agentId);
                elements.streamVideo.srcObject = value;
                return value;
            },
            
            onConnectionStateChange: (state) => {
                console.log('Connection State for agent', agentId, ':', state);
                this.handleConnectionStateChange(container, elements, state, agentId);
            },
            
            onVideoStateChange: (state) => {
                console.log('Video State for agent', agentId, ':', state);
                this.handleVideoStateChange(container, elements, state);
            },
            
            onNewMessage: (messages, type) => {
                this.handleNewMessage(container, elements, messages, type, agentId);
            },
            
            onAgentActivityStateChange: (state) => {
                this.handleAgentActivityStateChange(container, elements, state);
            },
            
            onError: (error, errorData) => {
                console.error('Agent error for', agentId, ':', error, errorData);
                this.showError(container, 'Agent error: ' + error);
            }
        };
        
        // Stream options
        const streamOptions = {
            compatibilityMode: "auto",
            streamWarmup: true,
            fluent: true
        };
        
        // Create agent manager
        const agentManager = await window.createAgentManager(agentId, {
            auth,
            callbacks,
            streamOptions
        });
        
        // Store instance
        this.agentInstances.set(agentId, agentManager);
        
        // Connect
        await agentManager.connect();
        
        // Set up event listeners
        this.setupEventListeners(container, elements, agentManager, agentId);
        
        // Hide loading, show interface
        this.hideLoading(container);
        this.showInterface(container);
    }
    
    getAgentElements(container, agentId) {
        return {
            streamVideo: container.querySelector(`#streamVideoElement-${agentId}`),
            idleVideo: container.querySelector(`#idleVideoElement-${agentId}`),
            connectionLabel: container.querySelector(`#connectionLabel-${agentId}`),
            answers: container.querySelector(`#answers-${agentId}`),
            textArea: container.querySelector(`#textArea-${agentId}`),
            actionButton: container.querySelector(`#actionButton-${agentId}`),
            speechButton: container.querySelector(`#speechButton-${agentId}`),
            interruptButton: container.querySelector(`#interruptButton-${agentId}`),
            reconnectButton: container.querySelector(`#reconnectButton-${agentId}`),
            videoContainer: container.querySelector(`#video-container-${agentId}`)
        };
    }
    
    setupEventListeners(container, elements, agentManager, agentId) {
        // Send message
        elements.actionButton.addEventListener('click', () => {
            this.handleAction(container, elements, agentManager, agentId);
        });
        
        // Enter key to send
        elements.textArea.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.handleAction(container, elements, agentManager, agentId);
            }
        });
        
        // Tab to switch modes
        container.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                event.preventDefault();
                this.switchModes(container, agentId);
            }
        });
        
        // Speech button
        elements.speechButton.addEventListener('click', () => {
            this.toggleSpeech(container, elements, agentManager, agentId);
        });
        
        // Interrupt button
        elements.interruptButton.addEventListener('click', () => {
            agentManager.interrupt({ type: "click" });
        });
        
        // Reconnect button
        elements.reconnectButton.addEventListener('click', () => {
            agentManager.reconnect();
        });
    }
    
    handleAction(container, elements, agentManager, agentId) {
        const text = elements.textArea.value.trim();
        if (!text) return;
        
        const selectedMode = container.querySelector(`input[name="option-${agentId}"]:checked`).value;
        
        if (selectedMode === 'chat') {
            agentManager.chat(text);
            elements.connectionLabel.textContent = 'Thinking...';
        } else if (selectedMode === 'speak') {
            agentManager.speak({
                type: "text",
                input: text
            });
        }
        
        elements.textArea.value = '';
    }
    
    switchModes(container, agentId) {
        const options = container.querySelectorAll(`input[name="option-${agentId}"]`);
        const checkedIndex = Array.from(options).findIndex(opt => opt.checked);
        const nextIndex = (checkedIndex + 1) % options.length;
        options[nextIndex].checked = true;
    }
    
    toggleSpeech(container, elements, agentManager, agentId) {
        // Basic speech implementation - you can enhance this
        if ('webkitSpeechRecognition' in window) {
            const recognition = new webkitSpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                elements.textArea.value = transcript;
                this.handleAction(container, elements, agentManager, agentId);
            };
            
            recognition.start();
        } else {
            alert('Speech recognition not supported in this browser');
        }
    }
    
    handleConnectionStateChange(container, elements, state, agentId) {
        if (state === 'connecting') {
            elements.connectionLabel.textContent = 'Connecting...';
            container.classList.add('connecting');
        } else if (state === 'connected') {
            elements.connectionLabel.textContent = 'Connected';
            elements.actionButton.disabled = false;
            elements.speechButton.disabled = false;
            container.classList.remove('connecting');
            container.classList.add('connected');
        } else if (state === 'disconnected' || state === 'closed') {
            elements.connectionLabel.textContent = 'Disconnected';
            elements.actionButton.disabled = true;
            elements.speechButton.disabled = true;
            container.classList.remove('connected');
            container.classList.add('disconnected');
        }
    }
    
    handleVideoStateChange(container, elements, state) {
        if (state === 'START') {
            elements.videoContainer.classList.add('streaming');
        } else {
            elements.videoContainer.classList.remove('streaming');
        }
    }
    
    handleNewMessage(container, elements, messages, type, agentId) {
        const lastMessage = messages[messages.length - 1];
        if (!lastMessage) return;
        
        if (lastMessage.role === 'assistant' && type === 'answer') {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'agent-message';
            messageDiv.textContent = lastMessage.content;
            elements.answers.appendChild(messageDiv);
        } else if (lastMessage.role === 'user') {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'user-message';
            messageDiv.textContent = lastMessage.content;
            elements.answers.appendChild(messageDiv);
        }
        
        // Scroll to bottom
        elements.answers.scrollTop = elements.answers.scrollHeight;
    }
    
    handleAgentActivityStateChange(container, elements, state) {
        if (state === 'TALKING') {
            elements.interruptButton.style.display = 'inline-flex';
            elements.speechButton.style.display = 'none';
            elements.actionButton.style.display = 'none';
        } else {
            elements.interruptButton.style.display = 'none';
            elements.speechButton.style.display = 'inline-flex';
            elements.actionButton.style.display = 'inline-flex';
        }
    }
    
    showLoading(container) {
        container.querySelector('.did-agent-loading').style.display = 'block';
        container.querySelector('.did-agent-error').style.display = 'none';
        container.querySelector('.did-agent-interface').style.display = 'none';
    }
    
    hideLoading(container) {
        container.querySelector('.did-agent-loading').style.display = 'none';
    }
    
    showInterface(container) {
        container.querySelector('.did-agent-interface').style.display = 'block';
    }
    
    showError(container, message) {
        container.querySelector('.did-agent-loading').style.display = 'none';
        container.querySelector('.did-agent-interface').style.display = 'none';
        const errorDiv = container.querySelector('.did-agent-error');
        errorDiv.style.display = 'block';
        errorDiv.querySelector('p').textContent = message;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new DIDAgentIntegration();
});
