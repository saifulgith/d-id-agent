<?php
/**
 * Plugin Name: D-ID Agent Simple Working
 * Description: Simple working version based on official D-ID SDK
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentSimpleWorking {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_simple', array($this, 'render_agent_shortcode'));
    }
    
    public function init() {
        $this->backend_url = get_option('did_agent_backend_url', 'https://d-id-agent-1sdx.onrender.com');
    }
    
    public function enqueue_scripts() {
        // Load D-ID SDK directly
        wp_enqueue_script('did-sdk', 'https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js', array(), '1.0.0', true);
        
        // Configure and initialize
        wp_add_inline_script('did-sdk', '
            console.log("🚀 D-ID Agent Simple Working - Loading SDK");
            
            // Wait for SDK to load
            function waitForSDK() {
                if (window.DID && window.DID.createAgentManager) {
                    console.log("✅ D-ID SDK loaded successfully");
                    initializeAgent();
                } else {
                    console.log("⏳ Waiting for D-ID SDK...");
                    setTimeout(waitForSDK, 100);
                }
            }
            
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
                    
                    // Create agent manager
                    const agentManager = await window.DID.createAgentManager({
                        agentId: "v2_agt_aKkqeO6X",
                        container: document.getElementById("did-agent-container"),
                        auth: {
                            type: "key",
                            clientKey: clientKey
                        },
                        onSrcObjectReady: (stream) => {
                            console.log("📹 Video stream ready");
                            document.getElementById("loading").style.display = "none";
                        },
                        onConnectionStateChange: (state) => {
                            console.log("🔗 Connection state:", state);
                            if (state === "connected") {
                                console.log("✅ Agent connected!");
                                agentManager.chat("Hello, how can I help you today?");
                            }
                        },
                        onError: (error) => {
                            console.error("❌ Agent error:", error);
                        }
                    });
                    
                    console.log("✅ Agent manager created successfully");
                    
                } catch (error) {
                    console.error("❌ Failed to initialize agent:", error);
                }
            }
            
            // Start when DOM is ready
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", waitForSDK);
            } else {
                waitForSDK();
            }
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
}

new DIDAgentSimpleWorking();
