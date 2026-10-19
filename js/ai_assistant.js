/**
 * ai_assistant.js - Interactive Floating AI Assistant & RAG Query Drawer
 * Powered by Python FastAPI, PyMuPDF, Sentence Transformers, FAISS, and Tool-Calling Agent
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inject AI Assistant Floating Trigger Button
    const triggerBtn = document.createElement('button');
    triggerBtn.id = 'ai-assistant-trigger';
    triggerBtn.className = 'btn btn-primary-custom shadow-lg';
    triggerBtn.style.cssText = `
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 9999;
        border-radius: 50px;
        padding: 12px 22px;
        font-weight: 600;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    `;
    triggerBtn.innerHTML = `
        <i class="fa-solid fa-wand-magic-sparkles fa-lg text-warning"></i>
        <span>AI Outpass Assistant</span>
    `;
    document.body.appendChild(triggerBtn);

    // 2. Inject Modal / Drawer HTML
    const drawerHtml = `
        <div class="modal fade" id="aiAssistantModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content glass-card border-0" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border-radius: 20px;">
                    <div class="modal-header border-secondary border-opacity-25 pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-circle" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">
                                <i class="fa-solid fa-robot fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-light mb-0">Hostel Outpass AI Assistant</h5>
                                <small class="text-secondary">Powered by Python FastAPI, FAISS Vector Search & Tool-Calling Agent</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4" style="min-height: 380px;">
                        <!-- Quick Prompt Pills -->
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill ai-quick-pill" data-prompt="What is the status of student 23ADR045?">
                                <i class="fa-solid fa-user-graduate me-1"></i>Check Student 23ADR045
                            </button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill ai-quick-pill" data-prompt="Show current hostel occupancy and capacity">
                                <i class="fa-solid fa-hotel me-1"></i>Occupancy Status
                            </button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill ai-quick-pill" data-prompt="Who is currently outside on an outing?">
                                <i class="fa-solid fa-person-walking-arrow-right me-1"></i>Students Outside
                            </button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill ai-quick-pill" data-prompt="Are there any pending home pass requests?">
                                <i class="fa-solid fa-house-chimney-user me-1"></i>Pending Passes
                            </button>
                        </div>

                        <!-- Chat Messages Container -->
                        <div id="ai-chat-messages" class="d-flex flex-column gap-3 mb-3 p-3 rounded-3" style="background: rgba(0, 0, 0, 0.3); max-height: 380px; overflow-y: auto;">
                            <div class="d-flex align-items-start gap-2 text-light">
                                <div class="badge bg-primary p-2 rounded-circle"><i class="fa-solid fa-robot"></i></div>
                                <div class="p-3 rounded-3" style="background: rgba(30, 41, 59, 0.8); max-width: 85%;">
                                    Hello! I am your <strong>Hostel Outpass AI Assistant</strong>. Ask me about student presence, hostel occupancy, pending approvals, or hostel policies and rulebooks indexed with PyMuPDF & FAISS.
                                </div>
                            </div>
                        </div>

                        <!-- Tool Call Indicator (Hidden by default) -->
                        <div id="ai-tool-indicator" class="d-none alert alert-info py-2 px-3 mb-2" style="font-size: 0.85rem; background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); color: #93c5fd;">
                            <i class="fa-solid fa-gear fa-spin me-2"></i><span id="ai-tool-name">Executing Python agent tool...</span>
                        </div>
                    </div>

                    <div class="modal-footer border-secondary border-opacity-25 p-3">
                        <form id="ai-chat-form" class="w-100 d-flex gap-2">
                            <input type="text" id="ai-chat-input" class="form-control" placeholder="Type a question (e.g. 'Is student 23ADR045 inside?')..." autocomplete="off" required>
                            <button type="submit" id="ai-send-btn" class="btn btn-primary-custom px-4">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', drawerHtml);

    const modalEl = document.getElementById('aiAssistantModal');
    const bsModal = new bootstrap.Modal(modalEl);
    triggerBtn.addEventListener('click', () => bsModal.show());

    const chatMessages = document.getElementById('ai-chat-messages');
    const chatInput = document.getElementById('ai-chat-input');
    const chatForm = document.getElementById('ai-chat-form');
    const toolIndicator = document.getElementById('ai-tool-indicator');
    const toolName = document.getElementById('ai-tool-name');

    // Quick pills click handler
    document.querySelectorAll('.ai-quick-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            chatInput.value = btn.getAttribute('data-prompt');
            chatForm.dispatchEvent(new Event('submit'));
        });
    });

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const query = chatInput.value.trim();
        if (!query) return;

        // Append User Message
        const userMsgDiv = document.createElement('div');
        userMsgDiv.className = 'd-flex align-items-start gap-2 justify-content-end text-light';
        userMsgDiv.innerHTML = `
            <div class="p-3 rounded-3 text-end" style="background: rgba(99, 102, 241, 0.4); max-width: 85%;">
                ${escapeHtml(query)}
            </div>
            <div class="badge bg-indigo-500 p-2 rounded-circle" style="background:#6366f1;"><i class="fa-solid fa-user"></i></div>
        `;
        chatMessages.appendChild(userMsgDiv);
        chatInput.value = '';
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Show tool calling indicator
        toolIndicator.classList.remove('d-none');
        toolName.innerText = "Analyzing intent & selecting Python tools...";

        try {
            const resp = await fetch('/api/agent/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: query, user_type: 'Admin' })
            });

            const data = await resp.json();
            toolIndicator.classList.add('d-none');

            // Format tools called badge
            let toolsHtml = '';
            if (data.tools_called && data.tools_called.length > 0) {
                toolsHtml = `<div class="mt-2 text-secondary" style="font-size:0.75rem;"><i class="fa-solid fa-wrench me-1"></i>Executed: <code>${data.tools_called.join(', ')}</code></div>`;
            }

            // Append AI Response
            const aiMsgDiv = document.createElement('div');
            aiMsgDiv.className = 'd-flex align-items-start gap-2 text-light';
            aiMsgDiv.innerHTML = `
                <div class="badge bg-primary p-2 rounded-circle"><i class="fa-solid fa-robot"></i></div>
                <div class="p-3 rounded-3" style="background: rgba(30, 41, 59, 0.8); max-width: 85%;">
                    ${formatMarkdown(data.response || "No response received.")}
                    ${toolsHtml}
                </div>
            `;
            chatMessages.appendChild(aiMsgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

        } catch (err) {
            toolIndicator.classList.add('d-none');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger py-2 px-3';
            errorDiv.innerText = "Error connecting to AI Agent service.";
            chatMessages.appendChild(errorDiv);
        }
    });

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function formatMarkdown(text) {
        return text
            .replace(/### (.*?)\n/g, '<h6 class="fw-bold text-light mt-1 mb-2">$1</h6>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code class="bg-dark px-1 py-0.5 rounded text-info">$1</code>')
            .replace(/\n- (.*?)/g, '<br>&bull; $1')
            .replace(/\n\n/g, '<br><br>');
    }
});
