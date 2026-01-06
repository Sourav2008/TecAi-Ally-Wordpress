# 🤖 TecSupport Genius

🚀 Why TecSupport Genius?

**TecSupport Genius renamed from TecAi Ally** by **TecDevs** is not just another chatbot plugin. It is a full-scale customer success suite that lives inside your WordPress database. While other plugins charge monthly SaaS fees to store your data, TecSupport Genius gives you full ownership of your customer interactions and tickets.

### 🌟 Industry-Leading Features

* **👻 Ghost Mode (Live Typing Preview):** Boost response speed by 300%. Agents can see what the customer is typing *before* they hit enter.
* **🎫 "Jira-Lite" Native Ticketing:** A professional-grade ticketing system built directly into your WP Admin. No external subscriptions required.
* **🧠 Site-Specific Training (RAG):** One-click "Knowledge Base" indexing. The AI reads your posts, pages, and products to provide hyper-accurate answers.
* **🛒 WooCommerce Action Agent:** The AI doesn't just talk; it can look up order statuses and suggest products directly in the chat.
* **🤖 Multi-LLM Support:** Choose your brain. Connect to **Google Gemini (Free tier available)**, OpenAI's GPT-4, or Claude with ease.
* **📊 Agent Assist:** AI-generated suggested replies for human agents to ensure every response is professional and fast.

---

## 📂 File Structure

```text
tecsupport-genius/
├── includes/
│   ├── admin/          # Kanban Dashboard & Settings
│   ├── ai/             # Gemini & OpenAI Service Logic
│   ├── tickets/        # Custom Post Type & Ticketing Logic
│   ├── api/            # REST API Endpoints & Ghost Mode
│   └── frontend/       # React-based Chat Widget
├── assets/             # CSS/JS for Admin and Frontend
└── tecsupport-genius.php # Plugin Entry Point
🛠️ Installation
Download: Clone this repository into your /wp-content/plugins/ directory.

Activate: Navigate to the WordPress Dashboard > Plugins and click Activate on TecSupport Genius.

Configure: Go to TecSupport Genius > Settings and enter your Gemini or OpenAI API Key.

Train: Go to the Training tab and click Index Site Content to let the AI learn your business.

🔒 Security & Privacy
GDPR Compliant: "Ghost Mode" is opt-in for users. Privacy toggles are built-in.

Data Sovereignty: All chat logs and tickets are stored in your local WordPress database.

Secure API: Nonce verification on every REST API request.

👨‍💻 Developer & Agency
Developed with ❤️ by TecDevs. Our mission is to empower WordPress users with Enterprise-level AI tools.

📄 License
This project is licensed under the GPLv2 License - see the LICENSE file for details.
