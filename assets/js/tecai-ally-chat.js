(function () {
  'use strict';

  function mergeChatOptions(defaults, overrides) {
    var base = defaults || {};
    var over = overrides || {};
    var out = {};
    Object.keys(base).forEach(function (k) {
      out[k] = base[k];
    });
    Object.keys(over).forEach(function (k) {
      out[k] = over[k];
    });
    return out;
  }

  function createEl(tag, className, attrs) {
    var el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        if (key === 'text') {
          el.textContent = attrs[key];
        } else if (key === 'html') {
          el.innerHTML = attrs[key];
        } else {
          el.setAttribute(key, attrs[key]);
        }
      });
    }
    return el;
  }

  function buildTranscript(messages) {
    return messages
      .map(function (m) {
        return (m.role === 'user' ? 'Customer: ' : 'TecAI Ally: ') + m.text;
      })
      .join('\n');
  }

  function initChat() {
    if (typeof window === 'undefined') {
      return;
    }

    if (typeof TecAIAllyConfig === 'undefined') {
      return;
    }

    var cfg = TecAIAllyConfig;

    var root = document.getElementById('tecai-ally-chat-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'tecai-ally-chat-root';
      document.body.appendChild(root);
    }

    root.classList.add('tecai-ally-root');
    root.style.setProperty('--tecai-ally-accent', cfg.accentColor || '#1d4ed8');
    if (cfg.position === 'left') {
      root.classList.add('tecai-ally-left');
    } else {
      root.classList.add('tecai-ally-right');
    }

    var state = {
      isOpen: false,
      conversationId: null,
      messages: [],
      sending: false,
    };

    var container = createEl('div', 'tecai-ally-container');
    var bubble = createEl('button', 'tecai-ally-bubble', {
      type: 'button',
      'aria-label': cfg.bubbleLabel || 'Chat',
    });
    var bubbleLabel = createEl('span', 'tecai-ally-bubble-label', {
      text: cfg.bubbleLabel || '',
    });
    bubble.appendChild(createEl('span', 'tecai-ally-bubble-icon', { text: '💬' }));
    bubble.appendChild(bubbleLabel);

    var panel = createEl('div', 'tecai-ally-panel tecai-ally-panel-closed');

    var header = createEl('div', 'tecai-ally-header');
    var title = createEl('div', 'tecai-ally-title', { text: cfg.labels.header });
    var closeBtn = createEl('button', 'tecai-ally-close', {
      type: 'button',
      'aria-label': 'Close',
    });
    closeBtn.textContent = '×';
    header.appendChild(title);
    header.appendChild(closeBtn);

    var intro = createEl('div', 'tecai-ally-intro', { text: cfg.labels.intro });

    var messagesWrap = createEl('div', 'tecai-ally-messages');

    var privacyWrap = createEl('div', 'tecai-ally-privacy');
    var consentCheckbox = null;
    if (cfg.canStore) {
      consentCheckbox = createEl('input', 'tecai-ally-consent-checkbox');
      consentCheckbox.type = 'checkbox';
      consentCheckbox.id = 'tecai-ally-consent';
      var consentLabel = createEl('label', 'tecai-ally-consent-label', {
        text: cfg.labels.consentLabel,
      });
      consentLabel.setAttribute('for', consentCheckbox.id);
      privacyWrap.appendChild(consentCheckbox);
      privacyWrap.appendChild(consentLabel);
    }

    var emailInput = createEl('input', 'tecai-ally-email-input');
    emailInput.type = 'email';
    emailInput.placeholder = cfg.labels.emailPlaceholder;

    var footer = createEl('div', 'tecai-ally-footer');
    var input = createEl('textarea', 'tecai-ally-input');
    input.placeholder = cfg.labels.inputPlaceholder;

    var actionsRow = createEl('div', 'tecai-ally-actions');
    var sendBtn = createEl('button', 'tecai-ally-send', {
      type: 'button',
      text: cfg.labels.send,
    });

    var secondaryRow = createEl('div', 'tecai-ally-secondary-actions');
    var escBtn = createEl('button', 'tecai-ally-escalate', {
      type: 'button',
      text: cfg.labels.escButton,
    });
    var deleteBtn = createEl('button', 'tecai-ally-delete', {
      type: 'button',
      text: cfg.labels.deleteButton,
    });

    actionsRow.appendChild(sendBtn);
    secondaryRow.appendChild(escBtn);
    secondaryRow.appendChild(deleteBtn);

    footer.appendChild(input);
    footer.appendChild(actionsRow);

    panel.appendChild(header);
    panel.appendChild(intro);
    panel.appendChild(messagesWrap);
    panel.appendChild(privacyWrap);
    panel.appendChild(emailInput);
    panel.appendChild(footer);
    panel.appendChild(secondaryRow);

    container.appendChild(panel);
    container.appendChild(bubble);

    root.appendChild(container);

    function togglePanel(forceOpen) {
      var open = typeof forceOpen === 'boolean' ? forceOpen : !state.isOpen;
      state.isOpen = open;
      if (open) {
        panel.classList.remove('tecai-ally-panel-closed');
        panel.classList.add('tecai-ally-panel-open');
        input.focus();
      } else {
        panel.classList.add('tecai-ally-panel-closed');
        panel.classList.remove('tecai-ally-panel-open');
      }
    }

    function appendMessage(role, text) {
      var item = createEl('div', 'tecai-ally-message tecai-ally-' + role);
      var bubbleEl = createEl('div', 'tecai-ally-message-bubble');
      bubbleEl.textContent = text;
      item.appendChild(bubbleEl);
      messagesWrap.appendChild(item);
      messagesWrap.scrollTop = messagesWrap.scrollHeight;
      state.messages.push({ role: role, text: text });
    }

    function setSending(isSending) {
      state.sending = isSending;
      if (isSending) {
        sendBtn.setAttribute('disabled', 'disabled');
      } else {
        sendBtn.removeAttribute('disabled');
      }
    }

    function handleError(message) {
      appendMessage('bot', message || cfg.labels.genericError);
    }

    function sendMessage() {
      if (state.sending) {
        return;
      }

      var value = input.value.trim();
      if (!value) {
        return;
      }

      setSending(true);
      appendMessage('user', value);
      input.value = '';

      var payload = {
        tecai_ally_nonce: cfg.chatNonce,
        message: value,
        conversation_id: state.conversationId,
        store_consent: consentCheckbox ? !!consentCheckbox.checked : false,
        email: emailInput.value || '',
      };

      // Debug: Log nonce to console
      if (typeof console !== 'undefined') {
        console.log('TecAI Ally: Sending request with nonce:', cfg.chatNonce ? cfg.chatNonce.substring(0, 10) + '...' : 'MISSING');
        console.log('TecAI Ally: REST URL:', cfg.restUrl + 'chat');
      }

      fetch(cfg.restUrl + 'chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': (cfg.restNonce || cfg.chatNonce) || '',
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      })
        .then(function (res) {
          if (res.status === 429) {
            throw { rateLimited: true };
          }
          
          if (!res.ok) {
            // Read response once and handle both JSON and text
            return res.text().then(function (text) {
              var errorMsg = cfg.labels.genericError;
              var errorData = null;
              
              // Try to parse as JSON
              try {
                errorData = JSON.parse(text);
                if (errorData && errorData.message) {
                  errorMsg = errorData.message;
                } else if (errorData && errorData.code === 'tecai_ally_forbidden') {
                  errorMsg = 'Security check failed. Please refresh the page and try again.';
                } else if (errorData && errorData.data && errorData.data.status) {
                  // WordPress REST API error format
                  if (errorData.data.status === 403) {
                    errorMsg = 'Security check failed. Please refresh the page and try again.';
                  } else if (errorData.data.status === 400) {
                    errorMsg = errorData.message || 'Invalid request. Please try again.';
                  }
                }
              } catch (e) {
                // Not JSON, use text if it's short enough
                if (text && text.length < 200) {
                  errorMsg = text;
                } else {
                  errorMsg = cfg.labels.genericError + ' (HTTP ' + res.status + ')';
                }
              }
              
              console.error('TecAI Ally API Error:', res.status, text);
              throw { message: errorMsg, status: res.status, errorData: errorData };
            });
          }
          
          // Success response - parse as JSON
          return res.json();
        })
        .then(function (data) {
          if (!data) {
            handleError();
            return;
          }

          if (data.needs_api_key) {
            handleError(data.bot_message || cfg.labels.genericError);
            sendBtn.setAttribute('disabled', 'disabled');
            return;
          }

          if (data.bot_message) {
            appendMessage('bot', data.bot_message);
          }

          if (data.conversation_id) {
            state.conversationId = data.conversation_id;
          }

          if (data.suggest_human) {
            escBtn.classList.add('tecai-ally-escalate-highlight');
          }
        })
        .catch(function (err) {
          console.error('TecAI Ally Chat Error:', err);
          if (err && err.rateLimited) {
            handleError(cfg.labels.rateLimited);
          } else if (err && err.message) {
            handleError(err.message);
          } else if (err && err.name === 'TypeError' && err.message && err.message.includes('fetch')) {
            handleError('Network error. Please check your internet connection and try again.');
          } else {
            handleError();
          }
        })
        .finally(function () {
          setSending(false);
        });
    }

    function deleteConversation() {
      if (!state.conversationId) {
        state.messages = [];
        messagesWrap.innerHTML = '';
        return;
      }

      var url = cfg.restUrl + 'conversation/' + encodeURIComponent(state.conversationId) + '?tecai_ally_nonce=' + encodeURIComponent(cfg.chatNonce);

      fetch(url, {
        method: 'DELETE',
      })
        .then(function () {
          state.messages = [];
          messagesWrap.innerHTML = '';
          state.conversationId = null;
        })
        .catch(function () {
          // Silently ignore.
        });
    }

    function escalateToHuman() {
      if (!state.messages.length) {
        return;
      }

      var transcript = buildTranscript(state.messages);

      var payload = {
        tecai_ally_nonce: cfg.chatNonce,
        conversation_id: state.conversationId || '',
        transcript: transcript,
        email: emailInput.value || '',
        store_consent: consentCheckbox ? !!consentCheckbox.checked : false,
      };

      fetch(cfg.restUrl + 'escalate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('HTTP ' + res.status);
          }
          return res.json();
        })
        .then(function () {
          appendMessage('bot', 'Okay, I have transferred this conversation to a human. You will be contacted soon.');
          escBtn.disabled = true;
        })
        .catch(function () {
          handleError();
        });
    }

    bubble.addEventListener('click', function () {
      togglePanel();
    });

    closeBtn.addEventListener('click', function () {
      togglePanel(false);
    });

    sendBtn.addEventListener('click', function () {
      sendMessage();
    });

    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) {
        ev.preventDefault();
        sendMessage();
      }
    });

    deleteBtn.addEventListener('click', function () {
      deleteConversation();
    });

    escBtn.addEventListener('click', function () {
      escalateToHuman();
    });
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initChat);
    } else {
      initChat();
    }
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { mergeChatOptions: mergeChatOptions };
  } else if (typeof window !== 'undefined') {
    window.mergeChatOptions = mergeChatOptions;
  }
})();
