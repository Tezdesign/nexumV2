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
        this.detailsDrawer = null
        this.detailsBackdrop = null
        this.detailsToggleButton = null
        this.detailsCloseButton = null
        this.detailsAvatar = null
        this.detailsAvatarFallback = null
        this.detailsName = null
        this.detailsType = null
        this.detailsDangerActionWrap = null
        this.detailsDangerAction = null
        this.detailsDangerIcon = null
        this.detailsChatInfoToggle = null
        this.detailsChatInfoBody = null
        this.detailsChatInfoChevron = null
        this.detailsInfoName = null
        this.detailsInfoType = null
        this.detailsInfoCreated = null
        this.detailsCustomizeSection = null
        this.detailsCustomizeToggle = null
        this.detailsCustomizeBody = null
        this.detailsCustomizeChevron = null
        this.detailsMembersSection = null
        this.detailsMembersToggle = null
        this.detailsMembersBody = null
        this.detailsMembersChevron = null
        this.membersList = null
        this.membersAddButton = null
        this.customizeNameToggleButton = null
        this.customizeAvatarToggleButton = null
        this.customizeAvatarInput = null
        this.renameModal = null
        this.renameModalInstance = null
        this.renameForm = null
        this.renameInput = null
        this.renameError = null
        this.renameSubmitButton = null
        this.nicknameModal = null
        this.nicknameForm = null
        this.nicknameInput = null
        this.nicknameError = null
        this.nicknameLabel = null
        this.nicknameSubmitButton = null
        this.addMembersModal = null
        this.addMembersSearch = null
        this.addMembersList = null
        this.addMembersEmpty = null
        this.dangerConfirmModal = null
        this.dangerConfirmMessage = null
        this.dangerConfirmSubmitButton = null
        this.dangerConfirmCancelButton = null
        this.dangerConfirmResolver = null
        this.editingMemberUserId = null
        this.membersById = new Map()
        this.participantNicknameMap = new Map()
        this.bottomNotice = null
        this.bottomNoticeTimer = null
        this.currentUserName = 'You'
        this.currentUserAvatar = ''
        this.messagesSimplebar = null
        this.chatForm = null
        this.chatInput = null
        this.chatSendButton = null
        this.activeFetchController = null
        this.activeAttachmentControllers = new Map()
        this.activeAudioElement = null
        this.linkPreviewCache = new Map()
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
        this.detailsDrawer = document.querySelector('[data-apps-chat="details-drawer"]')
        this.detailsBackdrop = document.querySelector('[data-apps-chat="details-backdrop"]')
        this.detailsToggleButton = document.querySelector('[data-apps-chat="details-toggle"]')
        this.detailsCloseButton = document.querySelector('[data-apps-chat="details-close"]')
        this.detailsAvatar = document.querySelector('[data-apps-chat="details-avatar"]')
        this.detailsAvatarFallback = document.querySelector('[data-apps-chat="details-avatar-fallback"]')
        this.detailsName = document.querySelector('[data-apps-chat="details-name"]')
        this.detailsType = document.querySelector('[data-apps-chat="details-type"]')
        this.detailsDangerActionWrap = document.querySelector('[data-apps-chat="details-danger-action-wrap"]')
        this.detailsDangerAction = document.querySelector('[data-apps-chat="details-danger-action"]')
        this.detailsDangerIcon = document.querySelector('[data-apps-chat="details-danger-icon"]')
        this.detailsChatInfoToggle = document.querySelector('[data-apps-chat="details-chat-info-toggle"]')
        this.detailsChatInfoBody = document.querySelector('[data-apps-chat="details-chat-info-body"]')
        this.detailsChatInfoChevron = document.querySelector('[data-apps-chat="details-chat-info-chevron"]')
        this.detailsInfoName = document.querySelector('[data-apps-chat="details-info-name"]')
        this.detailsInfoType = document.querySelector('[data-apps-chat="details-info-type"]')
        this.detailsInfoCreated = document.querySelector('[data-apps-chat="details-info-created"]')
        this.detailsCustomizeSection = document.querySelector('[data-apps-chat="details-customize-section"]')
        this.detailsCustomizeToggle = document.querySelector('[data-apps-chat="details-customize-toggle"]')
        this.detailsCustomizeBody = document.querySelector('[data-apps-chat="details-customize-body"]')
        this.detailsCustomizeChevron = document.querySelector('[data-apps-chat="details-customize-chevron"]')
        this.detailsMembersSection = document.querySelector('[data-apps-chat="details-members-section"]')
        this.detailsMembersToggle = document.querySelector('[data-apps-chat="details-members-toggle"]')
        this.detailsMembersBody = document.querySelector('[data-apps-chat="details-members-body"]')
        this.detailsMembersChevron = document.querySelector('[data-apps-chat="details-members-chevron"]')
        this.membersList = document.querySelector('[data-apps-chat="members-list"]')
        this.membersAddButton = document.querySelector('[data-apps-chat="members-add-button"]')
        this.customizeNameToggleButton = document.querySelector('[data-apps-chat="customize-name-toggle"]')
        this.customizeAvatarToggleButton = document.querySelector('[data-apps-chat="customize-avatar-toggle"]')
        this.customizeAvatarInput = document.querySelector('[data-apps-chat="customize-avatar-input"]')
        this.renameModal = document.querySelector('[data-apps-chat="rename-modal"]')
        this.renameForm = document.querySelector('[data-apps-chat="rename-form"]')
        this.renameInput = document.querySelector('[data-apps-chat="rename-input"]')
        this.renameError = document.querySelector('[data-apps-chat="rename-error"]')
        this.renameSubmitButton = document.querySelector('[data-apps-chat="rename-submit"]')
        this.nicknameModal = document.querySelector('[data-apps-chat="nickname-modal"]')
        this.nicknameForm = document.querySelector('[data-apps-chat="nickname-form"]')
        this.nicknameInput = document.querySelector('[data-apps-chat="nickname-input"]')
        this.nicknameError = document.querySelector('[data-apps-chat="nickname-error"]')
        this.nicknameLabel = document.querySelector('[data-apps-chat="nickname-label"]')
        this.nicknameSubmitButton = document.querySelector('[data-apps-chat="nickname-submit"]')
        this.addMembersModal = document.querySelector('[data-apps-chat="add-members-modal"]')
        this.addMembersSearch = document.querySelector('[data-apps-chat="add-members-search"]')
        this.addMembersList = document.querySelector('[data-apps-chat="add-members-list"]')
        this.addMembersEmpty = document.querySelector('[data-apps-chat="add-members-empty"]')
        this.dangerConfirmModal = document.querySelector('[data-apps-chat="danger-confirm-modal"]')
        this.dangerConfirmMessage = document.querySelector('[data-apps-chat="danger-confirm-message"]')
        this.dangerConfirmSubmitButton = document.querySelector('[data-apps-chat="danger-confirm-submit"]')
        this.dangerConfirmCancelButton = document.querySelector('[data-apps-chat="danger-confirm-cancel"]')
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

    getConversationTypeRaw = (item) => {
        return (item?.dataset.conversationType || '').toUpperCase()
    }

    isGroupConversation = (item) => {
        return this.getConversationTypeRaw(item) === 'GROUP'
    }

    isConversationOwner = (item) => {
        return (item?.dataset.conversationIsAdmin || '0') === '1'
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

        this.updateDetailsDrawer(item)
    }

    updateDetailsDrawer = (item) => {
        if (!this.detailsDrawer) {
            return
        }

        const conversationName = this.getConversationDisplayName(item)
        const conversationTypeRaw = this.getConversationTypeRaw(item)
        const conversationType = conversationTypeRaw === 'DM' ? 'Direct message' : 'Group conversation'
        const avatarSrc = item?.dataset.conversationAvatar || ''
        const conversationCreatedAt = item?.dataset.conversationCreatedAt || '--'
        const isAdmin = (item?.dataset.conversationIsAdmin || '0') === '1'

        if (this.detailsName) {
            this.detailsName.textContent = conversationName
        }

        if (this.detailsType) {
            this.detailsType.textContent = conversationType
        }

        if (this.detailsInfoName) {
            this.detailsInfoName.textContent = conversationName
        }

        if (this.detailsInfoType) {
            this.detailsInfoType.textContent = conversationType
        }

        if (this.detailsInfoCreated) {
            this.detailsInfoCreated.textContent = conversationCreatedAt
        }

        if (this.detailsCustomizeSection) {
            this.detailsCustomizeSection.classList.toggle('d-none', conversationTypeRaw !== 'GROUP')
            if (conversationTypeRaw !== 'GROUP') {
                this.setCustomizeOpen(false)
            }
        }

        if (this.detailsMembersSection) {
            this.detailsMembersSection.classList.remove('d-none')
        }

        if (this.customizeNameToggleButton) {
            this.customizeNameToggleButton.disabled = conversationTypeRaw !== 'GROUP'
        }

        if (this.customizeAvatarToggleButton) {
            this.customizeAvatarToggleButton.disabled = conversationTypeRaw !== 'GROUP'
        }

        if (this.detailsDangerAction) {
            if (conversationTypeRaw === 'DM') {
                this.detailsDangerAction.textContent = 'Delete conversation'
            } else {
                this.detailsDangerAction.textContent = isAdmin ? 'Delete conversation' : 'Leave conversation'
            }
        }

        if (this.detailsDangerIcon) {
            this.detailsDangerIcon.className = 'fs-16'
            if (conversationTypeRaw === 'DM' || isAdmin) {
                this.detailsDangerIcon.classList.add('ti', 'ti-trash')
            } else {
                this.detailsDangerIcon.classList.add('ti', 'ti-logout-2')
            }
        }

        if (this.detailsAvatar && this.detailsAvatarFallback) {
            if (avatarSrc) {
                this.detailsAvatar.src = avatarSrc
                this.detailsAvatar.classList.remove('d-none')
                this.detailsAvatarFallback.classList.add('d-none')
            } else {
                this.detailsAvatar.removeAttribute('src')
                this.detailsAvatar.classList.add('d-none')
                this.detailsAvatarFallback.classList.remove('d-none')
                this.detailsAvatarFallback.textContent = conversationName.slice(0, 1).toUpperCase() || '-'
            }
        }
    }

    syncConversationItem = (item, update) => {
        if (!item || !update) {
            return
        }

        const nextName = String(update.name || this.getConversationDisplayName(item))
        const nextAvatar = String(update.avatarSrc || '')

        item.dataset.conversationName = nextName
        item.dataset.groupTitle = this.isGroupConversation(item) ? nextName : ''
        item.dataset.dmName = this.isGroupConversation(item) ? '' : nextName
        item.dataset.conversationAvatar = nextAvatar

        const nameNode = item.querySelector('[data-apps-chat="conversation-name"]')
        if (nameNode) {
            nameNode.textContent = nextName
        }

        const avatarContainer = item.querySelector('.avatar-md')
        const imageNode = item.querySelector('[data-apps-chat="conversation-avatar-image"]')
        const fallbackNode = item.querySelector('[data-apps-chat="conversation-avatar-fallback"]')
        if (nextAvatar) {
            let nextImageNode = imageNode
            if (!nextImageNode && avatarContainer) {
                nextImageNode = document.createElement('img')
                nextImageNode.className = 'w-100 h-100 object-fit-cover rounded-circle'
                nextImageNode.alt = nextName
                nextImageNode.setAttribute('data-apps-chat', 'conversation-avatar-image')
                avatarContainer.textContent = ''
                avatarContainer.appendChild(nextImageNode)
            }

            if (nextImageNode) {
                nextImageNode.src = nextAvatar
                nextImageNode.classList.remove('d-none')
            }

            if (fallbackNode) {
                fallbackNode.classList.add('d-none')
            }
        } else {
            let nextFallbackNode = fallbackNode
            if (!nextFallbackNode && avatarContainer) {
                nextFallbackNode = document.createElement('div')
                nextFallbackNode.className = 'h-100 w-100 rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center fw-semibold'
                nextFallbackNode.setAttribute('data-apps-chat', 'conversation-avatar-fallback')
                avatarContainer.textContent = ''
                avatarContainer.appendChild(nextFallbackNode)
            }

            if (imageNode) {
                imageNode.removeAttribute('src')
                imageNode.classList.add('d-none')
            }

            if (nextFallbackNode) {
                nextFallbackNode.classList.remove('d-none')
                nextFallbackNode.textContent = nextName.slice(0, 1).toUpperCase() || '-'
            }
        }
    }

    syncConversationSelection = (update) => {
        if (!update || !this.activeConversationItem) {
            return
        }

        const conversationId = String(this.activeConversationItem.dataset.conversationId || '')
        if (conversationId !== String(update.id || '')) {
            return
        }

        this.syncConversationItem(this.activeConversationItem, update)
        this.updateConversationHeader(this.activeConversationItem)
    }

    setDetailsDrawerOpen = (open) => {
        if (!this.detailsDrawer) {
            return
        }

        this.detailsDrawer.classList.toggle('d-none', !open)
        this.detailsDrawer.style.display = open ? 'block' : 'none'
        if (this.detailsBackdrop) {
            this.detailsBackdrop.classList.toggle('d-none', !open)
            this.detailsBackdrop.style.display = open ? 'block' : 'none'
        }
        if (this.detailsToggleButton) {
            this.detailsToggleButton.setAttribute('aria-expanded', String(open))
        }

        if (!open) {
            this.setChatInfoOpen(false)
            this.setCustomizeOpen(false)
            this.setMembersOpen(false)
        }
    }

    setChatInfoOpen = (open) => {
        if (!this.detailsChatInfoBody || !this.detailsChatInfoToggle) {
            return
        }

        this.detailsChatInfoBody.classList.toggle('d-none', !open)
        this.detailsChatInfoToggle.setAttribute('aria-expanded', String(open))

        if (this.detailsChatInfoChevron) {
            this.detailsChatInfoChevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)'
            this.detailsChatInfoChevron.style.transition = 'transform 0.16s ease'
        }
    }

    setCustomizeOpen = (open) => {
        if (!this.detailsCustomizeBody || !this.detailsCustomizeToggle) {
            return
        }

        this.detailsCustomizeBody.classList.toggle('d-none', !open)
        this.detailsCustomizeToggle.setAttribute('aria-expanded', String(open))

        if (this.detailsCustomizeChevron) {
            this.detailsCustomizeChevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)'
            this.detailsCustomizeChevron.style.transition = 'transform 0.16s ease'
        }
    }

    setMembersOpen = (open) => {
        if (!this.detailsMembersBody || !this.detailsMembersToggle) {
            return
        }

        this.detailsMembersBody.classList.toggle('d-none', !open)
        this.detailsMembersToggle.setAttribute('aria-expanded', String(open))

        if (this.detailsMembersChevron) {
            this.detailsMembersChevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)'
            this.detailsMembersChevron.style.transition = 'transform 0.16s ease'
        }
    }

    initDetailsDrawer = () => {
        if (!this.detailsDrawer) {
            return
        }

        this.setDetailsDrawerOpen(false)

        this.detailsToggleButton?.addEventListener('click', (event) => {
            event.preventDefault()
            const isCurrentlyOpen = !this.detailsDrawer.classList.contains('d-none') && this.detailsDrawer.style.display !== 'none'
            this.setDetailsDrawerOpen(!isCurrentlyOpen)
        })

        this.detailsCloseButton?.addEventListener('click', (event) => {
            event.preventDefault()
            this.setDetailsDrawerOpen(false)
        })

        this.detailsBackdrop?.addEventListener('click', () => {
            this.setDetailsDrawerOpen(false)
        })

        this.detailsChatInfoToggle?.addEventListener('click', (event) => {
            event.preventDefault()
            const isOpen = !this.detailsChatInfoBody?.classList.contains('d-none')
            this.setChatInfoOpen(!isOpen)
        })

        this.detailsChatInfoToggle?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            event.preventDefault()
            const isOpen = !this.detailsChatInfoBody?.classList.contains('d-none')
            this.setChatInfoOpen(!isOpen)
        })

        this.detailsCustomizeToggle?.addEventListener('click', (event) => {
            event.preventDefault()
            const isOpen = !this.detailsCustomizeBody?.classList.contains('d-none')
            this.setCustomizeOpen(!isOpen)
        })

        this.detailsCustomizeToggle?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            event.preventDefault()
            const isOpen = !this.detailsCustomizeBody?.classList.contains('d-none')
            this.setCustomizeOpen(!isOpen)
        })

        this.detailsMembersToggle?.addEventListener('click', (event) => {
            event.preventDefault()
            const isOpen = !this.detailsMembersBody?.classList.contains('d-none')
            this.setMembersOpen(!isOpen)
        })

        this.detailsMembersToggle?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            event.preventDefault()
            const isOpen = !this.detailsMembersBody?.classList.contains('d-none')
            this.setMembersOpen(!isOpen)
        })
    }

    getBootstrapModal = (element) => {
        if (!element || !window.bootstrap?.Modal) {
            return null
        }

        this.renameModalInstance = window.bootstrap.Modal.getOrCreateInstance(element)
        return this.renameModalInstance
    }

    setRenameError = (message = '') => {
        if (!this.renameError) {
            return
        }

        const hasError = String(message || '').trim().length > 0
        this.renameError.textContent = message || ''
        this.renameError.classList.toggle('d-none', !hasError)
    }

    showBottomNotice = (message) => {
        if (!message) {
            return
        }

        if (!this.bottomNotice) {
            this.bottomNotice = document.createElement('div')
            this.bottomNotice.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3 z-3'
            this.bottomNotice.style.maxWidth = '92vw'
            this.bottomNotice.style.width = 'auto'

            const card = document.createElement('div')
            card.className = 'shadow-lg rounded-3 border border-danger-subtle bg-danger-subtle text-danger px-3 py-2 small'
            card.style.maxWidth = '360px'
            card.style.boxShadow = '0 18px 36px rgba(0,0,0,0.18)'
            card.dataset.appsChatNotice = 'body'

            this.bottomNotice.appendChild(card)
            document.body.appendChild(this.bottomNotice)
        }

        const body = this.bottomNotice.querySelector('[data-apps-chat-notice="body"]')
        if (body) {
            body.textContent = message
        }

        this.bottomNotice.classList.remove('d-none')

        if (this.bottomNoticeTimer) {
            window.clearTimeout(this.bottomNoticeTimer)
        }

        this.bottomNoticeTimer = window.setTimeout(() => {
            if (this.bottomNotice) {
                this.bottomNotice.classList.add('d-none')
            }
        }, 2600)
    }

    resolveDangerConfirm = (confirmed) => {
        if (!this.dangerConfirmResolver) {
            return
        }

        const resolve = this.dangerConfirmResolver
        this.dangerConfirmResolver = null
        resolve(confirmed)
    }

    openDangerConfirmModal = (message) => {
        if (!this.dangerConfirmModal || !window.bootstrap?.Modal) {
            return Promise.resolve(window.confirm(message))
        }

        if (this.dangerConfirmMessage) {
            this.dangerConfirmMessage.textContent = message
        }

        if (this.dangerConfirmResolver) {
            this.resolveDangerConfirm(false)
        }

        const modal = this.getBootstrapModal(this.dangerConfirmModal)
        modal?.show()

        return new Promise((resolve) => {
            this.dangerConfirmResolver = resolve
        })
    }

    openRenameModal = () => {
        if (!this.activeConversationItem || !this.isGroupConversation(this.activeConversationItem)) {
            return
        }

        if (!this.renameModal || !this.renameInput) {
            return
        }

        this.setRenameError('')
        this.renameInput.value = this.getConversationDisplayName(this.activeConversationItem)
        this.getBootstrapModal(this.renameModal)?.show()

        queueMicrotask(() => {
            this.renameInput?.focus()
            this.renameInput?.select()
        })
    }

    closeRenameModal = () => {
        this.getBootstrapModal(this.renameModal)?.hide()
    }

    buildConversationRenameEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/name`
    }

    buildConversationAvatarEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/avatar`
    }

    buildParticipantsEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants`
    }

    buildParticipantCandidatesEndpoint = (conversationId, query = '') => {
        const q = String(query || '').trim()
        if (!q) {
            return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants/candidates`
        }

        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants/candidates?q=${encodeURIComponent(q)}`
    }

    buildAddParticipantEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants/add`
    }

    buildParticipantNicknameEndpoint = (conversationId, userId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants/${encodeURIComponent(String(userId))}/nickname`
    }

    buildKickParticipantEndpoint = (conversationId, userId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/participants/${encodeURIComponent(String(userId))}/kick`
    }

    buildConversationDangerActionEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/danger-action`
    }

    setNicknameError = (message = '') => {
        if (!this.nicknameError) {
            return
        }

        const hasError = String(message || '').trim().length > 0
        this.nicknameError.textContent = message || ''
        this.nicknameError.classList.toggle('d-none', !hasError)
    }

    closeNicknameModal = () => {
        this.getBootstrapModal(this.nicknameModal)?.hide()
        this.editingMemberUserId = null
    }

    openNicknameModal = (userId) => {
        const member = this.membersById.get(String(userId))
        if (!member || !this.nicknameModal || !this.nicknameInput) {
            return
        }

        this.editingMemberUserId = String(userId)
        this.nicknameInput.value = String(member.nickname || member.name || '').trim()
        if (this.nicknameLabel) {
            this.nicknameLabel.textContent = `Nickname for ${member.fullName || member.name || 'member'}`
        }
        this.setNicknameError('')
        this.getBootstrapModal(this.nicknameModal)?.show()

        queueMicrotask(() => {
            this.nicknameInput?.focus()
            this.nicknameInput?.select()
        })
    }

    updateDirectConversationTitleFromMembers = (members, renamedUserId) => {
        if (!this.activeConversationItem || this.getConversationTypeRaw(this.activeConversationItem) !== 'DM') {
            return
        }

        const targetId = String(renamedUserId || '')
        const targetMember = members.find((member) => String(member.userId || '') === targetId)
        const selfMember = members.find((member) => !!member.isSelf)
        const nextName = targetMember && !targetMember.isSelf
            ? String(targetMember.name || targetMember.fullName || 'Unknown conversation')
            : String(selfMember?.name || selfMember?.fullName || this.getConversationDisplayName(this.activeConversationItem))

        this.syncConversationItem(this.activeConversationItem, {
            name: nextName,
            avatarSrc: this.activeConversationItem.dataset.conversationAvatar || '',
        })
        this.updateConversationHeader(this.activeConversationItem)
    }

    renderMembers = (members) => {
        if (!this.membersList) {
            return
        }

        this.membersById.clear()
        this.participantNicknameMap.clear()
        this.membersList.innerHTML = ''

        members.forEach((member) => {
            const userId = String(member.userId || '')
            this.membersById.set(userId, member)
            this.participantNicknameMap.set(userId, member.name || '')

            const row = document.createElement('div')
            row.className = 'd-flex align-items-start gap-2'

            const avatar = this.createAvatarElement(member.avatarSrc || '', member.name || 'M')
            row.appendChild(avatar)

            const body = document.createElement('div')
            body.className = 'flex-grow-1 min-w-0'
            body.innerHTML = `<div class="fw-semibold text-truncate">${member.name || 'Unknown User'}</div><div class="text-muted small text-truncate">${member.subtitle || ''}</div>`
            row.appendChild(body)

            const actions = document.createElement('div')
            actions.className = 'dropdown'
            const actionButton = document.createElement('button')
            actionButton.type = 'button'
            actionButton.className = 'btn btn-sm btn-icon btn-ghost-light'
            actionButton.setAttribute('data-bs-toggle', 'dropdown')
            actionButton.setAttribute('aria-expanded', 'false')
            actionButton.innerHTML = '<i class="ti ti-dots-vertical"></i>'
            actions.appendChild(actionButton)

            const menu = document.createElement('ul')
            menu.className = 'dropdown-menu dropdown-menu-end'

            if (member.canRenameNickname) {
                const renameItem = document.createElement('li')
                renameItem.innerHTML = `<button type="button" class="dropdown-item" data-member-action="rename" data-user-id="${userId}">Change nickname</button>`
                menu.appendChild(renameItem)
            }

            if (member.canKick) {
                const kickItem = document.createElement('li')
                kickItem.innerHTML = `<button type="button" class="dropdown-item text-danger" data-member-action="kick" data-user-id="${userId}">Kick member</button>`
                menu.appendChild(kickItem)
            }

            actions.appendChild(menu)
            row.appendChild(actions)
            this.membersList.appendChild(row)
        })
    }

    loadConversationMembers = async (conversationId) => {
        if (!conversationId) {
            return []
        }

        try {
            const response = await fetch(this.buildParticipantsEndpoint(conversationId), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to load members.')
            }

            const members = Array.isArray(payload.members) ? payload.members : []
            this.renderMembers(members)
            if (this.membersAddButton) {
                this.membersAddButton.classList.toggle('d-none', !payload.canAddMembers)
                this.membersAddButton.disabled = !payload.canAddMembers
            }

            return members
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to load members.')
            return []
        }
    }

    loadAddableMembers = async (query = '') => {
        if (!this.activeConversationId || !this.addMembersList) {
            return
        }

        const response = await fetch(this.buildParticipantCandidatesEndpoint(this.activeConversationId, query), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
        })

        const payload = await response.json().catch(() => ({}))
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to load users.')
        }

        const candidates = Array.isArray(payload.candidates) ? payload.candidates : []
        this.addMembersList.innerHTML = ''

        candidates.forEach((candidate) => {
            const row = document.createElement('div')
            row.className = 'd-flex align-items-center gap-2 border rounded-3 px-2 py-2'

            const avatar = this.createAvatarElement(candidate.avatarSrc || '', candidate.name || 'M')
            row.appendChild(avatar)

            const body = document.createElement('div')
            body.className = 'flex-grow-1 min-w-0'
            body.innerHTML = `<div class="fw-medium text-truncate">${candidate.name || 'Unknown User'}</div><div class="text-muted small">${candidate.role || 'Member'}</div>`
            row.appendChild(body)

            const addButton = document.createElement('button')
            addButton.type = 'button'
            addButton.className = 'btn btn-sm btn-primary'
            addButton.setAttribute('data-add-user-id', String(candidate.userId || ''))
            addButton.textContent = 'Add'
            row.appendChild(addButton)

            this.addMembersList.appendChild(row)
        })

        if (this.addMembersEmpty) {
            this.addMembersEmpty.classList.toggle('d-none', candidates.length > 0)
        }
    }

    openAddMembersModal = async () => {
        if (!this.activeConversationId) {
            return
        }

        this.getBootstrapModal(this.addMembersModal)?.show()
        try {
            await this.loadAddableMembers('')
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to load users.')
        }
    }

    addMemberToConversation = async (userId) => {
        if (!this.activeConversationId) {
            return
        }

        const response = await fetch(this.buildAddParticipantEndpoint(this.activeConversationId), {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams({ userId: String(userId) }),
        })

        const payload = await response.json().catch(() => ({}))
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to add member.')
        }

        await this.loadConversationMembers(this.activeConversationId)
        await this.loadAddableMembers(this.addMembersSearch?.value || '')
    }

    submitNicknameUpdate = async (event) => {
        event.preventDefault()

        if (!this.activeConversationId || !this.editingMemberUserId || !this.nicknameInput) {
            return
        }

        const targetUserId = this.editingMemberUserId
        this.setNicknameError('')
        this.nicknameSubmitButton?.setAttribute('disabled', 'disabled')

        try {
            const response = await fetch(this.buildParticipantNicknameEndpoint(this.activeConversationId, this.editingMemberUserId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ nickname: this.nicknameInput.value || '' }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to change nickname.')
            }

            this.closeNicknameModal()
            const members = await this.loadConversationMembers(this.activeConversationId)
            this.updateDirectConversationTitleFromMembers(Array.isArray(members) ? members : [], targetUserId)
            await this.loadConversationMessages(this.activeConversationId, this.buildMessagesEndpoint(this.activeConversationItem, this.activeConversationId))
        } catch (error) {
            this.setNicknameError(error?.message || 'Failed to change nickname.')
        } finally {
            this.nicknameSubmitButton?.removeAttribute('disabled')
        }
    }

    kickMember = async (userId) => {
        if (!this.activeConversationId) {
            return
        }

        const response = await fetch(this.buildKickParticipantEndpoint(this.activeConversationId, userId), {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
        })

        const payload = await response.json().catch(() => ({}))
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to kick member.')
        }

        await this.loadConversationMembers(this.activeConversationId)
    }

    resetConversationView = () => {
        this.activeConversationItem = null
        this.activeConversationId = null

        this.conversationItems.forEach((conversationItem) => {
            conversationItem.classList.remove('active')
        })

        if (this.activeConversationName) {
            this.activeConversationName.textContent = 'Select a conversation'
        }

        if (this.activeConversationMeta) {
            this.activeConversationMeta.textContent = 'Choose a conversation from the list'
        }

        if (this.activeConversationAvatar) {
            this.activeConversationAvatar.removeAttribute('src')
            this.activeConversationAvatar.classList.add('d-none')
        }

        if (this.activeConversationAvatarFallback) {
            this.activeConversationAvatarFallback.classList.remove('d-none')
            this.activeConversationAvatarFallback.textContent = '-'
        }

        this.clearMessages()
        this.setMessagesState('Select a conversation to load messages.', true)
        this.setComposerEnabled(false)
        this.setDetailsDrawerOpen(false)
    }

    removeConversationItemById = (conversationId) => {
        const idText = String(conversationId || '')
        const targetItem = this.conversationItems.find((item) => String(item.dataset.conversationId || '') === idText)
        if (!targetItem) {
            return
        }

        targetItem.remove()
        this.conversationItems = this.conversationItems.filter((item) => item !== targetItem)
    }

    executeConversationDangerAction = async () => {
        if (!this.activeConversationId || !this.activeConversationItem) {
            return
        }

        const conversationId = this.activeConversationId
        const actionLabel = (this.detailsDangerAction?.textContent || '').trim() || 'Delete conversation'
        const confirmed = await this.openDangerConfirmModal(`${actionLabel}?`)
        if (!confirmed) {
            return
        }

        this.detailsDangerActionWrap?.classList.add('disabled')
        this.detailsDangerActionWrap?.setAttribute('aria-disabled', 'true')

        try {
            const response = await fetch(this.buildConversationDangerActionEndpoint(conversationId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to process this action.')
            }

            this.removeConversationItemById(conversationId)
            this.applyConversationVisibility()

            const nextConversation = this.getFirstVisibleConversation()
            if (nextConversation) {
                await this.selectConversation(nextConversation)
            } else {
                this.resetConversationView()
            }
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to process this action.')
        } finally {
            this.detailsDangerActionWrap?.classList.remove('disabled')
            this.detailsDangerActionWrap?.removeAttribute('aria-disabled')
        }
    }

    submitConversationRename = async (event) => {
        event.preventDefault()

        if (!this.activeConversationItem || !this.renameInput) {
            return
        }

        const conversationId = this.activeConversationItem.dataset.conversationId || ''
        const title = this.renameInput.value.trim()

        this.setRenameError('')
        this.renameSubmitButton?.setAttribute('disabled', 'disabled')

        try {
            const response = await fetch(this.buildConversationRenameEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({ title }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to rename chat.')
            }

            this.syncConversationSelection(payload.conversation)
            this.closeRenameModal()
        } catch (error) {
            this.setRenameError(error?.message || 'Failed to rename chat.')
        } finally {
            this.renameSubmitButton?.removeAttribute('disabled')
        }
    }

    triggerAvatarPicker = () => {
        if (!this.activeConversationItem || !this.isGroupConversation(this.activeConversationItem)) {
            return
        }

        if (!this.isConversationOwner(this.activeConversationItem)) {
            this.showBottomNotice('Only the chat owner can modify this discussion.')
            return
        }

        this.customizeAvatarInput?.click()
    }

    uploadConversationAvatar = async () => {
        if (!this.activeConversationItem || !this.customizeAvatarInput?.files?.length) {
            return
        }

        const conversationId = this.activeConversationItem.dataset.conversationId || ''
        const file = this.customizeAvatarInput.files[0]

        const formData = new FormData()
        formData.append('avatar', file)

        try {
            const response = await fetch(this.buildConversationAvatarEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: formData,
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to update the chat picture.')
            }

            this.syncConversationSelection(payload.conversation)
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to update the chat picture.')
        } finally {
            this.customizeAvatarInput.value = ''
        }
    }

    initCustomization = () => {
        this.customizeNameToggleButton?.addEventListener('click', (event) => {
            event.preventDefault()
            this.openRenameModal()
        })

        this.customizeAvatarToggleButton?.addEventListener('click', (event) => {
            event.preventDefault()
            this.triggerAvatarPicker()
        })

        this.customizeAvatarInput?.addEventListener('change', () => {
            this.uploadConversationAvatar()
        })

        this.renameForm?.addEventListener('submit', this.submitConversationRename)

        this.renameModal?.addEventListener('hidden.bs.modal', () => {
            this.setRenameError('')
        })

        this.renameModal?.querySelector('[data-apps-chat="rename-modal-close"]')?.addEventListener('click', () => {
            this.closeRenameModal()
        })

        this.renameModal?.querySelector('[data-apps-chat="rename-modal-cancel"]')?.addEventListener('click', () => {
            this.closeRenameModal()
        })

        this.membersList?.addEventListener('click', async (event) => {
            const actionButton = event.target.closest('[data-member-action]')
            if (!actionButton) {
                return
            }

            const action = actionButton.getAttribute('data-member-action')
            const userId = actionButton.getAttribute('data-user-id')
            if (!action || !userId) {
                return
            }

            if (action === 'rename') {
                this.openNicknameModal(userId)
                return
            }

            if (action === 'kick') {
                try {
                    await this.kickMember(userId)
                } catch (error) {
                    this.showBottomNotice(error?.message || 'Failed to kick member.')
                }
            }
        })

        this.membersAddButton?.addEventListener('click', async () => {
            await this.openAddMembersModal()
        })

        this.nicknameForm?.addEventListener('submit', this.submitNicknameUpdate)
        this.nicknameModal?.addEventListener('hidden.bs.modal', () => {
            this.setNicknameError('')
            this.editingMemberUserId = null
        })
        this.nicknameModal?.querySelector('[data-apps-chat="nickname-modal-close"]')?.addEventListener('click', () => {
            this.closeNicknameModal()
        })
        this.nicknameModal?.querySelector('[data-apps-chat="nickname-modal-cancel"]')?.addEventListener('click', () => {
            this.closeNicknameModal()
        })

        this.addMembersModal?.querySelector('[data-apps-chat="add-members-close"]')?.addEventListener('click', () => {
            this.getBootstrapModal(this.addMembersModal)?.hide()
        })

        this.addMembersSearch?.addEventListener('input', async () => {
            try {
                await this.loadAddableMembers(this.addMembersSearch.value || '')
            } catch (error) {
                this.showBottomNotice(error?.message || 'Failed to load users.')
            }
        })

        this.addMembersList?.addEventListener('click', async (event) => {
            const addButton = event.target.closest('[data-add-user-id]')
            if (!addButton) {
                return
            }

            const userId = addButton.getAttribute('data-add-user-id')
            if (!userId) {
                return
            }

            addButton.setAttribute('disabled', 'disabled')
            try {
                await this.addMemberToConversation(userId)
            } catch (error) {
                this.showBottomNotice(error?.message || 'Failed to add member.')
            } finally {
                addButton.removeAttribute('disabled')
            }
        })

        this.dangerConfirmSubmitButton?.addEventListener('click', () => {
            this.resolveDangerConfirm(true)
            this.getBootstrapModal(this.dangerConfirmModal)?.hide()
        })

        this.dangerConfirmCancelButton?.addEventListener('click', () => {
            this.resolveDangerConfirm(false)
            this.getBootstrapModal(this.dangerConfirmModal)?.hide()
        })

        this.dangerConfirmModal?.querySelector('[data-apps-chat="danger-confirm-close"]')?.addEventListener('click', () => {
            this.resolveDangerConfirm(false)
            this.getBootstrapModal(this.dangerConfirmModal)?.hide()
        })

        this.dangerConfirmModal?.addEventListener('hidden.bs.modal', () => {
            this.resolveDangerConfirm(false)
        })

        this.detailsDangerActionWrap?.addEventListener('click', async (event) => {
            event.preventDefault()
            if (this.detailsDangerActionWrap?.classList.contains('disabled')) {
                return
            }

            await this.executeConversationDangerAction()
        })

        this.detailsDangerActionWrap?.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            event.preventDefault()
            if (this.detailsDangerActionWrap?.classList.contains('disabled')) {
                return
            }

            await this.executeConversationDangerAction()
        })
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
        const fileName = String(attachment?.fileName || '').toLowerCase()
        if (mimeType.startsWith('image/')) {
            return 'image'
        }

        if (mimeType.startsWith('video/')) {
            return 'video'
        }

        if (mimeType.startsWith('audio/')) {
            return 'audio'
        }

        if (fileName.endsWith('.mp3') || fileName.endsWith('.wav') || fileName.endsWith('.ogg') || fileName.endsWith('.m4a') || fileName.endsWith('.aac') || fileName.endsWith('.flac') || fileName.endsWith('.webm')) {
            return 'audio'
        }

        return 'file'
    }

    createAttachmentNode = (attachment, index) => {
        const kind = this.getAttachmentKind(attachment)
        const url = attachment?.url || '#'
        const fileName = attachment?.fileName || `attachment-${index + 1}`

        const wrapper = document.createElement('div')
        wrapper.className = 'mt-2 d-block w-100'

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
            const primaryColor = 'var(--bs-primary)'
            const waveIdleColor = primaryColor
            const waveActiveColor = primaryColor
            const waveStrokeColor = 'rgba(13, 110, 253, 0.65)'

            const bubbleWrapper = document.createElement('div')
            bubbleWrapper.className = 'audio-bubble-wrapper d-flex align-items-center gap-2 px-2 py-2 rounded-pill border'
            bubbleWrapper.style.backgroundColor = 'var(--bs-tertiary-bg)'
            bubbleWrapper.style.borderColor = 'var(--bs-border-color)'
            bubbleWrapper.style.color = 'var(--bs-body-color)'
            bubbleWrapper.style.maxWidth = '100%'
            bubbleWrapper.style.minWidth = '240px'

            const playButton = document.createElement('button')
            playButton.type = 'button'
            playButton.className = 'btn btn-primary rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 shadow-none'
            playButton.style.width = '40px'
            playButton.style.height = '40px'
            playButton.style.padding = '0'
            playButton.style.lineHeight = '1'
            playButton.style.boxShadow = 'none'
            playButton.setAttribute('aria-label', 'Play audio')
            playButton.textContent = '▶'

            const audio = document.createElement('audio')
            audio.preload = 'metadata'
            audio.src = url
            audio.style.display = 'none'

            const contentArea = document.createElement('div')
            contentArea.className = 'flex-grow-1 min-w-0'
            contentArea.style.minWidth = '0'

            const waveformRow = document.createElement('div')
            waveformRow.style.position = 'relative'
            waveformRow.style.display = 'flex'
            waveformRow.style.alignItems = 'flex-end'
            waveformRow.style.gap = '4px'
            waveformRow.style.width = '100%'
            waveformRow.style.height = '44px'
            waveformRow.style.minHeight = '44px'
            waveformRow.style.padding = '6px 0 4px'
            waveformRow.style.cursor = 'pointer'
            waveformRow.style.userSelect = 'none'
            waveformRow.style.overflow = 'hidden'
            waveformRow.style.backgroundColor = 'rgba(13, 110, 253, 0.08)'
            waveformRow.style.borderRadius = '999px'

            const waveformSeed = String(attachment?.id || fileName || url)
            const waveformBars = []
            const barCount = 36

            for (let barIndex = 0; barIndex < barCount; barIndex += 1) {
                const bar = document.createElement('span')
                const seedChar = waveformSeed.charCodeAt(barIndex % waveformSeed.length) || (barIndex + 17)
                const normalizedHeightPx = 16 + ((seedChar + barIndex * 17) % 26)

                bar.style.width = '6px'
                bar.style.height = `${normalizedHeightPx}px`
                bar.style.flex = '1 1 0'
                bar.style.minWidth = '6px'
                bar.style.maxWidth = '8px'
                bar.style.display = 'block'
                bar.style.alignSelf = 'flex-end'
                bar.style.borderRadius = '999px'
                bar.style.backgroundColor = waveIdleColor
                bar.style.border = `1px solid ${waveStrokeColor}`
                bar.style.opacity = '0.90'
                bar.style.boxShadow = '0 0 0 1px rgba(13, 110, 253, 0.08)'
                bar.style.transformOrigin = 'center bottom'
                bar.style.transition = 'background-color 0.12s ease, opacity 0.12s ease, transform 0.12s ease'
                waveformRow.appendChild(bar)
                waveformBars.push(bar)
            }

            const playhead = document.createElement('span')
            playhead.style.position = 'absolute'
            playhead.style.top = '0'
            playhead.style.bottom = '0'
            playhead.style.width = '2px'
            playhead.style.left = '0%'
            playhead.style.borderRadius = '999px'
            playhead.style.backgroundColor = primaryColor
            playhead.style.transform = 'translateX(-1px)'

            waveformRow.appendChild(playhead)

            const timerRow = document.createElement('div')
            timerRow.className = 'd-flex align-items-center justify-content-between mt-1'

            const currentTimeText = document.createElement('span')
            currentTimeText.textContent = '0:00'
            currentTimeText.style.fontSize = '11px'
            currentTimeText.style.color = 'var(--bs-secondary-color)'

            const durationText = document.createElement('span')
            durationText.textContent = '0:00'
            durationText.style.fontSize = '11px'
            durationText.style.color = 'var(--bs-secondary-color)'

            timerRow.appendChild(currentTimeText)
            timerRow.appendChild(durationText)

            const updatePlayState = () => {
                playButton.textContent = audio.paused ? '▶' : '❚❚'
                playButton.setAttribute('aria-label', audio.paused ? 'Play audio' : 'Pause audio')
            }

            const formatTime = (seconds) => {
                if (!Number.isFinite(seconds) || seconds < 0) {
                    return '0:00'
                }

                const minutes = Math.floor(seconds / 60)
                const remainingSeconds = String(Math.floor(seconds % 60)).padStart(2, '0')
                return `${minutes}:${remainingSeconds}`
            }

            const updateProgress = () => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    playhead.style.left = '0%'
                    currentTimeText.textContent = '0:00'
                    return
                }

                const progress = Math.min(100, Math.max(0, (audio.currentTime / audio.duration) * 100))
                const activeBarCount = Math.max(1, Math.round((progress / 100) * waveformBars.length))

                waveformBars.forEach((bar, index) => {
                    const isActive = index < activeBarCount
                    const isCurrent = index === activeBarCount - 1

                    bar.style.backgroundColor = isActive ? waveActiveColor : waveIdleColor
                    bar.style.opacity = isActive ? '1' : '0.65'
                    bar.style.transform = isCurrent && !audio.paused ? 'scaleY(1.24)' : 'scaleY(1)'
                })

                playhead.style.left = `${progress}%`
                currentTimeText.textContent = formatTime(audio.currentTime)
            }

            const updateDuration = () => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                durationText.textContent = formatTime(audio.duration)
            }

            const pauseOtherAudio = () => {
                if (this.activeAudioElement && this.activeAudioElement !== audio) {
                    this.activeAudioElement.pause()
                }
                this.activeAudioElement = audio
            }

            const seekToPointer = (event) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                const rect = waveformRow.getBoundingClientRect()
                const offset = Math.min(Math.max(0, event.clientX - rect.left), rect.width)
                const ratio = rect.width > 0 ? offset / rect.width : 0
                audio.currentTime = ratio * audio.duration
                updateProgress()
            }

            playButton.addEventListener('click', async () => {
                if (audio.paused) {
                    pauseOtherAudio()
                    try {
                        await audio.play()
                    } catch (error) {
                        console.error('Audio playback failed:', error)
                    }
                } else {
                    audio.pause()
                }

                updatePlayState()
            })

            waveformRow.addEventListener('click', seekToPointer)

            audio.addEventListener('play', updatePlayState)
            audio.addEventListener('playing', pauseOtherAudio)
            audio.addEventListener('pause', () => {
                updatePlayState()
                if (this.activeAudioElement === audio) {
                    this.activeAudioElement = null
                }
            })
            audio.addEventListener('ended', () => {
                audio.currentTime = 0
                updatePlayState()
                playhead.style.left = '0%'
                currentTimeText.textContent = '0:00'
                if (this.activeAudioElement === audio) {
                    this.activeAudioElement = null
                }
            })
            audio.addEventListener('timeupdate', updateProgress)
            audio.addEventListener('loadedmetadata', updateDuration)
            audio.addEventListener('error', () => {
                waveformBars.forEach((bar) => {
                    bar.style.backgroundColor = waveIdleColor
                    bar.style.opacity = '0.65'
                    bar.style.transform = 'scaleY(1)'
                })
                playButton.disabled = true
            })

            contentArea.appendChild(waveformRow)
            contentArea.appendChild(timerRow)

            bubbleWrapper.appendChild(playButton)
            bubbleWrapper.appendChild(audio)
            bubbleWrapper.appendChild(contentArea)

            wrapper.classList.add('mt-2')
            wrapper.appendChild(bubbleWrapper)

            updatePlayState()
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

    buildLinkPreviewEndpoint = (url) => {
        return `/apps-chat/link-preview?url=${encodeURIComponent(String(url))}`
    }

    extractMessageUrls = (value) => {
        const matches = String(value || '').match(/https?:\/\/[^\s<>"']+/gi) || []
        const cleaned = matches
            .map((url) => url.replace(/[),.;!?]+$/, ''))
            .filter((url) => url.length > 0)

        return Array.from(new Set(cleaned))
    }

    shortenLinkLabel = (url) => {
        try {
            const parsed = new URL(url)
            const host = parsed.host
            const path = parsed.pathname.replace(/^\/+/, '')
            if (!path) {
                return host
            }

            const shortPath = path.length > 28 ? `${path.slice(0, 25)}...` : path
            return `${host}/${shortPath}`
        } catch {
            return String(url).length > 56 ? `${String(url).slice(0, 53)}...` : String(url)
        }
    }

    loadLinkPreview = async (url) => {
        if (this.linkPreviewCache.has(url)) {
            return this.linkPreviewCache.get(url)
        }

        const request = fetch(this.buildLinkPreviewEndpoint(url), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
        })
            .then(async (response) => {
                if (!response.ok) {
                    return {
                        success: false,
                        preview: {
                            url,
                            displayUrl: this.shortenLinkLabel(url),
                        },
                    }
                }

                const payload = await response.json()
                return {
                    success: !!payload?.success,
                    preview: payload?.preview || {
                        url,
                        displayUrl: this.shortenLinkLabel(url),
                    },
                }
            })
            .catch(() => ({
                success: false,
                preview: {
                    url,
                    displayUrl: this.shortenLinkLabel(url),
                },
            }))

        this.linkPreviewCache.set(url, request)
        return request
    }

    hasRichMetadata = (preview) => {
        if (!preview) {
            return false
        }

        return Boolean(
            (preview.title && String(preview.title).trim() !== '') ||
            (preview.description && String(preview.description).trim() !== '') ||
            (preview.image && String(preview.image).trim() !== '') ||
            (preview.siteName && String(preview.siteName).trim() !== '')
        )
    }

    createShortLinkFallbackNode = (targetUrl, displayUrl, isOwn) => {
        const card = document.createElement('a')
        card.href = targetUrl
        card.target = '_blank'
        card.rel = 'noopener noreferrer'
        card.className = 'd-block text-decoration-none rounded-3 border px-2 py-2 mt-2'
        card.style.backgroundColor = isOwn ? 'rgba(13,110,253,0.05)' : 'rgba(108,117,125,0.08)'
        card.style.borderColor = isOwn ? 'rgba(13,110,253,0.24)' : 'rgba(108,117,125,0.22)'

        const label = document.createElement('div')
        label.style.fontSize = '12px'
        label.style.fontWeight = '500'
        label.style.color = isOwn ? '#ffffff' : '#0b1220'
        label.style.whiteSpace = 'nowrap'
        label.style.overflow = 'hidden'
        label.style.textOverflow = 'ellipsis'
        label.textContent = displayUrl

        card.appendChild(label)
        return card
    }

    createLinkPreviewNode = (url, result, isOwn) => {
        const preview = result?.preview || {}
        const targetUrl = preview.url || url
        const displayUrl = preview.displayUrl || this.shortenLinkLabel(targetUrl)
        const hasRich = this.hasRichMetadata(preview)

        if (!hasRich) {
            return this.createShortLinkFallbackNode(targetUrl, displayUrl, isOwn)
        }

        const card = document.createElement('a')
        card.href = targetUrl
        card.target = '_blank'
        card.rel = 'noopener noreferrer'
        card.className = 'd-block text-decoration-none rounded-3 border overflow-hidden mt-2'
        card.style.backgroundColor = isOwn ? 'rgba(13,110,253,0.08)' : 'var(--bs-tertiary-bg)'
        card.style.borderColor = isOwn ? 'rgba(13,110,253,0.35)' : 'var(--bs-border-color)'

        if (preview.image) {
            const image = document.createElement('img')
            image.src = preview.image
            image.alt = preview.title || preview.siteName || displayUrl
            image.className = 'w-100 d-block'
            image.style.maxHeight = '170px'
            image.style.objectFit = 'cover'
            image.style.backgroundColor = 'rgba(0,0,0,0.04)'
            card.appendChild(image)
        }

        const content = document.createElement('div')
        content.className = 'p-2'

        const title = document.createElement('div')
        title.style.fontWeight = '600'
        title.style.fontSize = '13px'
        title.style.color = isOwn ? '#ffffff' : '#0b1220'
        title.style.whiteSpace = 'nowrap'
        title.style.overflow = 'hidden'
        title.style.textOverflow = 'ellipsis'

        const description = document.createElement('div')
        description.style.fontSize = '12px'
        description.style.color = isOwn ? 'rgba(255,255,255,0.9)' : '#111827'
        description.style.display = '-webkit-box'
        description.style.webkitLineClamp = '2'
        description.style.webkitBoxOrient = 'vertical'
        description.style.overflow = 'hidden'
        description.style.marginTop = '2px'

        const footer = document.createElement('div')
        footer.style.fontSize = '11px'
        footer.style.color = isOwn ? 'rgba(255,255,255,0.85)' : '#334155'
        footer.style.marginTop = '6px'
        footer.textContent = displayUrl

        title.textContent = preview.title || preview.siteName || displayUrl
        description.textContent = preview.description || preview.siteName || displayUrl

        content.appendChild(title)
        content.appendChild(description)
        content.appendChild(footer)
        card.appendChild(content)
        return card
    }

    renderMessageLinkPreviews = async (urls, linkPreviewContainer, isOwn) => {
        if (!urls.length || !linkPreviewContainer) {
            return
        }

        const previews = await Promise.all(urls.map((url) => this.loadLinkPreview(url)))
        linkPreviewContainer.innerHTML = ''

        previews.forEach((previewResult, idx) => {
            const url = urls[idx]
            linkPreviewContainer.appendChild(this.createLinkPreviewNode(url, previewResult, isOwn))
        })

        linkPreviewContainer.classList.remove('d-none')
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
        const senderIdKey = message?.senderId !== null && message?.senderId !== undefined
            ? String(message.senderId)
            : ''
        const senderName = this.participantNicknameMap.get(senderIdKey) || message.senderName || 'Unknown User'
        const displayName = isOwn ? 'You.' : senderName
        const avatarLabel = isOwn ? this.currentUserName : senderName
        const avatarSrc = isOwn
            ? (message.senderAvatarSrc || this.currentUserAvatar || '')
            : (message.senderAvatarSrc || '')
        const timeLabel = message.timeLabel || '--'
        const body = message.body || ''
        const urlsInBody = this.extractMessageUrls(body)
        const bodyWithoutLinks = body.replace(/https?:\/\/[^\s<>"']+/gi, '').replace(/\s{2,}/g, ' ').trim()
        const attachments = Array.isArray(message.attachments) ? message.attachments : []
        const isAttachmentMessage = String(message.kind || '').toUpperCase() === 'ATTACHMENT'
        const fallbackText = body.trim().length > 0 ? body : 'Attachment'
        const bodyTextToRender = bodyWithoutLinks !== '' ? bodyWithoutLinks : (isAttachmentMessage ? fallbackText : '')
        const hasTextBody = bodyTextToRender.trim().length > 0 || isAttachmentMessage

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
        bodyElement.textContent = bodyTextToRender

        if (hasTextBody) {
            chatMessage.appendChild(bodyElement)
        }

        const attachmentContainer = document.createElement('div')
        attachmentContainer.className = 'd-grid gap-2 d-none'

        const linkPreviewContainer = document.createElement('div')
        linkPreviewContainer.className = 'd-grid gap-2 d-none'

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

        if (!isAttachmentMessage && urlsInBody.length > 0) {
            chatMessage.appendChild(linkPreviewContainer)
            queueMicrotask(() => {
                this.renderMessageLinkPreviews(urlsInBody, linkPreviewContainer, isOwn)
            })
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

    selectConversation = async (item) => {
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

        if (this.isGroupConversation(item)) {
            await this.loadConversationMembers(nextConversationId)
        } else {
            this.participantNicknameMap.clear()
            this.membersById.clear()
            if (this.membersList) {
                this.membersList.innerHTML = ''
            }
            if (this.membersAddButton) {
                this.membersAddButton.classList.add('d-none')
                this.membersAddButton.disabled = true
            }
            await this.loadConversationMembers(nextConversationId)
        }

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

    buildDMCandidatesEndpoint = (query = '') => {
        const q = String(query || '').trim()
        if (!q) {
            return '/apps-chat/direct-messages/candidates'
        }

        return `/apps-chat/direct-messages/candidates?q=${encodeURIComponent(q)}`
    }

    buildCreateDMEndpoint = () => {
        return '/apps-chat/direct-messages/create'
    }

    loadDMCandidates = async (query = '') => {
        const dmCandidatesList = document.querySelector('[data-apps-chat="dm-candidates-list"]')
        const dmEmpty = document.querySelector('[data-apps-chat="dm-empty"]')
        const dmLoading = document.querySelector('[data-apps-chat="dm-loading"]')

        try {
            const response = await fetch(this.buildDMCandidatesEndpoint(query), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to load users.')
            }

            if (dmLoading) dmLoading.classList.add('d-none')

            const candidates = Array.isArray(payload.candidates) ? payload.candidates : []
            if (dmCandidatesList) {
                dmCandidatesList.innerHTML = ''

                candidates.forEach((candidate) => {
                    const row = document.createElement('div')
                    row.className = 'd-flex align-items-center gap-2 border rounded-3 px-2 py-2 cursor-pointer hover-shadow'
                    row.style.cursor = 'pointer'
                    row.style.transition = 'all 0.2s ease'
                    row.setAttribute('data-dm-user-id', String(candidate.userId || ''))

                    const avatar = this.createAvatarElement(candidate.avatarSrc || '', candidate.name || 'M')
                    row.appendChild(avatar)

                    const body = document.createElement('div')
                    body.className = 'flex-grow-1 min-w-0'
                    body.innerHTML = `<div class="fw-medium text-truncate">${candidate.name || 'Unknown User'}</div><div class="text-muted small">${candidate.role || 'Member'}</div>`
                    row.appendChild(body)

                    row.addEventListener('click', () => {
                        this.createDirectMessage(candidate.userId)
                    })

                    dmCandidatesList.appendChild(row)
                })
            }

            if (dmEmpty) {
                dmEmpty.classList.toggle('d-none', candidates.length > 0)
            }
        } catch (error) {
            if (dmLoading) dmLoading.classList.add('d-none')
            if (dmEmpty) dmEmpty.classList.remove('d-none')
            this.showBottomNotice(error?.message || 'Failed to load users.')
        }
    }

    createDirectMessage = async (userId) => {
        const userIdNum = parseInt(userId, 10)
        if (!Number.isInteger(userIdNum) || userIdNum <= 0) {
            this.showBottomNotice('Invalid user id.')
            return
        }

        // Disable all buttons while processing
        const dmCandidatesList = document.querySelector('[data-apps-chat="dm-candidates-list"]')
        const allButtons = dmCandidatesList?.querySelectorAll('[data-dm-user-id]') || []
        allButtons.forEach(btn => btn.setAttribute('disabled', 'disabled'))

        try {
            const response = await fetch(this.buildCreateDMEndpoint(), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ userId: String(userIdNum) }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok) {
                throw new Error(payload.error || `Failed to create direct message (${response.status}).`)
            }

            if (!payload.success) {
                throw new Error(payload.error || 'Failed to create direct message.')
            }

            const conversationId = payload.conversation?.id
            if (!conversationId) {
                throw new Error('No conversation ID returned.')
            }

            // Close the modal
            const dmModal = document.getElementById('dmCreateModal')
            if (dmModal && window.bootstrap?.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(dmModal)
                if (modalInstance) {
                    modalInstance.hide()
                }
            }

            // Try to find and select the conversation, or reload
            queueMicrotask(async () => {
                const conversationItem = document.querySelector(`[data-apps-chat="conversation-item"][data-conversation-id="${conversationId}"]`)
                if (conversationItem) {
                    conversationItem.click()
                } else {
                    // Reload page after a short delay to ensure all events are processed
                    setTimeout(() => {
                        window.location.reload()
                    }, 200)
                }
            })
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to create direct message.')
            // Re-enable buttons on error
            allButtons.forEach(btn => btn.removeAttribute('disabled'))
        }
    }

    initDMModal = () => {
        const dmModal = document.getElementById('dmCreateModal')
        const dmSearchInput = document.querySelector('[data-apps-chat="dm-search-input"]')

        if (!dmModal) {
            return
        }

        // Load candidates when modal is shown
        dmModal.addEventListener('show.bs.modal', async () => {
            if (dmSearchInput) {
                dmSearchInput.value = ''
            }
            await this.loadDMCandidates('')
        })

        // Search functionality
        if (dmSearchInput) {
            dmSearchInput.addEventListener('input', async () => {
                await this.loadDMCandidates(dmSearchInput.value || '')
            })
        }
    }

    init = () => {
        this.cacheElements();
        this.setComposerEnabled(false);
        this.initDetailsDrawer();
        this.initCustomization();
        this.initDMModal();
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
