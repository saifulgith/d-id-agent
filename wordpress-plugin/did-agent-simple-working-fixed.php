<?php
/**
 * Plugin Name: D-ID Agent Simple Working Fixed
 * Description: Simple working version based on official D-ID SDK - FIXED
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentSimpleWorkingFixed {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_simple', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function init() {
        $this->backend_url = get_option('did_agent_backend_url', 'https://d-id-agent-1sdx.onrender.com');
    }
    
    public function enqueue_scripts() {
        // Load D-ID SDK as ES module
        wp_add_inline_script('jquery', '
            console.log("🚀 D-ID Agent Simple Working Fixed - Loading SDK");
            
            // Load SDK as ES module
            const script = document.createElement("script");
            script.type = "module";
            script.innerHTML = `
                import * as sdk from "https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js";
                
                // Make SDK available globally
                window.DID = sdk;
                window.createAgentManager = sdk.createAgentManager;
                
                console.log("✅ D-ID SDK loaded successfully");
                
                // Initialize agent
                initializeAgent();
                
                async function initializeAgent() {
                    try {
                        console.log("🔑 Getting client key...");
                        const response = await fetch("' . esc_js($this->backend_url) . '/api/client-key", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ capabilities: ["streaming", "ws"] })
                        });
                        
                        const data = await response.json();
                        const clientKey = data.client_key || data.clientKey;
                        
                        if (!clientKey) {
                            throw new Error("No client key received");
                        }
                        
                        console.log("✅ Client key received");
                        
                        // Redirect all D-ID API calls to our backend
                        const backendUrl = "' . esc_js($this->backend_url) . '";
                        const originalFetch = window.fetch;
                        window.fetch = function(url, options = {}) {
                            let newUrl = url;
                            if (typeof url === "string" && url.includes("api.d-id.com")) {
                                newUrl = url.replace("https://api.d-id.com", backendUrl + "/api");
                                console.log("🔄 Redirecting API call:", url, "->", newUrl);
                                
                                // Add client key to authorization header
                                if (!options.headers) options.headers = {};
                                options.headers["Client-Key"] = clientKey;
                                options.headers["Authorization"] = "Basic " + btoa(clientKey + ":");
                            }
                            return originalFetch.call(this, newUrl, options);
                        };
                        
                        // Redirect WebSocket connections
                        const originalWebSocket = window.WebSocket;
                        window.WebSocket = function(url, protocols) {
                            if (url.includes("notifications.d-id.com")) {
                                const newUrl = url.replace("wss://notifications.d-id.com", backendUrl.replace("https://", "wss://") + "/api/notifications");
                                console.log("🔄 Redirecting WebSocket:", url, "->", newUrl);
                                return new originalWebSocket(newUrl, protocols);
                            }
                            return new originalWebSocket(url, protocols);
                        };
                        
                        // Create agent manager using official D-ID pattern
                        const agentManager = await window.createAgentManager("v2_agt_aKkqeO6X", {
                            auth: { type: "key", clientKey: clientKey },
                            callbacks: {
                                onSrcObjectReady: (stream) => {
                                    console.log("📹 Video stream ready");
                                    const video = document.getElementById("did-agent-container").querySelector("video");
                                    if (video) {
                                        video.srcObject = stream;
                                    } else {
                                        // Create video element
                                        const videoElement = document.createElement("video");
                                        videoElement.srcObject = stream;
                                        videoElement.autoplay = true;
                                        videoElement.playsInline = true;
                                        videoElement.muted = true;
                                        videoElement.style.width = "100%";
                                        videoElement.style.height = "100%";
                                        videoElement.style.objectFit = "cover";
                                        document.getElementById("did-agent-container").appendChild(videoElement);
                                    }
                                    document.getElementById("loading").style.display = "none";
                                },
                                onVideoStateChange: (state) => {
                                    console.log("📹 Video state:", state);
                                },
                                onConnectionStateChange: (state) => {
                                    console.log("🔗 Connection state:", state);
                                    if (state === "connected") {
                                        console.log("✅ Agent connected!");
                                        agentManager.chat("Hello, how can I help you today?");
                                    }
                                },
                                onNewMessage: (messages, type) => {
                                    console.log("💬 New message:", messages, type);
                                },
                                onError: (error, errorData) => {
                                    console.error("❌ Agent error:", error, errorData);
                                }
                            }
                        });
                        
                        console.log("✅ Agent manager created successfully");
                        
                    } catch (error) {
                        console.error("❌ Failed to initialize agent:", error);
                    }
                }
            `;
            document.head.appendChild(script);
        ');
    }
    
    public function render_agent_shortcode($atts) {
        ob_start();
        ?>
        <div id="did-agent-container" style="width: 800px; height: 600px; background: #000; border-radius: 16px; margin: 20px auto; position: relative;">
            <div id="loading" style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 18px;">
                Loading D-ID Agent...
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
    
    public function admin_init() {
        register_setting('did_agent_settings', 'did_agent_backend_url');
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('did_agent_backend_url', sanitize_text_field($_POST['backend_url']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $backend_url = get_option('did_agent_backend_url', 'https://d-id-agent-1sdx.onrender.com');
        ?>
        <div class="wrap">
            <h1>D-ID Agent Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Backend URL</th>
                        <td>
                            <input type="url" name="backend_url" value="<?php echo esc_attr($backend_url); ?>" class="regular-text" required />
                            <p class="description">Enter your Render backend URL (e.g., https://your-app.onrender.com)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Usage</h2>
            <p>Use the shortcode <code>[did_agent_simple]</code> to display the D-ID agent on any page or post.</p>
            
            <h2>Current Configuration</h2>
            <p><strong>Backend URL:</strong> <?php echo esc_html($backend_url); ?></p>
            <p><strong>Agent ID:</strong> v2_agt_aKkqeO6X</p>
        </div>
        <?php
    }
}

new DIDAgentSimpleWorkingFixed();
