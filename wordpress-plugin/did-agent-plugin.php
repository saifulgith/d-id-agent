<?php
/**
 * Plugin Name: D-ID Agent Integration
 * Plugin URI: https://github.com/saifulgith/d-id-agent
 * Description: Integrate D-ID AI Agents into your WordPress site with secure backend API.
 * Version: 1.0.0
 * Author: Saiful
 * License: MIT
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentPlugin {
    
    private $backend_url;
    
    public function __construct() {
        $this->backend_url = get_option('did_backend_url', '');
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function enqueue_scripts() {
        // Only load on pages that have the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'did_agent')) {
            
            // Load D-ID SDK with proper module handling
            wp_add_inline_script('jquery', '
                // Load D-ID SDK as ES module
                const script = document.createElement("script");
                script.type = "module";
                script.innerHTML = `
                    import { createAgentManager, StreamType } from "https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js";
                    window.createAgentManager = createAgentManager;
                    window.StreamType = StreamType;
                    console.log("D-ID SDK loaded as ES module");
                `;
                document.head.appendChild(script);
            ');
            
            // Enqueue our custom integration script
            wp_enqueue_script(
                'did-agent-integration',
                plugin_dir_url(__FILE__) . 'js/did-agent-integration.js',
                array('did-client-sdk'),
                '2.0.0', // Updated version to force cache refresh
                true
            );
            
            // Enqueue styles
            wp_enqueue_style(
                'did-agent-styles',
                plugin_dir_url(__FILE__) . 'css/did-agent-styles.css',
                array(),
                '1.0.0'
            );
            
            // Pass backend URL to JavaScript
            wp_localize_script('did-agent-integration', 'didAgentConfig', array(
                'backendUrl' => $this->backend_url,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('did_agent_nonce')
            ));
            
            // Also make it available globally
            wp_add_inline_script('jquery', '
                window.didAgentConfig = {
                    backendUrl: "' . esc_js($this->backend_url) . '",
                    ajaxUrl: "' . esc_js(admin_url('admin-ajax.php')) . '",
                    nonce: "' . esc_js(wp_create_nonce('did_agent_nonce')) . '"
                };
                console.log("Global config set:", window.didAgentConfig);
            ');
            
            // Add direct test message and inline integration
            wp_add_inline_script('jquery', '
                console.log("URGENT TEST: PHP FILE UPDATED SUCCESSFULLY!");
                console.log("DIRECT TEST: Plugin is loading JavaScript file!");
                
                // Define the class first, outside any function
                window.DIDAgentIntegration = class {
                    constructor() {
                        this.agentInstances = new Map();
                        this.init();
                    }
                    
                    async init() {
                        console.log("INLINE VERSION: Starting agent integration");
                        
                        // Intercept fetch requests to redirect D-ID API calls to our backend
                        const originalFetch = window.fetch;
                        window.fetch = function(url, options) {
                            options = options || {};
                            if (typeof url === "string" && url.indexOf("api.d-id.com") !== -1) {
                                const newUrl = url.replace("https://api.d-id.com", window.didAgentConfig.backendUrl + "/api");
                                console.log("Redirecting D-ID API call: " + url + " -> " + newUrl);
                                return originalFetch(newUrl, options);
                            }
                            return originalFetch(url, options);
                        };
                        
                        // Intercept WebSocket connections to redirect to our backend
                        const originalWebSocket = window.WebSocket;
                        window.WebSocket = function(url, protocols) {
                            if (typeof url === "string" && url.indexOf("notifications.d-id.com") !== -1) {
                                const newUrl = url.replace("wss://notifications.d-id.com", window.didAgentConfig.backendUrl.replace("https://", "wss://") + "/api/notifications");
                                console.log("Redirecting D-ID WebSocket: " + url + " -> " + newUrl);
                                return new originalWebSocket(newUrl, protocols);
                            }
                            return new originalWebSocket(url, protocols);
                        };
                        
                        // Wait for D-ID SDK to load
                        let retries = 0;
                        const maxRetries = 10;
                        
                        while (typeof window.createAgentManager === "undefined" && retries < maxRetries) {
                            console.log("Waiting for D-ID SDK to load...", retries + 1);
                            await new Promise(resolve => setTimeout(resolve, 500));
                            retries++;
                        }
                        
                        if (typeof window.createAgentManager === "undefined") {
                            console.error("D-ID SDK not loaded after", maxRetries, "retries");
                            return;
                        }
                        
                        console.log("D-ID SDK loaded successfully");
                        
                        // Initialize all agent containers
                        const containers = document.querySelectorAll(".did-agent-container");
                        console.log("Found", containers.length, "agent containers");
                        
                        for (const container of containers) {
                            await this.initializeAgent(container);
                        }
                    }
                    
                    async initializeAgent(container) {
                        const agentId = container.dataset.agentId;
                        console.log("Initializing agent:", agentId);
                        
                        if (!agentId) {
                            this.showError(container, "Agent ID is required");
                            return;
                        }
                        
                        try {
                            this.showLoading(container);
                            
                            console.log("Getting client key from backend...");
                            const clientKey = await this.getClientKey(agentId);
                            console.log("Client key received:", clientKey ? "Yes" : "No");
                            
                            if (!clientKey) {
                                throw new Error("Failed to get client key from backend");
                            }
                            
                            console.log("Creating agent with client key...");
                            await this.createAgent(container, agentId, clientKey);
                            
                        } catch (error) {
                            console.error("Failed to initialize agent:", error);
                            this.showError(container, error.message);
                        }
                    }
                    
                    async getClientKey(agentId) {
                        try {
                            console.log("Fetching from:", window.didAgentConfig.backendUrl + "/api/client-key");
                            console.log("Current origin:", window.location.origin);
                            console.log("Current URL:", window.location.href);
                            
                            const response = await fetch(window.didAgentConfig.backendUrl + "/api/client-key", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                    "Accept": "application/json"
                                },
                                body: JSON.stringify({
                                    agentId: agentId,
                                    allowed_origins: [window.location.origin]
                                })
                            });
                            
                            console.log("Response status:", response.status);
                            console.log("Response headers:", [...response.headers.entries()]);
                            
                            if (!response.ok) {
                                const errorText = await response.text();
                                console.error("Backend error response:", errorText);
                                throw new Error("Backend error: " + response.status + " - " + errorText);
                            }
                            
                            const data = await response.json();
                            console.log("Backend response data:", data);
                            return data.client_key || data.clientKey || data.key || data.token;
                            
                        } catch (error) {
                            console.error("Error getting client key:", error);
                            if (error.name === "TypeError" && error.message.includes("Failed to fetch")) {
                                throw new Error("CORS error: Unable to connect to backend server. Please check CORS configuration.");
                            }
                            throw new Error("Failed to connect to backend server: " + error.message);
                        }
                    }
                    
                    async createAgent(container, agentId, clientKey) {
                        console.log("Creating agent with ID:", agentId);
                        
                        const auth = { type: "key", clientKey: clientKey };
                        
                        const elements = this.getAgentElements(container, agentId);
                        
                        const callbacks = {
                            onSrcObjectReady: (value) => {
                                console.log("SrcObject Ready for agent:", agentId);
                                elements.streamVideo.srcObject = value;
                                return value;
                            },
                            
                            onVideoStateChange: (state) => {
                                console.log("Video State for agent", agentId, ":", state);
                                if (state === "PLAYING") {
                                    console.log("Video is now playing for agent:", agentId);
                                }
                            },
                            
                            onConnectionStateChange: (state) => {
                                console.log("Connection State for agent", agentId, ":", state);
                                this.handleConnectionStateChange(container, elements, state, agentId);
                            },
                            
                            onVideoStateChange: (state) => {
                                console.log("Video State for agent", agentId, ":", state);
                                this.handleVideoStateChange(container, elements, state);
                            },
                            
                            onNewMessage: (messages, type) => {
                                this.handleNewMessage(container, elements, messages, type, agentId);
                            },
                            
                            onError: (error, errorData) => {
                                console.error("Agent error for", agentId, ":", error, errorData);
                                this.showError(container, "Agent error: " + error);
                            }
                        };
                        
                        const streamOptions = {
                            compatibilityMode: "auto",
                            streamWarmup: true,
                            fluent: true
                        };
                        
                        // Try using the D-ID SDK without custom apiBaseUrl first
                        const agentManager = await window.createAgentManager(agentId, {
                            auth,
                            callbacks,
                            streamOptions
                        });
                        
                        this.agentInstances.set(agentId, agentManager);
                        await agentManager.connect();
                        
                        this.setupEventListeners(container, elements, agentManager, agentId);
                        this.hideLoading(container);
                        this.showInterface(container);
                        
                        // Test: Send a greeting message to start the agent
                        setTimeout(() => {
                            console.log("Sending test message to start agent...");
                            agentManager.sendMessage("Hello! Can you introduce yourself?");
                        }, 2000);
                    }
                    
                    getAgentElements(container, agentId) {
                        return {
                            streamVideo: container.querySelector("#streamVideoElement-" + agentId),
                            idleVideo: container.querySelector("#idleVideoElement-" + agentId),
                            connectionLabel: container.querySelector("#connectionLabel-" + agentId),
                            answers: container.querySelector("#answers-" + agentId),
                            textArea: container.querySelector("#textArea-" + agentId),
                            actionButton: container.querySelector("#actionButton-" + agentId),
                            speechButton: container.querySelector("#speechButton-" + agentId),
                            interruptButton: container.querySelector("#interruptButton-" + agentId),
                            reconnectButton: container.querySelector("#reconnectButton-" + agentId),
                            videoContainer: container.querySelector("#video-container-" + agentId)
                        };
                    }
                    
                    setupEventListeners(container, elements, agentManager, agentId) {
                        elements.actionButton.addEventListener("click", () => {
                            this.handleAction(container, elements, agentManager, agentId);
                        });
                        
                        elements.textArea.addEventListener("keypress", (event) => {
                            if (event.key === "Enter") {
                                event.preventDefault();
                                this.handleAction(container, elements, agentManager, agentId);
                            }
                        });
                        
                        elements.reconnectButton.addEventListener("click", () => {
                            agentManager.reconnect();
                        });
                    }
                    
                    handleAction(container, elements, agentManager, agentId) {
                        const text = elements.textArea.value.trim();
                        if (!text) return;
                        
                        const selectedMode = container.querySelector("input[name=\"option-" + agentId + "\"]:checked").value;
                        
                        if (selectedMode === "chat") {
                            agentManager.chat(text);
                            elements.connectionLabel.textContent = "Thinking...";
                        } else if (selectedMode === "speak") {
                            agentManager.speak({
                                type: "text",
                                input: text
                            });
                        }
                        
                        elements.textArea.value = "";
                    }
                    
                    handleConnectionStateChange(container, elements, state, agentId) {
                        if (state === "connecting") {
                            elements.connectionLabel.textContent = "Connecting...";
                            container.classList.add("connecting");
                        } else if (state === "connected") {
                            elements.connectionLabel.textContent = "Connected";
                            elements.actionButton.disabled = false;
                            elements.speechButton.disabled = false;
                            container.classList.remove("connecting");
                            container.classList.add("connected");
                        } else if (state === "disconnected" || state === "closed") {
                            elements.connectionLabel.textContent = "Disconnected";
                            elements.actionButton.disabled = true;
                            elements.speechButton.disabled = true;
                            container.classList.remove("connected");
                            container.classList.add("disconnected");
                        }
                    }
                    
                    handleVideoStateChange(container, elements, state) {
                        if (state === "START") {
                            elements.videoContainer.classList.add("streaming");
                        } else {
                            elements.videoContainer.classList.remove("streaming");
                        }
                    }
                    
                    handleNewMessage(container, elements, messages, type, agentId) {
                        const lastMessage = messages[messages.length - 1];
                        if (!lastMessage) return;
                        
                        if (lastMessage.role === "assistant" && type === "answer") {
                            const messageDiv = document.createElement("div");
                            messageDiv.className = "agent-message";
                            messageDiv.textContent = lastMessage.content;
                            elements.answers.appendChild(messageDiv);
                        } else if (lastMessage.role === "user") {
                            const messageDiv = document.createElement("div");
                            messageDiv.className = "user-message";
                            messageDiv.textContent = lastMessage.content;
                            elements.answers.appendChild(messageDiv);
                        }
                        
                        elements.answers.scrollTop = elements.answers.scrollHeight;
                    }
                    
                    showLoading(container) {
                        container.querySelector(".did-agent-loading").style.display = "block";
                        container.querySelector(".did-agent-error").style.display = "none";
                        container.querySelector(".did-agent-interface").style.display = "none";
                    }
                    
                    hideLoading(container) {
                        container.querySelector(".did-agent-loading").style.display = "none";
                    }
                    
                    showInterface(container) {
                        container.querySelector(".did-agent-interface").style.display = "block";
                    }
                    
                    showError(container, message) {
                        container.querySelector(".did-agent-loading").style.display = "none";
                        container.querySelector(".did-agent-interface").style.display = "none";
                        const errorDiv = container.querySelector(".did-agent-error");
                        errorDiv.style.display = "block";
                        errorDiv.querySelector("p").textContent = message;
                    }
                };
                
                // Debug: Check if class was defined
                console.log("🔍 DIDAgentIntegration class defined:", typeof window.DIDAgentIntegration);
                
                // Start the integration immediately since config is already available
                console.log("🚀 Starting integration process...");
                
                // Create and start the integration
                console.log("DIDAgentIntegration class found, creating instance...");
                const integration = new window.DIDAgentIntegration();
                console.log("Integration instance created:", integration);
            ');
        }
    }
    
    public function render_agent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'agent_id' => '',
            'width' => '100%',
            'height' => '500px',
            'theme' => 'default'
        ), $atts);
        
        if (empty($atts['agent_id'])) {
            return '<div class="did-agent-error">Error: Agent ID is required. Use [did_agent agent_id="your_agent_id"]</div>';
        }
        
        if (empty($this->backend_url)) {
            return '<div class="did-agent-error">Error: Backend URL not configured. Please set it in the admin settings.</div>';
        }
        
        $agent_id = esc_attr($atts['agent_id']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        $theme = esc_attr($atts['theme']);
        
        ob_start();
        ?>
        <script>console.log("SHORTCODE TEST: Agent shortcode is rendering!");</script>
        <div class="did-agent-container" 
             data-agent-id="<?php echo $agent_id; ?>"
             data-theme="<?php echo $theme; ?>"
             style="width: <?php echo $width; ?>; height: <?php echo $height; ?>;">
            
            <!-- Loading State -->
            <div class="did-agent-loading" id="did-loading-<?php echo $agent_id; ?>">
                <div class="loading-spinner"></div>
                <p>Connecting to AI Agent...</p>
            </div>
            
            <!-- Error State -->
            <div class="did-agent-error" id="did-error-<?php echo $agent_id; ?>" style="display: none;">
                <p>Failed to connect to AI Agent. Please try again later.</p>
            </div>
            
            <!-- Agent Interface -->
            <div class="did-agent-interface" id="did-interface-<?php echo $agent_id; ?>" style="display: none;">
                
                <!-- Video Container -->
                <div class="video-container" id="video-container-<?php echo $agent_id; ?>">
                    <video id="streamVideoElement-<?php echo $agent_id; ?>" autoplay muted playsinline></video>
                    <video id="idleVideoElement-<?php echo $agent_id; ?>" autoplay muted playsinline loop></video>
                </div>
                
                <!-- Connection Status -->
                <div class="connection-status" id="connectionLabel-<?php echo $agent_id; ?>">
                    Connecting...
                </div>
                
                <!-- Chat Interface -->
                <div class="chat-interface">
                    <div class="chat-messages" id="answers-<?php echo $agent_id; ?>"></div>
                    
                    <!-- Input Controls -->
                    <div class="input-controls">
                        <div class="mode-selection">
                            <label>
                                <input type="radio" name="option-<?php echo $agent_id; ?>" value="chat" checked>
                                Chat
                            </label>
                            <label>
                                <input type="radio" name="option-<?php echo $agent_id; ?>" value="speak">
                                Speak
                            </label>
                        </div>
                        
                        <div class="input-group">
                            <textarea 
                                id="textArea-<?php echo $agent_id; ?>" 
                                placeholder="Type your message here..."
                                rows="2"></textarea>
                            <button id="actionButton-<?php echo $agent_id; ?>" class="send-button">
                                Send
                            </button>
                        </div>
                        
                        <div class="action-buttons">
                            <button id="speechButton-<?php echo $agent_id; ?>" class="speech-button" disabled>
                                Speech
                            </button>
                            <button id="interruptButton-<?php echo $agent_id; ?>" class="interrupt-button" style="display: none;">
                                Interrupt
                            </button>
                            <button id="reconnectButton-<?php echo $agent_id; ?>" class="reconnect-button">
                                Reconnect
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'D-ID Agent Settings',
            'D-ID Agent',
            'manage_options',
            'did-agent-settings',
            array($this, 'admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('did_agent_settings', 'did_backend_url');
        add_settings_section(
            'did_agent_main',
            'Backend Configuration',
            null,
            'did-agent-settings'
        );
        add_settings_field(
            'did_backend_url',
            'Backend URL',
            array($this, 'backend_url_callback'),
            'did-agent-settings',
            'did_agent_main'
        );
    }
    
    public function backend_url_callback() {
        $value = get_option('did_backend_url', '');
        echo '<input type="url" name="did_backend_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://your-backend.onrender.com" />';
        echo '<p class="description">Enter your Render backend URL (e.g., https://your-backend.onrender.com)</p>';
    }
    
    public function admin_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            update_option('did_backend_url', sanitize_url($_POST['did_backend_url']));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        
        $backend_url = get_option('did_backend_url', '');
        ?>
        <div class="wrap">
            <h1>D-ID Agent Settings</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="did_backend_url">Backend URL</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="did_backend_url" 
                                   name="did_backend_url" 
                                   value="<?php echo esc_attr($backend_url); ?>" 
                                   class="regular-text" 
                                   placeholder="https://your-backend.onrender.com" />
                            <p class="description">Enter your Render backend URL (e.g., https://your-backend.onrender.com)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Changes'); ?>
            </form>
            
            <div class="card" style="margin-top: 20px; padding: 20px; border: 1px solid #ccd0d4; background: #fff;">
                <h2>How to Use</h2>
                <ol>
                    <li>Set your backend URL above (from Render deployment)</li>
                    <li>Get your Agent ID from <a href="https://studio.d-id.com" target="_blank">D-ID Studio</a></li>
                    <li>Use the shortcode: <code>[did_agent agent_id="your_agent_id"]</code></li>
                    <li>Customize with options: <code>[did_agent agent_id="your_agent_id" width="100%" height="600px" theme="dark"]</code></li>
                </ol>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new DIDAgentPlugin();
