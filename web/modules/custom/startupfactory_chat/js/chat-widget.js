/**
 * @file
 * Startup Factory Chat Widget — self-contained chat UI.
 *
 * Reads config from window.StartupFactoryChatConfig (injected by controller).
 * Sends messages to the Drupal proxy at {api_endpoint} which forwards to ragchat.
 *
 * Usage: <script src="/embed/startupfactory-chat.js?project_id=42"></script>
 */
(function () {
  'use strict';

  var NL = '\n';

  function createWidget(config) {
    var apiBase = config.api_endpoint || '/api/chat';
    var project_id = config.project_id;
    var token = config.token || '';
    var greeting = config.greeting || 'Hi! How can I help you?';
    var placeholder = config.placeholder || 'Type a message...';
    var theme = config.theme || 'light';
    var height = config.height || '440px';
    var conversation_id = null;

    var isDark = theme === 'dark';
    var bgPanel = isDark ? '#1e1e2e' : '#ffffff';
    var bgUser = isDark ? '#4f46e5' : '#4f46e5';
    var bgBot = isDark ? '#2a2a3e' : '#f0f0f5';
    var textColor = isDark ? '#e0e0e0' : '#1a1a2e';
    var botTextColor = isDark ? '#e0e0e0' : '#333';
    var inputBg = isDark ? '#2a2a3e' : '#f5f5f5';
    var borderColor = isDark ? '#3a3a4e' : '#e0e0e0';
    var headerBg = isDark ? '#2a2a3e' : '#4f46e5';

    // ── Container ──
    var container = document.createElement('div');
    container.id = 'sf-chat-widget';
    container.style.cssText = [
      'position:fixed', 'bottom:80px', 'right:20px', 'width:380px',
      'height:' + height, 'z-index:9999', 'border-radius:14px',
      'box-shadow:0 8px 32px rgba(0,0,0,0.18)', 'overflow:hidden',
      'display:none', 'flex-direction:column',
      'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
      'font-size:14px', 'line-height:1.5',
      'background:' + bgPanel, 'border:1px solid ' + borderColor,
    ].join(';');

    // ── Header ──
    var header = document.createElement('div');
    header.style.cssText = [
      'background:' + headerBg, 'color:#fff', 'padding:14px 16px',
      'display:flex', 'align-items:center', 'justify-content:space-between',
      'font-weight:600', 'font-size:15px', 'flex-shrink:0',
    ].join(';');
    var headerTitle = document.createElement('span');
    headerTitle.textContent = 'Chat Assistant';
    var headerClose = document.createElement('button');
    headerClose.textContent = '\u00d7';
    headerClose.style.cssText = [
      'background:none', 'border:none', 'color:#fff', 'font-size:22px',
      'cursor:pointer', 'padding:0 4px', 'line-height:1',
    ].join(';');
    header.appendChild(headerTitle);
    header.appendChild(headerClose);
    container.appendChild(header);

    // ── Messages area ──
    var messages = document.createElement('div');
    messages.style.cssText = [
      'flex:1', 'overflow-y:auto', 'padding:16px', 'display:flex',
      'flex-direction:column', 'gap:10px',
    ].join(';');
    container.appendChild(messages);

    // ── Input area ──
    var inputWrap = document.createElement('div');
    inputWrap.style.cssText = [
      'display:flex', 'padding:10px 12px', 'gap:8px',
      'border-top:1px solid ' + borderColor, 'background:' + bgPanel,
      'flex-shrink:0',
    ].join(';');

    var input = document.createElement('input');
    input.type = 'text';
    input.placeholder = placeholder;
    input.style.cssText = [
      'flex:1', 'padding:10px 14px', 'border:1px solid ' + borderColor,
      'border-radius:20px', 'outline:none', 'font-size:14px',
      'background:' + inputBg, 'color:' + textColor,
      'font-family:inherit',
    ].join(';');

    var sendBtn = document.createElement('button');
    sendBtn.textContent = '\u2191';
    sendBtn.style.cssText = [
      'width:38px', 'height:38px', 'border-radius:50%', 'border:none',
      'background:#4f46e5', 'color:#fff', 'font-size:18px',
      'cursor:pointer', 'display:flex', 'align-items:center',
      'justify-content:center', 'flex-shrink:0', 'transition:background 0.2s',
    ].join(';');

    inputWrap.appendChild(input);
    inputWrap.appendChild(sendBtn);
    container.appendChild(inputWrap);

    document.body.appendChild(container);

    // ── Toggle button ──
    var toggle = document.createElement('button');
    toggle.className = 'sf-chat-toggle';
    toggle.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
    toggle.style.cssText = [
      'position:fixed', 'bottom:20px', 'right:20px', 'width:56px',
      'height:56px', 'border-radius:50%', 'background:#4f46e5',
      'color:white', 'border:none', 'cursor:pointer',
      'box-shadow:0 4px 16px rgba(79,70,229,0.4)',
      'display:flex', 'align-items:center', 'justify-content:center',
      'z-index:10000', 'transition:transform 0.2s,box-shadow 0.2s',
    ].join(';');
    document.body.appendChild(toggle);

    // ── Helpers ──
    function scrollToBottom() {
      messages.scrollTop = messages.scrollHeight;
    }

    function addMessage(text, sender) {
      var wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;flex-direction:column;' + (sender === 'user' ? 'align-items:flex-end;' : 'align-items:flex-end;');

      var bubble = document.createElement('div');
      var isUser = sender === 'user';
      bubble.style.cssText = [
        'max-width:85%', 'padding:10px 14px', 'border-radius:14px',
        'word-wrap:break-word', 'white-space:pre-wrap',
        isUser
          ? 'background:' + bgUser + ';color:#fff;border-bottom-right-radius:4px;'
          : 'background:' + bgBot + ';color:' + botTextColor + ';border-bottom-left-radius:4px;',
      ].join(';');

      // Simple markdown: **bold**, *italic*, `code`, ```code blocks```
      var html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/```([\s\S]*?)```/g, '<pre style="background:rgba(0,0,0,0.06);padding:8px;border-radius:6px;overflow-x:auto;font-size:13px;margin:4px 0">$1</pre>')
        .replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.06);padding:2px 5px;border-radius:3px;font-size:13px">$1</code>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
      bubble.innerHTML = html;

      wrap.appendChild(bubble);
      messages.appendChild(wrap);
      scrollToBottom();
    }

    function addTypingIndicator() {
      var wrap = document.createElement('div');
      wrap.id = 'sf-chat-typing';
      wrap.style.cssText = 'display:flex;align-items:flex-end;';

      var bubble = document.createElement('div');
      bubble.style.cssText = [
        'padding:10px 14px', 'border-radius:14px', 'background:' + bgBot,
        'border-bottom-left-radius:4px',
      ].join(';');
      bubble.innerHTML = '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#999;margin:0 2px;animation:sf-bounce 1.2s infinite"></span>'
        + '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#999;margin:0 2px;animation:sf-bounce 1.2s 0.2s infinite"></span>'
        + '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#999;margin:0 2px;animation:sf-bounce 1.2s 0.4s infinite"></span>';

      wrap.appendChild(bubble);
      messages.appendChild(wrap);
      scrollToBottom();
    }

    function removeTypingIndicator() {
      var el = document.getElementById('sf-chat-typing');
      if (el) el.remove();
    }

    // Inject keyframes for typing animation
    if (!document.getElementById('sf-chat-styles')) {
      var style = document.createElement('style');
      style.id = 'sf-chat-styles';
      style.textContent = '@keyframes sf-bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}';
      document.head.appendChild(style);
    }

    // ── Send message ──
    function sendMessage() {
      var text = input.value.trim();
      if (!text) return;

      input.value = '';
      addMessage(text, 'user');
      addTypingIndicator();
      sendBtn.disabled = true;
      input.disabled = true;

      var payload = {
        message: text,
        project_id: project_id,
      };
      if (conversation_id) {
        payload.conversation_id = conversation_id;
      }

      var xhr = new XMLHttpRequest();
      xhr.open('POST', apiBase, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      if (token) {
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
      }
      xhr.timeout = 60000;

      xhr.onload = function () {
        removeTypingIndicator();
        sendBtn.disabled = false;
        input.disabled = false;
        input.focus();

        try {
          var data = JSON.parse(xhr.responseText);
          if (data.success && data.response) {
            addMessage(data.response, 'bot');
            if (data.conversation_id) {
              conversation_id = data.conversation_id;
            }
          } else {
            addMessage('Sorry, something went wrong. Please try again.', 'bot');
          }
        } catch (e) {
          addMessage('Sorry, I could not understand the response.', 'bot');
        }
      };

      xhr.onerror = function () {
        removeTypingIndicator();
        sendBtn.disabled = false;
        input.disabled = false;
        addMessage('Network error. Please check your connection and try again.', 'bot');
      };

      xhr.ontimeout = function () {
        removeTypingIndicator();
        sendBtn.disabled = false;
        input.disabled = false;
        addMessage('Response timed out. Please try again.', 'bot');
      };

      xhr.send(JSON.stringify(payload));
    }

    // ── Event listeners ──
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    var isOpen = false;
    toggle.addEventListener('click', function () {
      isOpen = true;
      container.style.display = 'flex';
      toggle.style.display = 'none';
      input.focus();
      scrollToBottom();
    });

    headerClose.addEventListener('click', function () {
      isOpen = false;
      container.style.display = 'none';
      toggle.style.display = 'flex';
    });

    toggle.addEventListener('mouseenter', function () {
      toggle.style.transform = 'scale(1.1)';
      toggle.style.boxShadow = '0 6px 20px rgba(79,70,229,0.5)';
    });
    toggle.addEventListener('mouseleave', function () {
      toggle.style.transform = 'scale(1)';
      toggle.style.boxShadow = '0 4px 16px rgba(79,70,229,0.4)';
    });

    // ── Show greeting ──
    if (greeting) {
      addMessage(greeting, 'bot');
    }
  }

  function init() {
    var config = window.StartupFactoryChatConfig;
    if (!config || config.error) {
      return;
    }
    window.StartupFactoryChat = config;
    createWidget(config);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
