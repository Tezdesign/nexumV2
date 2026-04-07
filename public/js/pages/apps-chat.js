class ChatApp {

    constructor() {
        this.root = null
        this.messagesScrollWrapper = null
        this.messagesList = null
        this.messagesState = null
        this.conversationItems = []
        this.activeConversationItem = null
        this.activeConversationId = null
        this.filterButtons = []
        this.filterEmptyState = null
        this.filterLabel = null
        this.activeFilter = 'all'
        this.searchInput = null
        this.searchQuery = ''
        this.activeConversationAvatar = null
        this.activeConversationAvatarFallback = null
        this.activeConversationName = null
        this.activeConversationMeta = null
        this.currentUserName = 'You'
        this.currentUserAvatar = ''
        this.messagesSimplebar = null
        this.chatForm = null
        this.chatInput = null
        this.chatSendButton = null
        this.activeFetchController = null
        this.activeAttachmentControllers = new Map()
    }

    cacheElements = () => {
        this.root = document.querySelector('[data-apps-chat="chat-root"]')
        if (this.root) {
            this.currentUserName = this.root.dataset.currentUserName || this.currentUserName
            this.currentUserAvatar = this.root.dataset.currentUserAvatar || ''
        }

        this.messagesScrollWrapper = document.querySelector(
            '[data-apps-chat="messages-scroll-wrapper"]'
        )
        this.messagesList = document.querySelector('[data-apps-chat="messages-list"]')
        this.messagesState = document.querySelector('[data-apps-chat="messages-state"]')
        this.conversationItems = Array.from(
            document.querySelectorAll('[data-apps-chat="conversation-item"]')
        )
        this.filterButtons = Array.from(
            document.querySelectorAll('[data-chat-filter]')
        )
        const activeFilterButton = this.filterButtons.find(
            (button) => button.getAttribute('aria-pressed') === 'true'
        )
        if (activeFilterButton) {
            this.activeFilter = activeFilterButton.dataset.chatFilter || 'all'
        }
        this.filterEmptyState = document.querySelector('[data-apps-chat="filter-empty"]')
        this.filterLabel = document.querySelector('[data-apps-chat="filter-label"]')
        this.searchInput = document.querySelector('[data-apps-chat="search-input"]')
        this.activeConversationAvatar = document.querySelector('[data-apps-chat="active-conversation-avatar"]')
        this.activeConversationAvatarFallback = document.querySelector('[data-apps-chat="active-conversation-avatar-fallback"]')
        this.activeConversationName = document.querySelector('[data-apps-chat="active-conversation-name"]')
        this.activeConversationMeta = document.querySelector('[data-apps-chat="active-conversation-meta"]')
        this.chatForm = document.querySelector('#chat-form')
        if (this.chatForm) {
            this.chatInput = this.chatForm.querySelector('[data-apps-chat="chat-input"]')
            this.chatSendButton = this.chatForm.querySelector('[data-apps-chat="chat-send"]')
        }
        if (this.messagesScrollWrapper && window.SimpleBar)
            this.messagesSimplebar = new SimpleBar(this.messagesScrollWrapper)
    }

    clearMessages = () => {
        if (this.messagesList) {
            this.messagesList.innerHTML = ''
        }
    }

    setMessagesState = (text, visible = true) => {
        if (!this.messagesState) {
            return
        }

        this.messagesState.textContent = text
        this.messagesState.classList.toggle('d-none', !visible)
    }

    setComposerEnabled = (enabled) => {
        if (this.chatInput) {
            this.chatInput.disabled = !enabled
        }

        if (this.chatSendButton) {
            this.chatSendButton.disabled = !enabled
        }
    }

    applyConversationFilter = (filter) => {
        this.activeFilter = filter

        this.filterButtons.forEach((button) => {
            const isActive = button.dataset.chatFilter === filter
            button.classList.toggle('active', isActive)
            button.setAttribute('aria-pressed', String(isActive))

            const checkIcon = button.querySelector('[data-chat-filter-check]')
            if (checkIcon)
                checkIcon.classList.toggle('d-none', !isActive)

            if (isActive && this.filterLabel) {
                this.filterLabel.textContent = button.dataset.chatFilterLabel || 'All'
            }
        })

        this.applyConversationVisibility()
    }

    normalizeText = (value) => {
        return String(value || '').toLowerCase().trim()
    }

    getConversationDisplayName = (item) => {
        return item?.dataset.conversationName || 'Unknown conversation'
    }

    getConversationDisplayType = (item) => {
        const type = (item?.dataset.conversationType || '').toUpperCase()
        if (type === 'DM') {
            return 'Direct message'
        }

        if (type === 'GROUP') {
            return 'Group conversation'
        }

        return 'Conversation'
    }

    updateConversationHeader = (item) => {
        const conversationName = this.getConversationDisplayName(item)
        const conversationType = this.getConversationDisplayType(item)
        const avatarSrc = item?.dataset.conversationAvatar || ''

        if (this.activeConversationName) {
            this.activeConversationName.textContent = conversationName
        }

        if (this.activeConversationMeta) {
            this.activeConversationMeta.textContent = conversationType
        }

        if (this.activeConversationAvatar && this.activeConversationAvatarFallback) {
            if (avatarSrc) {
                this.activeConversationAvatar.src = avatarSrc
                this.activeConversationAvatar.classList.remove('d-none')
                this.activeConversationAvatarFallback.classList.add('d-none')
            } else {
                this.activeConversationAvatar.removeAttribute('src')
                this.activeConversationAvatar.classList.add('d-none')
                this.activeConversationAvatarFallback.classList.remove('d-none')
                this.activeConversationAvatarFallback.textContent = conversationName.slice(0, 1).toUpperCase() || '-'
            }
        }
    }

    getSearchValueByType = (item, rowType) => {
        if (rowType === 'DM') {
            return this.normalizeText(item.dataset.dmName)
        }

        if (rowType === 'GROUP') {
            return this.normalizeText(item.dataset.groupTitle)
        }

        return ''
    }

    applyConversationVisibility = () => {
        const selectedType = this.activeFilter === 'dm'
            ? 'DM'
            : this.activeFilter === 'group'
                ? 'GROUP'
                : null

        const query = this.normalizeText(this.searchQuery)
        let hasMatches = false

        this.conversationItems.forEach((item) => {
            const rowType = (item.dataset.conversationType || '').toUpperCase()
            const matchesFilter = selectedType === null || rowType === selectedType
            const searchTarget = this.getSearchValueByType(item, rowType)
            const matchesSearch = query.length === 0 || searchTarget.includes(query)
            const isVisible = matchesFilter && matchesSearch

            item.classList.toggle('d-none', !isVisible)
            if (isVisible) {
                hasMatches = true
            }
        })

        if (this.activeConversationItem && this.activeConversationItem.classList.contains('d-none')) {
            const firstVisibleConversation = this.getFirstVisibleConversation()
            if (firstVisibleConversation) {
                this.selectConversation(firstVisibleConversation)
            }
        }

        if (this.filterEmptyState) {
            this.filterEmptyState.classList.toggle('d-none', hasMatches)
        }
    }

    initFilters = () => {
        this.filterButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault()
                this.applyConversationFilter(button.dataset.chatFilter || 'all')
            })
        })

        if (this.conversationItems.length > 0) {
            this.applyConversationFilter(this.activeFilter)
        }
    }

    initSearch = () => {
        if (!this.searchInput) {
            return
        }

        this.searchInput.addEventListener('input', (event) => {
            this.searchQuery = event.target.value || ''
            this.applyConversationVisibility()
        })
    }

    getFirstVisibleConversation = () => {
        return this.conversationItems.find((item) => !item.classList.contains('d-none')) || null
    }

    buildMessagesEndpoint = (item, conversationId) => {
        const itemEndpoint = item?.dataset.messagesEndpoint || ''
        if (itemEndpoint) {
            return itemEndpoint
        }

        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/messages`
    }

    createAvatarElement = (avatarSrc, fallbackText) => {
        if (avatarSrc) {
            const image = document.createElement('img')
            image.src = avatarSrc
            image.className = 'avatar-sm rounded-circle'
            image.alt = fallbackText
            return image
        }

        const fallback = document.createElement('div')
        fallback.className = 'avatar-sm rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center fw-semibold'
        fallback.textContent = String(fallbackText || '-').slice(0, 1).toUpperCase()
        return fallback
    }

    getAttachmentKind = (attachment) => {
        const mimeType = String(attachment?.mimeType || '').toLowerCase()
        if (mimeType.startsWith('image/')) {
            return 'image'
        }

        if (mimeType.startsWith('video/')) {
            return 'video'
        }

        if (mimeType.startsWith('audio/')) {
            return 'audio'
        }

        return 'file'
    }

    createAttachmentNode = (attachment, index) => {
        const kind = this.getAttachmentKind(attachment)
        const url = attachment?.url || '#'
        const fileName = attachment?.fileName || `attachment-${index + 1}`

        const wrapper = document.createElement('div')
        wrapper.className = 'mt-2'

        if (kind === 'image') {
            const link = document.createElement('a')
            link.href = url
            link.target = '_blank'
            link.rel = 'noopener'

            const image = document.createElement('img')
            image.src = url
            image.alt = fileName
            image.className = 'img-fluid rounded-3 border'

            link.appendChild(image)
            wrapper.appendChild(link)
            return wrapper
        }

        if (kind === 'video') {
            const video = document.createElement('video')
            video.controls = true
            video.preload = 'metadata'
            video.src = url
            video.className = 'w-100 rounded-3 border bg-black'
            video.setAttribute('playsinline', 'playsinline')

            wrapper.appendChild(video)
            return wrapper
        }

        if (kind === 'audio') {
            const audio = document.createElement('audio')
            audio.controls = true
            audio.preload = 'metadata'
            audio.src = url
            audio.className = 'w-100'

            wrapper.appendChild(audio)
            return wrapper
        }

        const link = document.createElement('a')
        link.href = url
        link.target = '_blank'
        link.rel = 'noopener'
        link.className = 'd-inline-flex align-items-center gap-1 text-decoration-none'
        link.textContent = fileName

        wrapper.appendChild(link)
        return wrapper
    }

    buildAttachmentListEndpoint = (messageId) => {
        return `/apps-chat/messages/${encodeURIComponent(String(messageId))}/attachments`
    }

    loadMessageAttachments = async (messageId, attachmentContainer, bodyElement) => {
        if (!messageId || !attachmentContainer) {
            return
        }

        const existingController = this.activeAttachmentControllers.get(messageId)
        if (existingController) {
            existingController.abort()
        }

        const controller = new AbortController()
        this.activeAttachmentControllers.set(messageId, controller)

        try {
            const response = await fetch(this.buildAttachmentListEndpoint(messageId), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                signal: controller.signal,
            })

            if (!response.ok) {
                throw new Error(`Failed to load attachments (${response.status})`)
            }

            const payload = await response.json()
            if (!payload.success) {
                throw new Error(payload.error || 'Failed to load attachments')
            }

            const attachments = Array.isArray(payload.attachments) ? payload.attachments : []
            if (!attachments.length) {
                return
            }

            if (bodyElement) {
                bodyElement.classList.add('d-none')
            }

            attachmentContainer.innerHTML = ''
            attachments.forEach((attachment, index) => {
                attachmentContainer.appendChild(this.createAttachmentNode(attachment, index))
            })
            attachmentContainer.classList.remove('d-none')
        } catch (error) {
            if (error?.name === 'AbortError') {
                return
            }

            console.error('Attachment load failed:', error)
            if (bodyElement) {
                bodyElement.classList.remove('d-none')
            }
        } finally {
            if (this.activeAttachmentControllers.get(messageId) === controller) {
                this.activeAttachmentControllers.delete(messageId)
            }
        }
    }

    createMessageNode = (message, index) => {
        const isOwn = !!message.isOwn
        const senderName = message.senderName || 'Unknown User'
        const displayName = isOwn ? 'You.' : senderName
        const avatarLabel = isOwn ? this.currentUserName : senderName
        const avatarSrc = isOwn
            ? (message.senderAvatarSrc || this.currentUserAvatar || '')
            : (message.senderAvatarSrc || '')
        const timeLabel = message.timeLabel || '--'
        const body = message.body || ''
        const attachments = Array.isArray(message.attachments) ? message.attachments : []
        const isAttachmentMessage = String(message.kind || '').toUpperCase() === 'ATTACHMENT'
        const fallbackText = body.trim().length > 0 ? body : 'Attachment'
        const hasTextBody = body.trim().length > 0 || isAttachmentMessage

        const listItem = document.createElement('li')
        listItem.className = `chat-group${isOwn ? ' odd' : ''}`
        listItem.id = `message-${message.id || index}`

        const avatar = this.createAvatarElement(avatarSrc, avatarLabel)
        listItem.appendChild(avatar)

        const chatBody = document.createElement('div')
        chatBody.className = 'chat-body'

        const titleWrapper = document.createElement('div')
        const sender = document.createElement('h6')
        sender.className = 'd-inline-flex'
        sender.textContent = displayName
        const time = document.createElement('h6')
        time.className = 'd-inline-flex text-muted'
        time.textContent = timeLabel
        titleWrapper.appendChild(sender)
        titleWrapper.appendChild(time)

        const chatMessage = document.createElement('div')
        chatMessage.className = 'chat-message'
        const bodyElement = document.createElement('p')
        bodyElement.textContent = fallbackText

        if (hasTextBody) {
            chatMessage.appendChild(bodyElement)
        }

        const attachmentContainer = document.createElement('div')
        attachmentContainer.className = 'd-grid gap-2 d-none'

        if (isAttachmentMessage) {
            chatMessage.appendChild(attachmentContainer)
            queueMicrotask(() => {
                this.loadMessageAttachments(message.id, attachmentContainer, bodyElement)
            })
        } else if (attachments.length > 0) {
            attachmentContainer.classList.remove('d-none')
            attachments.forEach((attachment, attachmentIndex) => {
                attachmentContainer.appendChild(this.createAttachmentNode(attachment, attachmentIndex))
            })
            chatMessage.appendChild(attachmentContainer)
        }

        chatBody.appendChild(titleWrapper)
        chatBody.appendChild(chatMessage)
        listItem.appendChild(chatBody)

        return listItem
    }

    renderMessages = (messages) => {
        this.clearMessages()

        if (!messages.length) {
            this.setMessagesState('No messages in this conversation yet.', true)
            return
        }

        this.setMessagesState('', false)

        messages.forEach((message, index) => {
            const node = this.createMessageNode(message, index)
            this.messagesList?.appendChild(node)
        })

        this.scrollToBottom()
    }

    loadConversationMessages = async (conversationId, endpoint) => {
        if (!endpoint) {
            return
        }

        if (this.activeFetchController) {
            this.activeFetchController.abort()
        }

        const controller = new AbortController()
        this.activeFetchController = controller

        this.clearMessages()
        this.setMessagesState('Loading messages...', true)

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                signal: controller.signal,
            })

            if (!response.ok) {
                throw new Error(`Failed to load messages (${response.status})`)
            }

            const payload = await response.json()
            if (!payload.success) {
                throw new Error(payload.error || 'Failed to load messages')
            }

            this.renderMessages(Array.isArray(payload.messages) ? payload.messages : [])
        } catch (error) {
            if (error?.name === 'AbortError') {
                return
            }

            this.clearMessages()
            console.error('Conversation messages load failed:', error)
            this.setMessagesState('Could not load messages. Please try again.', true)
        } finally {
            if (this.activeFetchController === controller) {
                this.activeFetchController = null
            }
        }
    }

    selectConversation = (item) => {
        if (!item) {
            return
        }

        const rawConversationId = item.dataset.conversationId || ''
        const nextConversationId = rawConversationId !== '' ? rawConversationId : null
        const endpoint = this.buildMessagesEndpoint(item, rawConversationId)

        if (!endpoint) {
            return
        }

        if (this.activeConversationItem === item && this.activeConversationId === nextConversationId) {
            return
        }

        this.conversationItems.forEach((conversationItem) => {
            const isActive = conversationItem === item
            conversationItem.classList.toggle('active', isActive)
        })

        this.activeConversationItem = item
        this.activeConversationId = nextConversationId
        this.updateConversationHeader(item)
        this.setComposerEnabled(true)
        this.loadConversationMessages(nextConversationId, endpoint)
    }

    initConversationSelection = () => {
        this.conversationItems.forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault()
                this.selectConversation(item)
            })
        })

        const firstVisibleConversation = this.getFirstVisibleConversation()
        if (firstVisibleConversation) {
            this.selectConversation(firstVisibleConversation)
        }
    }

    initForm = () => {
        this.chatForm?.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!this.activeConversationId || !this.chatInput) {
                return
            }

            const data = Object.fromEntries(new FormData(e.target).entries());
            if (data.message) {
                if (data.message.trim().length === 0) {
                    this.chatForm.reset();
                } else {
                    const localMessage = {
                        id: `local-${Date.now()}`,
                        body: data['message'],
                        senderName: 'You',
                        senderAvatarSrc: '',
                        isOwn: true,
                        timeLabel: new Date().toLocaleTimeString([], {
                            hour: 'numeric',
                            minute: '2-digit',
                        }).toLowerCase(),
                    }

                    this.setMessagesState('', false)
                    this.messagesList?.appendChild(this.createMessageNode(localMessage, Date.now()))
                    this.chatInput.value = ''
                    this.scrollToBottom(true)
                }
            }
        })
    }

    scrollToBottom = (smooth = false) => {
        if (this.messagesSimplebar && this.messagesSimplebar.getScrollElement()) {
            const last = this.messagesSimplebar.getScrollElement().scrollHeight;
            if (smooth)
                this.messagesSimplebar.getScrollElement().style.scrollBehavior = "smooth"
            this.messagesSimplebar.getScrollElement().scrollTop = last
        }
    }

    init = () => {
        this.cacheElements();
        this.setComposerEnabled(false);
        this.scrollToBottom();
        this.initFilters();
        this.initSearch();
        this.initConversationSelection();
        this.initForm();
    }
}

const bootstrapChatApp = () => {
    new ChatApp().init()
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapChatApp, { once: true })
} else {
    bootstrapChatApp()
}
