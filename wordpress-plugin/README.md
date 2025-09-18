# D-ID Agent WordPress Plugin

A WordPress plugin that integrates D-ID AI Agents into your website with secure backend API integration.

## Features

- 🤖 **AI Agent Integration**: Embed D-ID agents directly in WordPress pages
- 🔐 **Secure Backend**: API keys stored securely on your backend server
- 💬 **Chat & Speak Modes**: Interactive conversation with AI agents
- 🎥 **Video Streaming**: Real-time video and audio streaming
- 📱 **Responsive Design**: Works on desktop and mobile devices
- 🎨 **Customizable Themes**: Light and dark theme support
- ⚡ **Easy Setup**: Simple shortcode integration

## Installation

### 1. Upload Plugin Files

1. Download or clone this repository
2. Upload the `wordpress-plugin` folder to your WordPress `/wp-content/plugins/` directory
3. Rename the folder to `did-agent-integration`

### 2. Activate Plugin

1. Go to your WordPress admin dashboard
2. Navigate to **Plugins** → **Installed Plugins**
3. Find "D-ID Agent Integration" and click **Activate**

### 3. Configure Backend URL

1. Go to **Settings** → **D-ID Agent**
2. Enter your Render backend URL (e.g., `https://your-backend.onrender.com`)
3. Click **Save Changes**

## Usage

### Basic Usage

Add the shortcode to any page or post:

```
[did_agent agent_id="your_agent_id"]
```

### Advanced Usage

Customize the agent with additional options:

```
[did_agent agent_id="your_agent_id" width="100%" height="600px" theme="dark"]
```

### Parameters

- `agent_id` (required): Your D-ID agent ID from D-ID Studio
- `width` (optional): Container width (default: "100%")
- `height` (optional): Container height (default: "500px")
- `theme` (optional): Theme style - "default" or "dark" (default: "default")

## Getting Your Agent ID

1. Go to [D-ID Studio](https://studio.d-id.com)
2. Create or select your agent
3. Click the **Embed** button (</>)
4. Copy the `data-agent-id` value
5. Use this ID in your shortcode

## Backend Setup

This plugin requires a Node.js backend server. See the main repository for backend setup instructions.

The backend should be deployed on Render.com and provide the following endpoints:
- `POST /api/client-key` - Get client key for frontend
- `GET /api/agents/:agentId` - Get agent details
- `POST /api/agents/:agentId/streams` - Create streams

## Troubleshooting

### Agent Not Loading

1. Check that your backend URL is correctly configured
2. Verify your agent ID is correct
3. Check browser console for error messages
4. Ensure your backend is running and accessible

### CORS Errors

Make sure your backend is configured to allow your WordPress domain in the CORS settings.

### Video Not Playing

1. Check that your browser supports WebRTC
2. Ensure HTTPS is enabled (required for WebRTC)
3. Check browser console for media errors

## Support

For issues and questions:
1. Check the browser console for error messages
2. Verify your backend is running correctly
3. Test with a simple agent first
4. Check D-ID Studio for agent status

## License

MIT License - see main repository for details.
