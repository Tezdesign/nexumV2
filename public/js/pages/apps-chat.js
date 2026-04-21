class ChatApp {

    constructor() {
        this.root = null
        this.messagesScrollWrapper = null
        this.messagesList = null
        this.messagesState = null
        this.conversationItems = []
        this.conversationItemsContainer = null
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
        this.messageActionsModal = null
        this.messageEditModal = null
        this.messageActionEditButton = null
        this.messageActionDeleteButton = null
        this.messageEditForm = null
        this.messageEditInput = null
        this.messageEditError = null
        this.messageEditSubmitButton = null
        this.messageActionTarget = null
        this.messageActionMode = 'TEXT'
        this.messageActionsMenu = null
        this.inlineEditNotice = null
        this.inlineEditCancelButton = null
        this.inlineEditMessageId = null
        this.inlineEditMessageNode = null
        this.groupCreateModal = null
        this.groupNameInput = null
        this.groupTitleError = null
        this.groupAvatarInput = null
        this.groupAvatarTrigger = null
        this.groupAvatarPreview = null
        this.groupAvatarClear = null
        this.groupSearchInput = null
        this.groupCandidatesList = null
        this.groupEmpty = null
        this.groupCreateSubmitButton = null
        this.selectedGroupMemberIds = new Set()
        this.editingMemberUserId = null
        this.membersById = new Map()
        this.participantNicknameMap = new Map()
        this.bottomNotice = null
        this.bottomNoticeTimer = null
        this.currentUserName = 'You'
        this.currentUserId = 0
        this.currentUserAvatar = ''
        this.callButtonAudio = null
        this.callButtonVideo = null
        this.stomp = null
        this.stompConnected = false
        this.stompConnecting = false
        this.stompConnectRequested = false
        this.stompReconnectTimer = null
        this.stompSubscriptions = []
        this.pendingIncomingCall = null
        this.pendingOutgoingCall = null
        this.callSocketUrl = 'ws://localhost:8090/ws'
        this.callTokenEndpoint = 'http://127.0.0.1:8090/livekit/token'
        this.callPageEndpoint = 'http://127.0.0.1:8090/livekit/call'
        this.callLivekitUrl = 'ws://127.0.0.1:7880'
        this.callSignalingEnabled = true
        this.messagesSimplebar = null
        this.chatForm = null
        this.chatInput = null
        this.chatSendButton = null
        this.chatSendDefaultHtml = ''
        this.voiceButton = null
        this.emojiButton = null
        this.emojiPickerInstance = null
        this.emojiPickerVisible = false
        this.emojiUseFallback = false
        this.emojiFallbackPicker = null
        this.emojiFallbackCloseButton = null
        this.emojiFallbackItems = []
        this.emojiTabs = []
        this.emojiSection = null
        this.gifSection = null
        this.emojiCdnHost = null
        this.emojiFallbackGrid = null
        this.activeEmojiTab = 'emoji'
        this.gifSearchInput = null
        this.gifClearButton = null
        this.gifResults = null
        this.gifStatus = null
        this.gifDebounceTimer = null
        this.tenorApiKey = ''
        this.gifApiProvider = 'kilpy'
        this.gifApiBaseUrl = 'https://api.klipy.com'
        this.gifCustomerId = 'guest'
        this.gifLocale = 'tn'
        this.voiceState = null
        this.voiceStateDot = null
        this.voiceStateLabel = null
        this.voiceStateTime = null
        this.voiceRecordingMode = false
        this.voiceRecordingActive = false
        this.voiceRecorder = null
        this.voiceStream = null
        this.voiceChunks = []
        this.voiceRecordedBlob = null
        this.voiceRecordedUrl = ''
        this.voicePreviewAudio = null
        this.voicePreviewPlaying = false
        this.voiceRecordingSeconds = 0
        this.voiceRecordingTimer = null
        this.voiceDiscardOnStop = false
        this.chatInputSelectionStart = 0
        this.chatInputSelectionEnd = 0
        this.attachmentButton = null
        this.attachmentInput = null
        this.activeFetchController = null
        this.activeAttachmentControllers = new Map()
        this.activeAudioElement = null
        this.linkPreviewCache = new Map()
        this.aiSummaryModal = null
        this.aiSummaryTitle = null
        this.aiSummaryText = null
        this.aiSummaryLoading = null
        this.aiSummarySeenAtUnread = new Map()
        this.aiSummaryUnreadThreshold = 10
        this.aiPendingConvId = null
        this.aiPendingUnreadCount = 0
        this.aiPendingTitle = 'Conversation'
        this.aiPendingMessages = []
        this.aiSummaryPendingByConv = new Set()
        this.aiSummaryPendingStorageKey = 'apps-chat-ai-summary-pending-v1'
    }

    cacheElements = () => {
        this.root = document.querySelector('[data-apps-chat="chat-root"]')
        if (this.root) {
            this.currentUserName = this.root.dataset.currentUserName || this.currentUserName
            const currentUserIdRaw = parseInt(this.root.dataset.currentUserId || '0', 10)
            const customerIdRaw = parseInt(this.root.dataset.gifCustomerId || '0', 10)
            this.currentUserId = Number.isInteger(currentUserIdRaw) && currentUserIdRaw > 0
                ? currentUserIdRaw
                : (Number.isInteger(customerIdRaw) && customerIdRaw > 0 ? customerIdRaw : 0)
            this.currentUserAvatar = this.root.dataset.currentUserAvatar || ''
            this.tenorApiKey = this.root.dataset.gifApiKey || this.tenorApiKey
            this.gifApiProvider = (this.root.dataset.gifApiProvider || this.gifApiProvider).toLowerCase()
            this.gifApiBaseUrl = (this.root.dataset.gifApiBaseUrl || this.gifApiBaseUrl).replace(/\/$/, '')
            this.gifCustomerId = this.root.dataset.gifCustomerId || this.gifCustomerId
            this.gifLocale = (this.root.dataset.gifLocale || this.gifLocale).toLowerCase()
            this.callSocketUrl = this.root.dataset.callSocketUrl || this.callSocketUrl
            this.callTokenEndpoint = this.root.dataset.callTokenEndpoint || this.callTokenEndpoint
            this.callPageEndpoint = this.root.dataset.callPageEndpoint || this.callPageEndpoint
            this.callLivekitUrl = this.root.dataset.callLivekitUrl || this.callLivekitUrl
            this.callSignalingEnabled = (this.root.dataset.callSignalingEnabled || '1') === '1'
            console.info('Call signaling config:', {
                currentUserId: this.currentUserId,
                callSocketUrl: this.callSocketUrl,
                enabled: this.callSignalingEnabled,
            })
        }

        this.messagesScrollWrapper = document.querySelector(
            '[data-apps-chat="messages-scroll-wrapper"]'
        )
        this.messagesList = document.querySelector('[data-apps-chat="messages-list"]')
        this.messagesState = document.querySelector('[data-apps-chat="messages-state"]')
        this.conversationItems = Array.from(
            document.querySelectorAll('[data-apps-chat="conversation-item"]')
        )
        this.conversationItemsContainer = document.querySelector('[data-apps-chat="conversation-items"]')
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
        this.callButtonAudio = document.querySelector('[data-apps-chat="call-audio"]')
        this.callButtonVideo = document.querySelector('[data-apps-chat="call-video"]')
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
        this.messageActionsModal = document.querySelector('[data-apps-chat="message-actions-modal"]')
        this.messageEditModal = document.querySelector('[data-apps-chat="message-edit-modal"]')
        this.messageActionEditButton = document.querySelector('[data-apps-chat="message-action-edit"]')
        this.messageActionDeleteButton = document.querySelector('[data-apps-chat="message-action-delete"]')
        this.messageEditForm = document.querySelector('[data-apps-chat="message-edit-form"]')
        this.messageEditInput = document.querySelector('[data-apps-chat="message-edit-input"]')
        this.messageEditError = document.querySelector('[data-apps-chat="message-edit-error"]')
        this.messageEditSubmitButton = document.querySelector('[data-apps-chat="message-edit-submit"]')
        this.aiSummaryModal = document.querySelector('[data-apps-chat="ai-summary-modal"]')
        this.aiSummaryTitle = document.querySelector('[data-apps-chat="ai-summary-title"]')
        this.aiSummaryText = document.querySelector('[data-apps-chat="ai-summary-text"]')
        this.aiSummaryLoading = document.querySelector('[data-apps-chat="ai-summary-loading"]')
        this.inlineEditNotice = document.querySelector('[data-apps-chat="inline-edit-notice"]')
        this.inlineEditCancelButton = document.querySelector('[data-apps-chat="inline-edit-cancel"]')
        this.groupCreateModal = document.getElementById('groupCreateModal')
        this.groupNameInput = document.querySelector('[data-apps-chat="group-name-input"]')
        this.groupTitleError = document.querySelector('[data-apps-chat="group-title-error"]')
        this.groupAvatarInput = document.querySelector('[data-apps-chat="group-avatar-input"]')
        this.groupAvatarTrigger = document.querySelector('[data-apps-chat="group-avatar-trigger"]')
        this.groupAvatarPreview = document.querySelector('[data-apps-chat="group-avatar-preview"]')
        this.groupAvatarClear = document.querySelector('[data-apps-chat="group-avatar-clear"]')
        this.groupSearchInput = document.querySelector('[data-apps-chat="group-search-input"]')
        this.groupCandidatesList = document.querySelector('[data-apps-chat="group-candidates-list"]')
        this.groupEmpty = document.querySelector('[data-apps-chat="group-empty"]')
        this.groupCreateSubmitButton = document.querySelector('[data-apps-chat="group-create-submit"]')
        this.chatForm = document.querySelector('#chat-form')
        if (this.chatForm) {
            this.chatInput = this.chatForm.querySelector('[data-apps-chat="chat-input"]')
            this.chatSendButton = this.chatForm.querySelector('[data-apps-chat="chat-send"]')
            this.chatSendDefaultHtml = this.chatSendButton?.innerHTML || ''
        }
        this.voiceButton = document.querySelector('[data-apps-chat="voice-button"]')
        this.emojiButton = document.querySelector('[data-apps-chat="emoji-button"]')
        this.emojiFallbackPicker = document.querySelector('[data-apps-chat="emoji-fallback-picker"]')
        this.emojiFallbackCloseButton = document.querySelector('[data-apps-chat="emoji-fallback-close"]')
        this.emojiFallbackItems = Array.from(document.querySelectorAll('[data-apps-chat="emoji-fallback-item"]'))
        this.emojiTabs = Array.from(document.querySelectorAll('[data-apps-chat="emoji-tab"]'))
        this.emojiSection = document.querySelector('[data-apps-chat="emoji-section"]')
        this.gifSection = document.querySelector('[data-apps-chat="gif-section"]')
        this.emojiCdnHost = document.querySelector('[data-apps-chat="emoji-cdn-host"]')
        this.emojiFallbackGrid = document.querySelector('[data-apps-chat="emoji-fallback-grid"]')
        this.gifSearchInput = document.querySelector('[data-apps-chat="gif-search"]')
        this.gifClearButton = document.querySelector('[data-apps-chat="gif-clear"]')
        this.gifResults = document.querySelector('[data-apps-chat="gif-results"]')
        this.gifStatus = document.querySelector('[data-apps-chat="gif-status"]')
        this.voiceState = document.querySelector('[data-apps-chat="voice-state"]')
        this.voiceStateDot = document.querySelector('[data-apps-chat="voice-state-dot"]')
        this.voiceStateLabel = document.querySelector('[data-apps-chat="voice-state-label"]')
        this.voiceStateTime = document.querySelector('[data-apps-chat="voice-state-time"]')
        this.attachmentButton = document.querySelector('[data-apps-chat="attachment-button"]')
        this.attachmentInput = document.querySelector('[data-apps-chat="attachment-input"]')
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

        if (this.emojiButton) {
            this.emojiButton.disabled = !enabled
        }

        if (this.voiceButton) {
            this.voiceButton.disabled = !enabled
        }

        if (!enabled) {
            this.closeEmojiPicker()
            this.cancelVoiceRecording({ resetComposer: false })
        }
    }

    rememberComposerSelection = () => {
        if (!this.chatInput) {
            return
        }

        const selectionStart = typeof this.chatInput.selectionStart === 'number'
            ? this.chatInput.selectionStart
            : this.chatInput.value.length
        const selectionEnd = typeof this.chatInput.selectionEnd === 'number'
            ? this.chatInput.selectionEnd
            : selectionStart

        this.chatInputSelectionStart = selectionStart
        this.chatInputSelectionEnd = selectionEnd
    }

    insertEmoji = (emoji) => {
        if (!this.chatInput || !emoji) {
            return
        }

        const value = String(this.chatInput.value || '')
        const start = typeof this.chatInput.selectionStart === 'number'
            ? this.chatInput.selectionStart
            : this.chatInputSelectionStart
        const end = typeof this.chatInput.selectionEnd === 'number'
            ? this.chatInput.selectionEnd
            : this.chatInputSelectionEnd

        const nextValue = `${value.slice(0, start)}${emoji}${value.slice(end)}`
        const nextCaret = start + String(emoji).length

        this.chatInput.value = nextValue
        this.chatInput.focus()

        if (typeof this.chatInput.setSelectionRange === 'function') {
            this.chatInput.setSelectionRange(nextCaret, nextCaret)
        }

        this.rememberComposerSelection()
        this.closeEmojiPicker()
    }

    closeEmojiPicker = () => {
        if (!this.emojiButton) {
            return
        }

        this.emojiFallbackPicker?.classList.add('d-none')
        this.emojiFallbackPicker?.setAttribute('aria-hidden', 'true')

        this.emojiPickerVisible = false
        this.emojiButton.setAttribute('aria-expanded', 'false')
    }

    toggleEmojiPicker = () => {
        if (!this.emojiButton || this.emojiButton.disabled) {
            return
        }

        if (!this.emojiFallbackPicker) {
            return
        }

        const isOpen = !this.emojiFallbackPicker.classList.contains('d-none')
        if (isOpen) {
            this.closeEmojiPicker()
            return
        }

        this.setEmojiPanelTab('emoji')
        this.emojiFallbackPicker.classList.remove('d-none')
        this.emojiFallbackPicker.setAttribute('aria-hidden', 'false')
        this.emojiPickerVisible = true
        this.emojiButton.setAttribute('aria-expanded', 'true')
    }

    formatVoiceDuration = (seconds) => {
        const totalSeconds = Math.max(0, Math.floor(Number(seconds) || 0))
        const minutes = Math.floor(totalSeconds / 60)
        const remainingSeconds = String(totalSeconds % 60).padStart(2, '0')
        return `${minutes}:${remainingSeconds}`
    }

    getPreferredVoiceMimeType = () => {
        if (!window.MediaRecorder?.isTypeSupported) {
            return ''
        }

        const candidates = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/ogg',
        ]

        return candidates.find((type) => window.MediaRecorder.isTypeSupported(type)) || ''
    }

    setVoiceComposerUi = () => {
        const voiceModeEnabled = this.voiceRecordingMode || this.voiceRecordedBlob !== null

        this.voiceState?.classList.toggle('d-none', !voiceModeEnabled)
        this.voiceState?.classList.toggle('recording', this.voiceRecordingActive)
        this.chatInput?.classList.toggle('d-none', voiceModeEnabled)
        
        // Show voice button always (for both normal state and voice recording control)
        if (this.voiceButton) {
            this.voiceButton.style.display = 'inline-flex'
        }
        
        // Hide attachment button whenever in voice mode (recording or hearing/preview)
        if (this.attachmentButton) {
            this.attachmentButton.style.display = voiceModeEnabled ? 'none' : 'inline-flex'
        }

        if (this.voiceStateDot) {
            this.voiceStateDot.classList.toggle('d-none', !this.voiceRecordingActive)
        }

        if (this.voiceStateLabel) {
            this.voiceStateLabel.textContent = this.getVoiceStateLabelText()
        }

        if (this.voiceStateTime) {
            this.voiceStateTime.textContent = this.getVoiceStateTimeText()
        }

        if (this.voiceButton) {
            this.voiceButton.setAttribute('aria-label', voiceModeEnabled ? 'Cancel voice recording' : 'Start voice recording')
            this.voiceButton.title = voiceModeEnabled ? 'Cancel voice recording' : 'Voice message'
            this.voiceButton.innerHTML = voiceModeEnabled
                ? '<i class="ti ti-x fs-20"></i>'
                : '<i class="ti ti-microphone fs-20"></i>'
        }

        if (this.emojiButton) {
            if (!voiceModeEnabled) {
                this.emojiButton.innerHTML = '<i class="ti ti-mood-smile fs-20"></i>'
                this.emojiButton.setAttribute('aria-label', 'Add emoji')
                this.emojiButton.title = 'Add emoji'
                return
            }

            if (this.voiceRecordingActive) {
                this.emojiButton.innerHTML = '<i class="ti ti-player-stop fs-20"></i>'
                this.emojiButton.setAttribute('aria-label', 'Stop recording')
                this.emojiButton.title = 'Stop recording'
            } else if (this.voiceRecordedBlob) {
                this.emojiButton.innerHTML = this.voicePreviewPlaying
                    ? '<i class="ti ti-player-pause fs-20"></i>'
                    : '<i class="ti ti-player-play fs-20"></i>'
                this.emojiButton.setAttribute('aria-label', this.voicePreviewPlaying ? 'Pause preview' : 'Play preview')
                this.emojiButton.title = this.voicePreviewPlaying ? 'Pause preview' : 'Play preview'
            }
        }

        if (this.chatSendButton) {
            this.chatSendButton.disabled = this.voiceRecordingActive || (voiceModeEnabled && !this.voiceRecordedBlob)
        }
    }

    clearVoiceRecordingTimer = () => {
        if (this.voiceRecordingTimer) {
            window.clearInterval(this.voiceRecordingTimer)
            this.voiceRecordingTimer = null
        }
    }

    stopVoicePreview = () => {
        if (this.voicePreviewAudio) {
            this.voicePreviewAudio.pause()
            this.voicePreviewAudio.currentTime = 0
        }

        this.voicePreviewPlaying = false
    }

    getVoiceStateLabelText = () => {
        if (this.voiceRecordingActive) {
            return 'Recording audio...'
        }

        if (this.voicePreviewPlaying) {
            return 'Playing audio...'
        }

        if (this.voiceRecordedBlob) {
            const duration = this.getVoicePreviewDurationSeconds()
            const remaining = this.getVoicePreviewRemainingSeconds()

            if (this.voicePreviewAudio && !this.voicePreviewAudio.paused && remaining > 0) {
                return `Playing • ${this.formatVoiceDuration(remaining)} left`
            }

            return `Tap to play • ${this.formatVoiceDuration(duration)}`
        }

        return 'Voice message'
    }

    getVoicePreviewDurationSeconds = () => {
        const duration = this.voicePreviewAudio?.duration
        if (Number.isFinite(duration) && duration > 0) {
            return duration
        }

        return this.voiceRecordingSeconds
    }

    getVoicePreviewRemainingSeconds = () => {
        const duration = this.getVoicePreviewDurationSeconds()
        const currentTime = this.voicePreviewAudio?.currentTime || 0
        return Math.max(0, duration - currentTime)
    }

    getVoiceStateTimeText = () => {
        if (this.voiceRecordingActive) {
            return this.formatVoiceDuration(this.voiceRecordingSeconds)
        }

        if (this.voiceRecordedBlob) {
            const remaining = this.getVoicePreviewRemainingSeconds()
            return `${this.formatVoiceDuration(remaining)} left`
        }

        return '00:00'
    }

    releaseVoiceRecordingResources = ({ discardRecording = false } = {}) => {
        if (discardRecording && this.voiceRecorder && this.voiceRecorder.state !== 'inactive') {
            this.voiceDiscardOnStop = true
        }

        this.clearVoiceRecordingTimer()

        if (this.voiceRecorder && this.voiceRecorder.state !== 'inactive') {
            try {
                this.voiceRecorder.stop()
            } catch {
                // ignore recorder shutdown races
            }
        }

        this.voiceRecorder = null

        if (this.voiceStream) {
            this.voiceStream.getTracks().forEach((track) => track.stop())
            this.voiceStream = null
        }
    }

    resetVoiceRecording = ({ resetComposer = true } = {}) => {
        this.stopVoicePreview()
        this.releaseVoiceRecordingResources()

        this.voiceRecordingMode = false
        this.voiceRecordingActive = false
        this.voiceChunks = []
        this.voiceRecordedBlob = null
        this.voiceRecordingSeconds = 0

        if (this.voiceRecordedUrl) {
            window.URL.revokeObjectURL(this.voiceRecordedUrl)
            this.voiceRecordedUrl = ''
        }

        if (resetComposer) {
            this.chatInput?.classList.remove('d-none')
            this.attachmentButton?.classList.remove('d-none')
            this.voiceState?.classList.add('d-none')
            if (this.voiceStateLabel) {
                this.voiceStateLabel.textContent = 'Voice message'
            }
            if (this.voiceStateTime) {
                this.voiceStateTime.textContent = '00:00'
            }
        }

        this.setVoiceComposerUi()
    }

    cancelVoiceRecording = ({ resetComposer = true } = {}) => {
        this.releaseVoiceRecordingResources({ discardRecording: true })

        this.voiceRecordingMode = false
        this.voiceRecordingActive = false
        this.voiceChunks = []
        this.voiceRecordedBlob = null
        this.voiceRecordingSeconds = 0

        if (this.voiceRecordedUrl) {
            window.URL.revokeObjectURL(this.voiceRecordedUrl)
            this.voiceRecordedUrl = ''
        }

        this.stopVoicePreview()

        if (resetComposer) {
            this.voiceState?.classList.add('d-none')
            this.chatInput?.classList.remove('d-none')
            this.attachmentButton?.classList.remove('d-none')
        }

        this.setVoiceComposerUi()
    }

    startVoiceRecording = async () => {
        if (this.voiceRecordingActive || this.voiceRecordedBlob) {
            return
        }

        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            this.showBottomNotice('Voice recording is not supported in this browser.')
            return
        }

        this.closeEmojiPicker()
        this.cancelVoiceRecording({ resetComposer: false })

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
            this.voiceStream = stream
            this.voiceChunks = []
            this.voiceRecordingMode = true
            this.voiceRecordingActive = true
            this.voiceRecordingSeconds = 0

            const mimeType = this.getPreferredVoiceMimeType()
            const recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream)

            this.voiceRecorder = recorder
            this.setVoiceComposerUi()

            this.voiceRecordingTimer = window.setInterval(() => {
                this.voiceRecordingSeconds += 1
                if (this.voiceStateTime) {
                    this.voiceStateTime.textContent = this.formatVoiceDuration(this.voiceRecordingSeconds)
                }
            }, 1000)

            recorder.addEventListener('dataavailable', (event) => {
                if (event.data && event.data.size > 0) {
                    this.voiceChunks.push(event.data)
                }
            })
            recorder.addEventListener('stop', () => {
                this.voiceRecordingActive = false
                this.clearVoiceRecordingTimer()
                if (this.voiceStream) {
                    this.voiceStream.getTracks().forEach((track) => track.stop())
                    this.voiceStream = null
                }

                if (this.voiceDiscardOnStop) {
                    this.voiceDiscardOnStop = false
                    this.voiceChunks = []
                    this.voiceRecordedBlob = null
                    this.voiceRecordingMode = false
                    this.voiceRecordingSeconds = 0

                    if (this.voiceRecordedUrl) {
                        window.URL.revokeObjectURL(this.voiceRecordedUrl)
                        this.voiceRecordedUrl = ''
                    }

                    this.stopVoicePreview()
                    this.setVoiceComposerUi()
                    return
                }

                if (this.voiceChunks.length === 0) {
                    this.resetVoiceRecording({ resetComposer: true })
                    return
                }

                const blobType = recorder.mimeType || mimeType || 'audio/webm'
                this.voiceRecordedBlob = new Blob(this.voiceChunks, { type: blobType })
                this.voiceChunks = []

                if (this.voiceRecordedUrl) {
                    window.URL.revokeObjectURL(this.voiceRecordedUrl)
                }

                this.voiceRecordedUrl = window.URL.createObjectURL(this.voiceRecordedBlob)
                if (!this.voicePreviewAudio) {
                    this.voicePreviewAudio = new Audio()
                }

                this.voicePreviewAudio.src = this.voiceRecordedUrl
                this.voicePreviewAudio.preload = 'metadata'
                this.voicePreviewAudio.onended = () => {
                    this.voicePreviewPlaying = false
                    this.setVoiceComposerUi()
                }

                this.voicePreviewPlaying = false
                this.voiceDiscardOnStop = false
                this.setVoiceComposerUi()
            })

            recorder.start()
            this.setVoiceComposerUi()
        } catch (error) {
            console.error('Voice recording failed:', error)
            this.showBottomNotice(error?.message || 'Could not start voice recording.')
            this.resetVoiceRecording({ resetComposer: true })
        }
    }

    stopVoiceRecording = () => {
        if (!this.voiceRecorder || this.voiceRecorder.state === 'inactive') {
            return
        }

        try {
            this.voiceRecorder.stop()
        } catch (error) {
            console.error('Voice stop failed:', error)
            this.showBottomNotice('Could not stop voice recording.')
        }
    }

    toggleVoicePreview = async () => {
        if (!this.voiceRecordedBlob || !this.voicePreviewAudio) {
            return
        }

        try {
            if (this.voicePreviewPlaying) {
                this.voicePreviewAudio.pause()
                this.voicePreviewPlaying = false
                this.setVoiceComposerUi()
                return
            }

            this.voicePreviewPlaying = true
            this.setVoiceComposerUi()
            await this.voicePreviewAudio.play()
        } catch (error) {
            console.error('Voice preview failed:', error)
            this.voicePreviewPlaying = false
            this.setVoiceComposerUi()
        }
    }

    uploadFilesAsAttachments = async (files) => {
        if (!this.activeConversationId || !Array.isArray(files) || files.length === 0) {
            return false
        }

        const formData = new FormData()
        formData.append('conversationId', String(this.activeConversationId))
        files.forEach((file) => {
            formData.append('files[]', file)
        })

        this.attachmentButton?.setAttribute('disabled', 'disabled')
        this.chatSendButton?.setAttribute('disabled', 'disabled')

        try {
            const response = await fetch(this.buildAttachmentUploadEndpoint(), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to upload file.')
            }

            const createdMessages = Array.isArray(payload.messages) ? payload.messages : []
            const createdAttachments = Array.isArray(payload.attachments) ? payload.attachments : []
            if (createdAttachments.length > 0) {
                const attachmentsByMessageId = new Map()
                createdAttachments.forEach((attachment) => {
                    const key = String(attachment?.messageId || '')
                    if (!key) {
                        return
                    }

                    if (!attachmentsByMessageId.has(key)) {
                        attachmentsByMessageId.set(key, [])
                    }

                    attachmentsByMessageId.get(key).push(attachment)
                })

                createdMessages.forEach((message) => {
                    const key = String(message?.id || '')
                    if (!key) {
                        return
                    }

                    const attachments = attachmentsByMessageId.get(key)
                    if (attachments && attachments.length > 0) {
                        message.attachments = attachments
                    }
                })
            }

            if (createdMessages.length > 0) {
                this.setMessagesState('', false)
            }

            createdMessages.forEach((message, index) => {
                this.messagesList?.appendChild(this.createMessageNode(message, Date.now() + index))
            })

            const latestMessage = createdMessages[createdMessages.length - 1]
            if (latestMessage) {
                this.syncConversationItemLastMessage(this.activeConversationItem, latestMessage)
            }

            this.scrollToBottom(true)
            return true
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to upload file.')
            return false
        } finally {
            this.attachmentButton?.removeAttribute('disabled')
            this.chatSendButton?.removeAttribute('disabled')
        }
    }

    buildVoiceRecordingFile = () => {
        if (!this.voiceRecordedBlob) {
            return null
        }

        const mimeType = this.voiceRecordedBlob.type || 'audio/webm'
        const extension = mimeType.includes('ogg') ? 'ogg' : 'webm'
        const fileName = `voice-message-${Date.now()}.${extension}`
        return new File([this.voiceRecordedBlob], fileName, { type: mimeType })
    }

    sendVoiceRecording = async () => {
        if (!this.voiceRecordedBlob) {
            return
        }

        const voiceFile = this.buildVoiceRecordingFile()
        if (!voiceFile) {
            return
        }

        this.voicePreviewAudio?.pause()
        this.voicePreviewPlaying = false

        const success = await this.uploadFilesAsAttachments([voiceFile])
        if (success) {
            this.resetVoiceRecording({ resetComposer: true })
        }
    }

    initEmojiPicker = () => {
        if (!this.emojiButton) {
            return
        }

        this.initGifPicker()

        if (!this.emojiCdnHost) {
            this.emojiUseFallback = true
            return
        }

        import('https://cdn.jsdelivr.net/npm/emoji-picker-element@1.29.1/index.js')
            .then(() => {
                if (window.customElements?.get('emoji-picker')) {
                    const pickerElement = document.createElement('emoji-picker')
                    pickerElement.classList.add('light')
                    pickerElement.setAttribute('style', 'width:100%;height:290px;--border-size:0;')
                    pickerElement.addEventListener('emoji-click', (event) => {
                        this.insertEmoji(event?.detail?.unicode || '')
                    })

                    this.emojiCdnHost.innerHTML = ''
                    this.emojiCdnHost.appendChild(pickerElement)
                    this.emojiFallbackGrid?.classList.add('d-none')
                    this.emojiUseFallback = false
                    return
                }

                throw new Error('emoji-picker custom element not registered.')
            })
            .catch((error) => {
                console.warn('Emoji CDN module failed to load. Falling back to local picker.', error)
                this.emojiUseFallback = true
                this.emojiFallbackGrid?.classList.remove('d-none')
            })
    }

    setEmojiPanelTab = (tab) => {
        const nextTab = tab === 'gif' ? 'gif' : 'emoji'
        this.activeEmojiTab = nextTab

        this.emojiTabs.forEach((button) => {
            const isActive = button.dataset.emojiTab === nextTab
            button.classList.toggle('active', isActive)
            button.setAttribute('aria-selected', String(isActive))
        })

        this.emojiSection?.classList.toggle('d-none', nextTab !== 'emoji')
        this.gifSection?.classList.toggle('d-none', nextTab !== 'gif')

        if (nextTab === 'gif') {
            this.loadGifResults(this.gifSearchInput?.value || '')
        }
    }

    buildTenorSearchEndpoint = (query) => {
        const q = String(query || '').trim()
        if (this.gifApiProvider === 'kilpy') {
            const customerId = String(this.gifCustomerId || 'guest')
            const locale = String(this.gifLocale || 'tn')
            return `${this.gifApiBaseUrl}/api/v1/${encodeURIComponent(this.tenorApiKey)}/gifs/search?q=${encodeURIComponent(q)}&limit=24&media_filter=nanogif,tinygif,gif&customer_id=${encodeURIComponent(customerId)}&locale=${encodeURIComponent(locale)}`
        }

        if (this.gifApiProvider !== 'tenor') {
            return `https://api.giphy.com/v1/gifs/search?api_key=${encodeURIComponent(this.tenorApiKey)}&q=${encodeURIComponent(q)}&limit=20&rating=pg&lang=en`
        }

        return `https://tenor.googleapis.com/v2/search?key=${encodeURIComponent(this.tenorApiKey)}&q=${encodeURIComponent(q)}&limit=20&media_filter=gif&contentfilter=low`
    }

    buildTenorFeaturedEndpoint = () => {
        if (this.gifApiProvider === 'kilpy') {
            const customerId = String(this.gifCustomerId || 'guest')
            const locale = String(this.gifLocale || 'tn')
            return `${this.gifApiBaseUrl}/api/v1/${encodeURIComponent(this.tenorApiKey)}/gifs/trending?limit=24&media_filter=nanogif,tinygif,gif&customer_id=${encodeURIComponent(customerId)}&locale=${encodeURIComponent(locale)}`
        }

        if (this.gifApiProvider !== 'tenor') {
            return `https://api.giphy.com/v1/gifs/trending?api_key=${encodeURIComponent(this.tenorApiKey)}&limit=20&rating=pg`
        }

        return `https://tenor.googleapis.com/v2/featured?key=${encodeURIComponent(this.tenorApiKey)}&limit=20&media_filter=gif&contentfilter=low`
    }

    extractTenorGifUrl = (gifItem) => {
        if (this.gifApiProvider === 'kilpy') {
            return (
                gifItem?.media?.tinygif?.url ||
                gifItem?.media?.gif?.url ||
                gifItem?.media_formats?.tinygif?.url ||
                gifItem?.media_formats?.gif?.url ||
                gifItem?.tinygif?.url ||
                gifItem?.gif?.url ||
                gifItem?.media?.preview?.url ||
                gifItem?.media?.thumbnail?.url ||
                ''
            )
        }

        if (this.gifApiProvider !== 'tenor') {
            const images = gifItem?.images || {}
            return (
                images?.fixed_width?.url ||
                images?.downsized?.url ||
                images?.original?.url ||
                ''
            )
        }

        const formats = gifItem?.media_formats || {}
        return (
            formats?.gif?.url ||
            formats?.mediumgif?.url ||
            formats?.tinygif?.url ||
            ''
        )
    }

    renderGifResults = (gifItems) => {
        if (!this.gifResults) {
            return
        }

        this.gifResults.innerHTML = ''

        if (!Array.isArray(gifItems) || gifItems.length === 0) {
            const empty = document.createElement('div')
            empty.className = 'text-muted small'
            empty.textContent = 'No GIFs found.'
            this.gifResults.appendChild(empty)
            return
        }

        gifItems.forEach((gifItem) => {
            const gifUrl = this.extractTenorGifUrl(gifItem)
            if (!gifUrl) {
                return
            }

            const description = gifItem?.content_description || gifItem?.description || gifItem?.title || ''

            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'chat-gif-item'
            button.setAttribute('aria-label', `Send GIF ${description}`.trim())

            const image = document.createElement('img')
            image.src = gifUrl
            image.loading = 'lazy'
            image.alt = description || 'GIF'

            button.appendChild(image)
            button.addEventListener('click', async () => {
                await this.sendGifMessage(gifUrl)
            })

            this.gifResults.appendChild(button)
        })
    }

    collectKlipyGifItems = (node, out = []) => {
        if (Array.isArray(node)) {
            node.forEach((entry) => this.collectKlipyGifItems(entry, out))
            return out
        }

        if (!node || typeof node !== 'object') {
            return out
        }

        const hasGifUrl = Boolean(
            node?.media?.tinygif?.url ||
            node?.media?.gif?.url ||
            node?.media_formats?.tinygif?.url ||
            node?.media_formats?.gif?.url ||
            node?.tinygif?.url ||
            node?.gif?.url
        )

        if (hasGifUrl) {
            out.push(node)
        }

        Object.values(node).forEach((value) => {
            this.collectKlipyGifItems(value, out)
        })

        return out
    }

    parseKlipyGifResults = (payload) => {
        if (payload?.success && Array.isArray(payload?.data)) {
            return payload.data
        }

        if (Array.isArray(payload?.data)) {
            return payload.data
        }

        if (Array.isArray(payload?.results)) {
            return payload.results
        }

        return this.collectKlipyGifItems(payload, [])
    }

    loadGifResults = async (query = '') => {
        if (!this.gifResults) {
            return
        }

        if (!String(this.tenorApiKey || '').trim()) {
            this.gifResults.innerHTML = ''
            const missingKey = document.createElement('div')
            missingKey.className = 'text-muted small'
            missingKey.textContent = 'GIF API key is missing. Set KILPY or KLIPY_API_KEY in your environment.'
            this.gifResults.appendChild(missingKey)
            this.gifStatus && (this.gifStatus.textContent = 'GIF API unavailable')
            return
        }

        const q = String(query || '').trim()
        const endpoint = q.length > 0 ? this.buildTenorSearchEndpoint(q) : this.buildTenorFeaturedEndpoint()

        this.gifStatus && (this.gifStatus.textContent = q.length > 0 ? `Results for "${q}"` : 'Trending GIFs')

        try {
            const response = await fetch(endpoint, { method: 'GET' })
            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    throw new Error('GIF API key was rejected. Check KILPY.')
                }

                if (response.status === 429) {
                    throw new Error('GIF API rate limit reached. Try again later.')
                }

                throw new Error(`Failed to load GIFs (${response.status}).`)
            }

            const payload = await response.json().catch(() => ({}))
            const results = this.gifApiProvider === 'kilpy'
                ? this.parseKlipyGifResults(payload)
                : this.gifApiProvider !== 'tenor'
                ? (Array.isArray(payload?.data) ? payload.data : [])
                : (Array.isArray(payload?.results) ? payload.results : [])
            this.renderGifResults(results)
        } catch (error) {
            console.error('GIF load failed:', error)
            this.gifResults.innerHTML = ''
            const failed = document.createElement('div')
            failed.className = 'text-muted small'
            failed.textContent = error?.message || 'Could not load GIFs right now.'
            this.gifResults.appendChild(failed)
            if (this.gifStatus) {
                this.gifStatus.textContent = error?.message || 'GIF load failed'
            }
        }
    }

    sendGifMessage = async (gifUrl) => {
        const cleanUrl = String(gifUrl || '').trim()
        if (!cleanUrl || !this.activeConversationId) {
            return
        }

        this.chatSendButton?.setAttribute('disabled', 'disabled')

        try {
            const response = await fetch(this.buildGifUploadEndpoint(), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({
                    conversationId: String(this.activeConversationId),
                    gifUrl: cleanUrl,
                }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to send GIF.')
            }

            const message = payload.message || null
            if (!message) {
                throw new Error('Failed to send GIF.')
            }

            const attachments = Array.isArray(payload.attachments) ? payload.attachments : []
            if (attachments.length > 0) {
                message.attachments = attachments
            }

            this.setMessagesState('', false)
            this.messagesList?.appendChild(this.createMessageNode(message, Date.now()))
            this.syncConversationItemLastMessage(this.activeConversationItem, message)
            this.scrollToBottom(true)
            this.closeEmojiPicker()
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to send GIF.')
        } finally {
            this.chatSendButton?.removeAttribute('disabled')
        }
    }

    initGifPicker = () => {
        this.emojiTabs.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault()
                this.setEmojiPanelTab(button.dataset.emojiTab || 'emoji')
            })
        })

        this.gifSearchInput?.addEventListener('input', () => {
            if (this.gifDebounceTimer) {
                window.clearTimeout(this.gifDebounceTimer)
            }

            this.gifDebounceTimer = window.setTimeout(() => {
                this.loadGifResults(this.gifSearchInput?.value || '')
            }, 220)
        })

        this.gifClearButton?.addEventListener('click', (event) => {
            event.preventDefault()
            if (this.gifSearchInput) {
                this.gifSearchInput.value = ''
            }
            this.loadGifResults('')
        })
    }

    isDirectGifUrl = (value) => {
        const text = String(value || '').trim()
        return /^https?:\/\/.+\.(gif)(\?.*)?$/i.test(text)
    }

    isDirectImageUrl = (value) => {
        const text = String(value || '').trim()
        return /^https?:\/\/.+\.(gif|png|jpe?g|webp)(\?.*)?$/i.test(text)
    }

    createInlineMediaNode = (url) => {
        const wrapper = document.createElement('div')
        wrapper.className = 'mt-2'

        const link = document.createElement('a')
        link.href = url
        link.target = '_blank'
        link.rel = 'noopener'

        const image = document.createElement('img')
        image.src = url
        image.alt = 'GIF'
        image.className = 'img-fluid rounded-3 border'
        image.loading = 'lazy'

        link.appendChild(image)
        wrapper.appendChild(link)
        return wrapper
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

    promoteConversationItem = (item) => {
        if (!item) {
            return
        }

        const targetContainer = this.conversationItemsContainer || item.parentElement
        if (!targetContainer) {
            return
        }

        targetContainer.prepend(item)
        this.conversationItems = [item, ...this.conversationItems.filter((conversationItem) => conversationItem !== item)]
    }

    syncConversationItemLastMessage = (item, message) => {
        if (!item || !message) {
            return
        }

        const bodyText = String(message.body || '').trim()
        const previewText = this.isDirectImageUrl(bodyText) ? 'GIF' : bodyText
        const timeLabel = String(message.timeLabel || '--')

        const previewNode = item.querySelector('[data-apps-chat="conversation-last-preview"]')
        if (previewNode) {
            previewNode.textContent = previewText !== '' ? previewText : 'No messages yet'
        }

        const timeNode = item.querySelector('[data-apps-chat="conversation-last-time"]')
        if (timeNode) {
            timeNode.textContent = timeLabel
        }

        this.promoteConversationItem(item)
        item.dataset.conversationCreatedAt = item.dataset.conversationCreatedAt || ''
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
            this.bottomNotice.style.zIndex = '2000'

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

    buildConversationMessageStoreEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/messages`
    }

    buildAttachmentUploadEndpoint = () => {
        return '/apps-chat/attachments'
    }

    buildGifUploadEndpoint = () => {
        return '/apps-chat/gifs'
    }

    buildMessageEditEndpoint = (messageId) => {
        return `/apps-chat/messages/${encodeURIComponent(String(messageId))}/edit`
    }

    buildMessageDeleteEndpoint = (messageId) => {
        return `/apps-chat/messages/${encodeURIComponent(String(messageId))}/delete`
    }

    setMessageEditError = (message = '') => {
        if (!this.messageEditError) {
            return
        }

        const text = String(message || '').trim()
        this.messageEditError.textContent = text
        this.messageEditError.classList.toggle('d-none', text.length === 0)
    }

    ensureInlineEditNotice = () => {
        if (this.inlineEditNotice || !this.chatForm || !this.chatInput) {
            return
        }

        const notice = document.createElement('div')
        notice.className = 'd-none align-items-center justify-content-between gap-2 px-3 py-2 mb-1 rounded-3'
        notice.setAttribute('data-apps-chat', 'inline-edit-notice')

        const label = document.createElement('span')
        label.setAttribute('data-apps-chat', 'inline-edit-title')
        label.textContent = 'Edit message'

        const cancelButton = document.createElement('button')
        cancelButton.type = 'button'
        cancelButton.className = 'btn btn-sm p-0'
        cancelButton.setAttribute('data-apps-chat', 'inline-edit-cancel')
        cancelButton.setAttribute('aria-label', 'Cancel edit')
        cancelButton.innerHTML = '&times;'
        cancelButton.addEventListener('click', () => {
            this.clearInlineEditMode({ resetInput: true })
        })

        notice.appendChild(label)
        notice.appendChild(cancelButton)

        const inputColumn = this.chatInput.closest('[data-apps-chat="chat-input-column"]')
        if (inputColumn) {
            inputColumn.insertBefore(notice, this.chatInput)
        } else {
            this.chatInput.parentElement?.insertBefore(notice, this.chatInput)
        }

        this.inlineEditNotice = notice
        this.inlineEditCancelButton = cancelButton
    }

    startInlineEditMode = (messageNode) => {
        if (!messageNode || !this.chatInput) {
            return
        }

        const messageId = String(messageNode.dataset.messageId || '')
        if (!messageId) {
            return
        }

        const bodyNode = messageNode.querySelector('[data-message-body]')
        const currentText = String(bodyNode?.textContent || '').trim()

        this.ensureInlineEditNotice()
        this.inlineEditMessageId = messageId
        this.inlineEditMessageNode = messageNode
        this.inlineEditNotice?.classList.remove('d-none')
        this.inlineEditNotice?.classList.add('d-flex')
        this.chatForm?.classList.add('chat-form-editing')
        if (this.chatSendButton) {
            this.chatSendButton.innerHTML = '<i class="ti ti-check"></i>'
        }
        this.chatInput.value = currentText
        this.chatInput.focus()
        this.chatInput.select()
    }

    clearInlineEditMode = ({ resetInput = false } = {}) => {
        this.inlineEditMessageId = null
        this.inlineEditMessageNode = null
        this.inlineEditNotice?.classList.add('d-none')
        this.inlineEditNotice?.classList.remove('d-flex')
        this.chatForm?.classList.remove('chat-form-editing')
        if (this.chatSendButton && this.chatSendDefaultHtml) {
            this.chatSendButton.innerHTML = this.chatSendDefaultHtml
        }

        if (resetInput && this.chatInput) {
            this.chatInput.value = ''
        }
    }

    ensureMessageActionsMenu = () => {
        if (this.messageActionsMenu) {
            return
        }

        const menu = document.createElement('div')
        menu.className = 'position-fixed bg-white border rounded-3 shadow-sm p-1'
        menu.style.display = 'none'
        menu.style.zIndex = '2100'
        menu.style.minWidth = '150px'
        menu.style.maxWidth = '220px'

        const editButton = document.createElement('button')
        editButton.type = 'button'
        editButton.className = 'btn btn-sm w-100 text-start'
        editButton.textContent = 'Edit'

        const deleteButton = document.createElement('button')
        deleteButton.type = 'button'
        deleteButton.className = 'btn btn-sm w-100 text-start text-danger'
        deleteButton.textContent = 'Delete'

        menu.appendChild(editButton)
        menu.appendChild(deleteButton)
        document.body.appendChild(menu)

        this.messageActionsMenu = menu
        this.messageActionEditButton = editButton
        this.messageActionDeleteButton = deleteButton

        editButton.addEventListener('click', () => {
            this.closeMessageActions()
            this.openMessageEditModal()
        })

        deleteButton.addEventListener('click', async () => {
            this.closeMessageActions()
            await this.deleteSelectedMessage()
        })

        document.addEventListener('click', (event) => {
            if (!this.messageActionsMenu || this.messageActionsMenu.style.display === 'none') {
                return
            }

            const target = event.target
            if (target instanceof Node && this.messageActionsMenu.contains(target)) {
                return
            }

            this.closeMessageActions()
        })

        window.addEventListener('resize', () => {
            this.closeMessageActions()
        })

        this.messagesScrollWrapper?.addEventListener('scroll', () => {
            this.closeMessageActions()
        })
    }

    openMessageActions = (targetMessageNode, cursorX, cursorY) => {
        if (!targetMessageNode) {
            return
        }

        this.ensureMessageActionsMenu()
        if (!this.messageActionsMenu) {
            return
        }

        this.messageActionTarget = targetMessageNode
        this.messageActionMode = String(targetMessageNode.dataset.messageKind || 'TEXT').toUpperCase()

        if (this.messageActionEditButton) {
            this.messageActionEditButton.classList.toggle('d-none', this.messageActionMode !== 'TEXT')
        }

        this.messageActionsMenu.style.visibility = 'hidden'
        this.messageActionsMenu.style.display = 'block'

        const menuRect = this.messageActionsMenu.getBoundingClientRect()
        const viewportWidth = window.innerWidth
        const viewportHeight = window.innerHeight
        const margin = 8

        let left = Number.isFinite(cursorX) ? cursorX : margin
        let top = Number.isFinite(cursorY) ? cursorY : margin

        if (left + menuRect.width + margin > viewportWidth) {
            left = viewportWidth - menuRect.width - margin
        }

        if (top + menuRect.height + margin > viewportHeight) {
            top = viewportHeight - menuRect.height - margin
        }

        left = Math.max(margin, left)
        top = Math.max(margin, top)

        this.messageActionsMenu.style.left = `${left}px`
        this.messageActionsMenu.style.top = `${top}px`
        this.messageActionsMenu.style.visibility = 'visible'
    }

    closeMessageActions = () => {
        if (!this.messageActionsMenu) {
            return
        }

        this.messageActionsMenu.style.display = 'none'
    }

    openMessageEditModal = () => {
        if (!this.messageActionTarget || this.messageActionMode !== 'TEXT') {
            return
        }

        this.startInlineEditMode(this.messageActionTarget)
    }

    closeMessageEditModal = () => {
        this.clearInlineEditMode({ resetInput: false })
    }

    applyEditedBadge = (messageNode, isEdited) => {
        if (!messageNode) {
            return
        }

        let badge = messageNode.querySelector('[data-message-edited-badge]')
        if (!isEdited) {
            if (badge) {
                badge.remove()
            }
            return
        }

        if (!badge) {
            badge = document.createElement('span')
            badge.className = 'text-muted ms-1'
            badge.style.fontSize = '11px'
            badge.setAttribute('data-message-edited-badge', '1')
            badge.textContent = '(edited)'
            const titleWrapper = messageNode.querySelector('[data-message-meta]')
            if (titleWrapper) {
                titleWrapper.appendChild(badge)
            }
        }
    }

    editSelectedMessage = async () => {
        if (!this.inlineEditMessageNode || !this.inlineEditMessageId || !this.chatInput) {
            return
        }

        const messageId = this.inlineEditMessageId
        if (!messageId) {
            return
        }

        const body = String(this.chatInput.value || '')
        this.chatSendButton?.setAttribute('disabled', 'disabled')
        this.attachmentButton?.setAttribute('disabled', 'disabled')

        try {
            const response = await fetch(this.buildMessageEditEndpoint(messageId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ body }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to edit message.')
            }

            const bodyNode = this.inlineEditMessageNode.querySelector('[data-message-body]')
            if (bodyNode) {
                bodyNode.textContent = String(payload.message?.body || '')
            }

            this.applyEditedBadge(this.inlineEditMessageNode, !!payload.message?.isEdited)

            const lastMessageNode = this.messagesList?.lastElementChild
            if (lastMessageNode === this.inlineEditMessageNode) {
                this.syncConversationItemLastMessage(this.activeConversationItem, {
                    body: String(payload.message?.body || ''),
                    timeLabel: String(payload.message?.timeLabel || '--'),
                })
            }

            this.clearInlineEditMode({ resetInput: true })
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to edit message.')
        } finally {
            this.chatSendButton?.removeAttribute('disabled')
            this.attachmentButton?.removeAttribute('disabled')
        }
    }

    deleteSelectedMessage = async () => {
        if (!this.messageActionTarget) {
            return
        }

        const messageId = this.messageActionTarget.dataset.messageId || ''
        if (!messageId) {
            return
        }

        const confirmed = await this.openDangerConfirmModal('Delete this message?')
        if (!confirmed) {
            return
        }

        try {
            const response = await fetch(this.buildMessageDeleteEndpoint(messageId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to delete message.')
            }

            if (String(this.inlineEditMessageId || '') === String(messageId)) {
                this.clearInlineEditMode({ resetInput: true })
            }

            this.messageActionTarget.remove()
            this.messageActionTarget = null
            await this.loadConversationMessages(this.activeConversationId, this.buildMessagesEndpoint(this.activeConversationItem, this.activeConversationId))
            this.closeMessageActions()
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to delete message.')
        }
    }

    uploadSelectedAttachments = async () => {
        if (!this.activeConversationId || !this.attachmentInput?.files?.length) {
            return
        }

        const files = Array.from(this.attachmentInput.files)
        await this.uploadFilesAsAttachments(files)

        if (this.attachmentInput) {
            this.attachmentInput.value = ''
        }
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
        this.cancelVoiceRecording({ resetComposer: true })
        this.activeConversationItem = null
        this.activeConversationId = null

        this.conversationItems.forEach((conversationItem) => {
            conversationItem.classList.remove('active')
            conversationItem.setAttribute('aria-current', 'false')
            conversationItem.tabIndex = -1
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

    buildMessagesEndpoint = (item, conversationId, { markAsRead = false } = {}) => {
        const itemEndpoint = item?.dataset.messagesEndpoint || ''
        const baseEndpoint = itemEndpoint || `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/messages`

        if (!markAsRead) {
            return baseEndpoint
        }

        const separator = baseEndpoint.includes('?') ? '&' : '?'
        return `${baseEndpoint}${separator}markAsRead=1`
    }

    buildAiSummaryEndpoint = (conversationId) => {
        return `/apps-chat/conversations/${encodeURIComponent(String(conversationId))}/ai-summary`
    }

    lastN = (items, n) => {
        if (!Array.isArray(items) || items.length === 0 || n <= 0) {
            return []
        }

        const from = Math.max(0, items.length - n)
        return items.slice(from)
    }

    getConversationUnreadCount = (conversationItem) => {
        if (!conversationItem) {
            return 0
        }

        const rawCount = parseInt(String(conversationItem.dataset.conversationUnreadCount || '0'), 10)
        if (Number.isFinite(rawCount) && rawCount > 0) {
            return rawCount
        }

        const badge = conversationItem.querySelector('[data-apps-chat="conversation-unread-badge"]')
        if (!badge) {
            return 0
        }

        const badgeText = String(badge.textContent || '').trim()
        if (badgeText === '99+') {
            return 99
        }

        const parsed = parseInt(badgeText, 10)
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0
    }

    removeAiSummaryChip = () => {
        this.messagesList?.querySelector('[data-apps-chat="ai-summary-chip-wrap"]')?.remove()
    }

    loadAiSummaryPendingState = () => {
        try {
            const raw = window.localStorage.getItem(this.aiSummaryPendingStorageKey)
            if (!raw) {
                this.aiSummaryPendingByConv = new Set()
                return
            }

            const parsed = JSON.parse(raw)
            if (!Array.isArray(parsed)) {
                this.aiSummaryPendingByConv = new Set()
                return
            }

            this.aiSummaryPendingByConv = new Set(
                parsed
                    .map((value) => String(value || '').trim())
                    .filter((value) => value !== '')
            )
        } catch {
            this.aiSummaryPendingByConv = new Set()
        }
    }

    saveAiSummaryPendingState = () => {
        try {
            const values = Array.from(this.aiSummaryPendingByConv)
            window.localStorage.setItem(this.aiSummaryPendingStorageKey, JSON.stringify(values))
        } catch {
            // Ignore storage errors to avoid breaking chat UI behavior.
        }
    }

    addAiSummaryChip = (onClick) => {
        if (!this.messagesList) {
            return
        }

        this.removeAiSummaryChip()

        const item = document.createElement('li')
        item.className = 'chat-ai-chip-wrap'
        item.setAttribute('data-apps-chat', 'ai-summary-chip-wrap')

        const chip = document.createElement('button')
        chip.type = 'button'
        chip.className = 'chat-ai-chip'
        chip.textContent = 'AI SUMMARY'

        chip.addEventListener('click', (event) => {
            event.preventDefault()
            event.stopPropagation()
            if (typeof onClick === 'function') {
                onClick()
            }
        })

        item.appendChild(chip)
        this.messagesList.appendChild(item)
        this.scrollToBottom(true)
    }

    openAiSummaryFrom = (title, messages, conversationId, unreadCount) => {
        this.aiPendingTitle = String(title || '').trim() || 'Conversation'
        this.aiPendingMessages = this.lastN(Array.isArray(messages) ? messages : [], 10)
        this.aiPendingConvId = conversationId
        this.aiPendingUnreadCount = unreadCount

        this.openAiSummary()
    }

    openAiSummary = async () => {
        if (!this.aiSummaryModal || !this.aiSummaryText || !this.aiPendingConvId) {
            return
        }

        const convIdSnapshot = this.aiPendingConvId
        const unreadSnapshot = this.aiPendingUnreadCount
        const titleSnapshot = this.sanitizeAiSummaryTitleInput(this.aiPendingTitle || 'Conversation')
        const convKey = String(convIdSnapshot)
        let summarySucceeded = false

        this.aiSummaryTitle && (this.aiSummaryTitle.textContent = 'AI Summary')
        this.aiSummaryText.textContent = ''
        this.aiSummaryLoading?.classList.remove('d-none')
        this.getBootstrapModal(this.aiSummaryModal)?.show()
        this.aiSummaryPendingByConv.add(convKey)
        this.saveAiSummaryPendingState()

        try {
            const response = await fetch(this.buildAiSummaryEndpoint(convIdSnapshot), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    title: titleSnapshot,
                }),
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Summary is unavailable right now.')
            }

            if (String(this.activeConversationId || '') !== String(convIdSnapshot)) {
                return
            }

            this.aiSummaryText.textContent = String(payload.summary || '').trim() || 'Summary unavailable.'
            summarySucceeded = true
        } catch (error) {
            this.aiSummaryText.textContent = error?.message || 'AI summary is unavailable (LM Studio is unreachable).'
        } finally {
            this.aiSummaryLoading?.classList.add('d-none')

            if (summarySucceeded) {
                this.aiSummarySeenAtUnread.set(convKey, unreadSnapshot)
                this.aiSummaryPendingByConv.delete(convKey)
                this.saveAiSummaryPendingState()
                this.removeAiSummaryChip()
            } else {
                this.aiSummaryPendingByConv.add(convKey)
                this.saveAiSummaryPendingState()
            }
        }
    }

    sanitizeAiSummaryTitleInput = (value) => {
        if (value === null || value === undefined) {
            return 'Conversation'
        }

        const cleaned = String(value)
            .replace(/[\u0000-\u001F\u007F]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()

        if (!cleaned) {
            return 'Conversation'
        }

        return cleaned.length > 120 ? cleaned.slice(0, 120) : cleaned
    }

    maybeShowAiSummaryChip = (conversationId, unreadBefore, conversationTitle, messages) => {
        if (!conversationId) {
            this.removeAiSummaryChip()
            return
        }

        const convKey = String(conversationId)
        const hasPending = this.aiSummaryPendingByConv.has(convKey)
        const seenAt = this.aiSummarySeenAtUnread.get(convKey) || 0
        const passesUnreadGate = unreadBefore >= this.aiSummaryUnreadThreshold && unreadBefore > seenAt

        if (!hasPending && !passesUnreadGate) {
            this.removeAiSummaryChip()
            return
        }

        const contextMessages = this.lastN(Array.isArray(messages) ? messages : [], 10)
        if (contextMessages.length === 0) {
            this.aiSummaryPendingByConv.delete(convKey)
            this.saveAiSummaryPendingState()
            this.removeAiSummaryChip()
            return
        }

        this.aiPendingConvId = conversationId
        this.aiPendingUnreadCount = unreadBefore
        this.aiPendingTitle = String(conversationTitle || '').trim() || 'Conversation'
        this.aiPendingMessages = contextMessages

        this.addAiSummaryChip(() => {
            this.openAiSummaryFrom(this.aiPendingTitle, contextMessages, conversationId, unreadBefore)
        })
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
        const extension = this.getAttachmentExtension(fileName)
        if (mimeType.startsWith('image/')) {
            return 'image'
        }

        if (this.isVoiceRecordingAttachment(attachment, fileName, mimeType)) {
            return 'audio'
        }

        if (mimeType.startsWith('video/')) {
            return this.isPreviewableVideoExtension(extension) ? 'video' : 'file'
        }

        if (mimeType.startsWith('audio/')) {
            return 'audio'
        }

        if (this.isPreviewableVideoExtension(extension)) {
            return 'video'
        }

        if (fileName.endsWith('.mp3') || fileName.endsWith('.wav') || fileName.endsWith('.ogg') || fileName.endsWith('.m4a') || fileName.endsWith('.aac') || fileName.endsWith('.flac') || fileName.endsWith('.webm')) {
            return 'audio'
        }

        return 'file'
    }

    isVoiceRecordingAttachment = (attachment, fileName = '', mimeType = '') => {
        const safeFileName = String(fileName || attachment?.fileName || '').toLowerCase()
        const safeMimeType = String(mimeType || attachment?.mimeType || '').toLowerCase()

        if (safeMimeType.startsWith('audio/')) {
            return true
        }

        return safeFileName.startsWith('voice-message-') || safeFileName.startsWith('voice-recording-')
    }

    getAttachmentExtension = (fileName = '') => {
        const safeFileName = String(fileName || '').toLowerCase()
        const parts = safeFileName.split('.')
        return parts.length > 1 ? parts[parts.length - 1] : ''
    }

    isPreviewableVideoExtension = (extension) => {
        return ['mp4', 'm4v', 'webm', 'ogv', 'ogg'].includes(String(extension || '').toLowerCase())
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

            video.addEventListener('error', () => {
                const fallback = document.createElement('a')
                fallback.href = url
                fallback.target = '_blank'
                fallback.rel = 'noopener'
                fallback.className = 'd-inline-flex align-items-center gap-2 text-decoration-none border rounded-3 px-3 py-2 bg-body-tertiary text-body'

                const icon = document.createElement('span')
                icon.textContent = '▶'
                icon.className = 'fw-semibold'

                const text = document.createElement('span')
                text.textContent = `${fileName} (open video)`

                fallback.appendChild(icon)
                fallback.appendChild(text)

                wrapper.replaceChildren(fallback)
            })

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
            waveformRow.style.touchAction = 'none'

            const waveformSeed = String(attachment?.id || fileName || url)
            const waveformBars = []
            const barCount = 36
            const baseBarHeights = []

            for (let barIndex = 0; barIndex < barCount; barIndex += 1) {
                const bar = document.createElement('span')
                const seedChar = waveformSeed.charCodeAt(barIndex % waveformSeed.length) || (barIndex + 17)
                const normalizedHeightPx = 16 + ((seedChar + barIndex * 17) % 26)
                baseBarHeights.push(normalizedHeightPx)

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

            const progressFill = document.createElement('span')
            progressFill.style.position = 'absolute'
            progressFill.style.inset = '0 auto 0 0'
            progressFill.style.width = '0%'
            progressFill.style.borderRadius = '999px'
            progressFill.style.background = 'linear-gradient(90deg, rgba(13, 110, 253, 0.28), rgba(13, 110, 253, 0.12))'
            progressFill.style.pointerEvents = 'none'

            const hoverIndicator = document.createElement('span')
            hoverIndicator.style.position = 'absolute'
            hoverIndicator.style.top = '0'
            hoverIndicator.style.bottom = '0'
            hoverIndicator.style.width = '2px'
            hoverIndicator.style.left = '0%'
            hoverIndicator.style.borderRadius = '999px'
            hoverIndicator.style.backgroundColor = 'rgba(13, 110, 253, 0.35)'
            hoverIndicator.style.opacity = '0'
            hoverIndicator.style.pointerEvents = 'none'

            const hoverTimeChip = document.createElement('span')
            hoverTimeChip.style.position = 'absolute'
            hoverTimeChip.style.top = '50%'
            hoverTimeChip.style.transform = 'translate(-50%, -170%)'
            hoverTimeChip.style.padding = '2px 6px'
            hoverTimeChip.style.borderRadius = '999px'
            hoverTimeChip.style.fontSize = '10px'
            hoverTimeChip.style.fontWeight = '600'
            hoverTimeChip.style.color = 'var(--bs-body-color)'
            hoverTimeChip.style.backgroundColor = 'var(--bs-body-bg)'
            hoverTimeChip.style.border = '1px solid var(--bs-border-color)'
            hoverTimeChip.style.boxShadow = '0 4px 14px rgba(15, 23, 42, 0.12)'
            hoverTimeChip.style.opacity = '0'
            hoverTimeChip.style.pointerEvents = 'none'
            hoverTimeChip.textContent = '0:00'

            waveformRow.appendChild(progressFill)
            waveformRow.appendChild(playhead)
            waveformRow.appendChild(hoverIndicator)
            waveformRow.appendChild(hoverTimeChip)

            let waveformFrameId = null
            let isWaveformDragging = false
            let lastPointerProgress = 0

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

            const renderWaveform = (animated = false, previewProgress = null) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    playhead.style.left = '0%'
                    progressFill.style.width = '0%'
                    hoverIndicator.style.opacity = '0'
                    hoverTimeChip.style.opacity = '0'
                    currentTimeText.textContent = '0:00'
                    return
                }

                const progress = Math.min(100, Math.max(0, previewProgress ?? ((audio.currentTime / audio.duration) * 100)))
                const activeBarCount = Math.max(1, Math.round((progress / 100) * waveformBars.length))
                const playbackPhase = audio.currentTime * 6.5
                const currentBarIndex = Math.max(0, Math.min(waveformBars.length - 1, Math.round((progress / 100) * (waveformBars.length - 1))))

                waveformBars.forEach((bar, index) => {
                    const isActive = index < activeBarCount
                    const isCurrent = index === activeBarCount - 1
                    const distanceFromPlayhead = Math.abs(index - activeBarCount + 1)
                    const proximity = Math.max(0, 1 - (distanceFromPlayhead / 6))
                    const pulse = animated ? (Math.sin(playbackPhase + index * 0.55) + 1) / 2 : 0
                    const heightScale = isActive
                        ? 0.94 + (proximity * 0.28) + (pulse * 0.22)
                        : 0.72 + (pulse * 0.08)

                    const hoverBoost = Math.max(0, 1 - (Math.abs(index - currentBarIndex) / 4))
                    bar.style.backgroundColor = isActive ? waveActiveColor : waveIdleColor
                    bar.style.opacity = isActive ? String(0.80 + (proximity * 0.20)) : String(0.42 + (hoverBoost * 0.12))
                    bar.style.transform = `scaleY(${isCurrent && !audio.paused ? Math.max(1.22, heightScale) : heightScale})`
                })

                playhead.style.left = `${progress}%`
                progressFill.style.width = `${progress}%`
                currentTimeText.textContent = formatTime(audio.currentTime)

                if (isWaveformDragging) {
                    hoverIndicator.style.opacity = '1'
                    hoverIndicator.style.left = `${progress}%`
                    hoverTimeChip.style.opacity = '1'
                    hoverTimeChip.style.left = `${progress}%`
                    hoverTimeChip.textContent = formatTime((progress / 100) * audio.duration)
                } else if (animated && !audio.paused) {
                    hoverIndicator.style.opacity = '0'
                    hoverTimeChip.style.opacity = '0'
                }
            }

            const stopWaveformAnimation = () => {
                if (waveformFrameId !== null) {
                    window.cancelAnimationFrame(waveformFrameId)
                    waveformFrameId = null
                }
            }

            const startWaveformAnimation = () => {
                stopWaveformAnimation()

                const tick = () => {
                    if (audio.paused || audio.ended) {
                        waveformFrameId = null
                        renderWaveform(false)
                        return
                    }

                    renderWaveform(true)
                    waveformFrameId = window.requestAnimationFrame(tick)
                }

                waveformFrameId = window.requestAnimationFrame(tick)
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

            const getPointerProgress = (event) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return 0
                }

                const rect = waveformRow.getBoundingClientRect()
                const offset = Math.min(Math.max(0, event.clientX - rect.left), rect.width)
                return rect.width > 0 ? offset / rect.width : 0
            }

            const seekToProgress = (progress) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                const safeProgress = Math.min(1, Math.max(0, progress))
                audio.currentTime = safeProgress * audio.duration
                renderWaveform(false, safeProgress * 100)
            }

            const updateHoverState = (event, commit = false) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                const progress = getPointerProgress(event)
                lastPointerProgress = progress
                renderWaveform(false, progress * 100)

                hoverIndicator.style.opacity = '1'
                hoverIndicator.style.left = `${progress * 100}%`
                hoverTimeChip.style.opacity = '1'
                hoverTimeChip.style.left = `${progress * 100}%`
                hoverTimeChip.textContent = formatTime(progress * audio.duration)

                if (commit) {
                    seekToProgress(progress)
                }
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

            waveformRow.addEventListener('pointerdown', (event) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                isWaveformDragging = true
                waveformRow.setPointerCapture?.(event.pointerId)
                updateHoverState(event, true)
            })

            waveformRow.addEventListener('pointermove', (event) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                if (!isWaveformDragging && event.buttons !== 1) {
                    const progress = getPointerProgress(event)
                    hoverIndicator.style.opacity = '1'
                    hoverIndicator.style.left = `${progress * 100}%`
                    hoverTimeChip.style.opacity = '1'
                    hoverTimeChip.style.left = `${progress * 100}%`
                    hoverTimeChip.textContent = formatTime(progress * audio.duration)
                    renderWaveform(audio && !audio.paused, progress * 100)
                    return
                }

                updateHoverState(event, true)
            })

            waveformRow.addEventListener('pointerup', (event) => {
                if (!audio.duration || Number.isNaN(audio.duration)) {
                    return
                }

                updateHoverState(event, true)
                isWaveformDragging = false
                waveformRow.releasePointerCapture?.(event.pointerId)
                if (!audio.paused) {
                    startWaveformAnimation()
                }
            })

            waveformRow.addEventListener('pointerleave', () => {
                if (isWaveformDragging) {
                    return
                }

                hoverIndicator.style.opacity = '0'
                hoverTimeChip.style.opacity = '0'
                renderWaveform(!audio.paused)
            })

            audio.addEventListener('play', () => {
                updatePlayState()
                startWaveformAnimation()
            })
            audio.addEventListener('playing', pauseOtherAudio)
            audio.addEventListener('pause', () => {
                updatePlayState()
                stopWaveformAnimation()
                renderWaveform(false)
                if (this.activeAudioElement === audio) {
                    this.activeAudioElement = null
                }
            })
            audio.addEventListener('ended', () => {
                audio.currentTime = 0
                updatePlayState()
                stopWaveformAnimation()
                renderWaveform(false)
                playhead.style.left = '0%'
                currentTimeText.textContent = '0:00'
                if (this.activeAudioElement === audio) {
                    this.activeAudioElement = null
                }
            })
            audio.addEventListener('timeupdate', () => renderWaveform(false))
            audio.addEventListener('loadedmetadata', updateDuration)
            audio.addEventListener('error', () => {
                stopWaveformAnimation()
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
            renderWaveform(false)
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
        const trimmedBody = String(body || '').trim()
        const isInlineImageMessage = this.isDirectImageUrl(trimmedBody)
        const urlsInBody = this.extractMessageUrls(body)
        const bodyWithoutLinks = body.replace(/https?:\/\/[^\s<>"']+/gi, '').replace(/\s{2,}/g, ' ').trim()
        const attachments = Array.isArray(message.attachments) ? message.attachments : []
        const isAttachmentMessage = String(message.kind || '').toUpperCase() === 'ATTACHMENT'
        const fallbackText = body.trim().length > 0 ? body : 'Attachment'
        const bodyTextToRender = isInlineImageMessage
            ? ''
            : bodyWithoutLinks !== '' ? bodyWithoutLinks : (isAttachmentMessage ? fallbackText : '')
        const hasTextBody = bodyTextToRender.trim().length > 0 || isAttachmentMessage

        const listItem = document.createElement('li')
        listItem.className = `chat-group${isOwn ? ' odd' : ''}`
        listItem.id = `message-${message.id || index}`
        listItem.dataset.messageId = String(message.id || '')
        listItem.dataset.messageKind = String(message.kind || 'TEXT').toUpperCase()
        listItem.dataset.messageOwn = isOwn ? '1' : '0'

        const avatar = this.createAvatarElement(avatarSrc, avatarLabel)
        listItem.appendChild(avatar)

        const chatBody = document.createElement('div')
        chatBody.className = 'chat-body'

        const titleWrapper = document.createElement('div')
        titleWrapper.setAttribute('data-message-meta', '1')
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
        bodyElement.setAttribute('data-message-body', '1')
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
            if (attachments.length > 0) {
                attachmentContainer.classList.remove('d-none')
                attachments.forEach((attachment, attachmentIndex) => {
                    attachmentContainer.appendChild(this.createAttachmentNode(attachment, attachmentIndex))
                })

                if (bodyElement) {
                    bodyElement.classList.add('d-none')
                }
            } else {
                queueMicrotask(() => {
                    this.loadMessageAttachments(message.id, attachmentContainer, bodyElement)
                })
            }
        } else if (attachments.length > 0) {
            attachmentContainer.classList.remove('d-none')
            attachments.forEach((attachment, attachmentIndex) => {
                attachmentContainer.appendChild(this.createAttachmentNode(attachment, attachmentIndex))
            })
            chatMessage.appendChild(attachmentContainer)
        }

        if (!isAttachmentMessage && !isInlineImageMessage && urlsInBody.length > 0) {
            chatMessage.appendChild(linkPreviewContainer)
            queueMicrotask(() => {
                this.renderMessageLinkPreviews(urlsInBody, linkPreviewContainer, isOwn)
            })
        }

        if (!isAttachmentMessage && isInlineImageMessage) {
            chatMessage.appendChild(this.createInlineMediaNode(trimmedBody))
        }

        this.applyEditedBadge(listItem, !!message.isEdited || !!message.editedAt)

        chatBody.appendChild(titleWrapper)
        chatBody.appendChild(chatMessage)
        listItem.appendChild(chatBody)

        return listItem
    }

    createReadReceiptCircle = (receipt) => {
        const wrapper = document.createElement('span')
        wrapper.className = 'd-inline-flex align-items-center justify-content-center rounded-circle border border-light bg-secondary-subtle text-secondary fw-semibold'
        wrapper.style.width = '18px'
        wrapper.style.height = '18px'
        wrapper.style.fontSize = '10px'
        wrapper.style.lineHeight = '18px'
        wrapper.title = receipt?.userName || 'Seen'

        const avatarSrc = receipt?.userAvatarSrc || ''
        if (avatarSrc) {
            const image = document.createElement('img')
            image.src = avatarSrc
            image.alt = receipt?.userName || 'Seen'
            image.className = 'rounded-circle'
            image.style.width = '16px'
            image.style.height = '16px'
            image.style.objectFit = 'cover'
            wrapper.appendChild(image)
            return wrapper
        }

        wrapper.textContent = (receipt?.userInitial || '?').slice(0, 1).toUpperCase()
        return wrapper
    }

    renderReadReceipts = (messages, readReceipts) => {
        if (!Array.isArray(messages) || !Array.isArray(readReceipts) || !this.messagesList) {
            return
        }

        const existingRows = this.messagesList.querySelectorAll('[data-apps-chat="read-receipts-row"]')
        existingRows.forEach((row) => row.remove())

        const renderedMessageIds = new Set(
            messages
                .map((message) => String(message?.id || ''))
                .filter((id) => id !== '')
        )

        const receiptsByMessageId = new Map()
        readReceipts.forEach((receipt) => {
            const messageId = String(receipt?.lastReadMessageId || '')
            if (!messageId || !renderedMessageIds.has(messageId)) {
                return
            }

            const current = receiptsByMessageId.get(messageId) || []
            current.push(receipt)
            receiptsByMessageId.set(messageId, current)
        })

        receiptsByMessageId.forEach((receipts, messageId) => {
            const messageNode = this.messagesList.querySelector(`[data-message-id="${messageId}"]`)
            if (!messageNode) {
                return
            }

            const chatBody = messageNode.querySelector('.chat-body')
            if (!chatBody) {
                return
            }

            const row = document.createElement('div')
            row.className = 'd-flex align-items-center flex-wrap gap-1 mt-1'
            row.setAttribute('data-apps-chat', 'read-receipts-row')
            row.style.marginInlineStart = '2px'

            receipts.forEach((receipt) => {
                row.appendChild(this.createReadReceiptCircle(receipt))
            })

            chatBody.appendChild(row)
        })
    }

    clearConversationUnreadState = (conversationItem) => {
        if (!conversationItem) {
            return
        }

        conversationItem.dataset.conversationUnread = '0'
        conversationItem.dataset.conversationUnreadCount = '0'
        const badge = conversationItem.querySelector('[data-apps-chat="conversation-unread-badge"]')
        if (badge) {
            badge.remove()
        }
    }

    renderMessages = (messages, readReceipts = []) => {
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

        this.renderReadReceipts(messages, readReceipts)

        this.scrollToBottom()
    }

    loadConversationMessages = async (conversationId, endpoint, options = {}) => {
        if (!endpoint) {
            return
        }

        const unreadBefore = Number.isFinite(options?.unreadBefore)
            ? Math.max(0, options.unreadBefore)
            : 0
        const conversationTitle = String(options?.conversationTitle || '').trim() || 'Conversation'

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

            const messages = Array.isArray(payload.messages) ? payload.messages : []
            const readReceipts = Array.isArray(payload.readReceipts) ? payload.readReceipts : []
            const conversationState = payload.conversationState || {}

            const serverUnreadBefore = Number.parseInt(String(conversationState.unreadBeforeRead ?? ''), 10)
            const unreadSnapshot = Number.isFinite(serverUnreadBefore) && serverUnreadBefore >= 0
                ? serverUnreadBefore
                : unreadBefore

            const lastReadBefore = Number.parseInt(String(conversationState.lastReadMessageIdBeforeRead ?? '0'), 10)
            const lastConversationMessageId = Number.parseInt(String(conversationState.lastConversationMessageId ?? '0'), 10)
            const hasUnreadByMessageId = Number.isFinite(lastConversationMessageId)
                && Number.isFinite(lastReadBefore)
                && lastConversationMessageId > lastReadBefore

            this.renderMessages(
                messages,
                readReceipts
            )

            if (String(this.activeConversationId || '') === String(conversationId)) {
                const effectiveUnread = hasUnreadByMessageId ? unreadSnapshot : 0
                this.maybeShowAiSummaryChip(conversationId, effectiveUnread, conversationTitle, messages)
            }
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

        this.cancelVoiceRecording({ resetComposer: true })

        if (this.inlineEditMessageId) {
            this.clearInlineEditMode({ resetInput: true })
        }

        const rawConversationId = item.dataset.conversationId || ''
        const nextConversationId = rawConversationId !== '' ? rawConversationId : null
        const endpoint = this.buildMessagesEndpoint(item, rawConversationId, { markAsRead: true })
        const unreadBefore = this.getConversationUnreadCount(item)
        const conversationTitle = String(item.dataset.conversationName || item.dataset.groupTitle || item.dataset.dmName || '').trim() || 'Conversation'

        if (!endpoint) {
            return
        }

        if (this.activeConversationItem === item && this.activeConversationId === nextConversationId) {
            return
        }

        this.conversationItems.forEach((conversationItem) => {
            const isActive = conversationItem === item
            conversationItem.classList.toggle('active', isActive)
            conversationItem.setAttribute('aria-current', isActive ? 'true' : 'false')
            conversationItem.tabIndex = isActive ? 0 : -1
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

        this.clearConversationUnreadState(item)
        await this.loadConversationMessages(nextConversationId, endpoint, {
            unreadBefore,
            conversationTitle,
        })
    }

    initConversationSelection = () => {
        this.conversationItems.forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault()
                this.selectConversation(item)
            })
        })
    }

    initForm = () => {
        this.chatForm?.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!this.activeConversationId || !this.chatInput) {
                return
            }

            if (this.voiceRecordingActive) {
                this.stopVoiceRecording()
                return
            }

            if (this.voiceRecordedBlob) {
                this.sendVoiceRecording()
                return
            }

            if (this.inlineEditMessageId) {
                this.editSelectedMessage()
                return
            }

            const body = String(this.chatInput.value || '').trim()
            if (body === '') {
                this.showBottomNotice('Please fill out this field.')
                this.chatInput.focus()
                return
            }

            this.chatSendButton?.setAttribute('disabled', 'disabled')

            fetch(this.buildConversationMessageStoreEndpoint(this.activeConversationId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({ body }),
            })
                .then(async (response) => {
                    const payload = await response.json().catch(() => ({}))
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.error || 'Failed to send message.')
                    }

                    const message = payload.message || null
                    if (!message) {
                        throw new Error('Failed to send message.')
                    }

                    this.setMessagesState('', false)
                    this.messagesList?.appendChild(this.createMessageNode(message, Date.now()))
                    this.syncConversationItemLastMessage(this.activeConversationItem, message)
                    this.chatInput.value = ''
                    this.closeEmojiPicker()
                    this.scrollToBottom(true)
                })
                .catch((error) => {
                    this.showBottomNotice(error?.message || 'Failed to send message.')
                })
                .finally(() => {
                    this.chatSendButton?.removeAttribute('disabled')
                })
        })

        this.messagesList?.addEventListener('contextmenu', (event) => {
            const target = event.target
            if (target instanceof HTMLElement && target.closest('a, button, input, textarea, video, audio')) {
                return
            }

            const messageNode = target instanceof HTMLElement ? target.closest('li.chat-group') : null
            if (!messageNode) {
                return
            }

            if ((messageNode.dataset.messageOwn || '0') !== '1') {
                return
            }

            event.preventDefault()
            this.openMessageActions(messageNode, event.clientX, event.clientY)
        })

        this.messageEditForm?.addEventListener('submit', async (event) => {
            event.preventDefault()
            await this.editSelectedMessage()
        })

        this.messageEditModal?.querySelector('[data-apps-chat="message-edit-close"]')?.addEventListener('click', () => {
            this.closeMessageEditModal()
        })

        this.messageEditModal?.querySelector('[data-apps-chat="message-edit-cancel"]')?.addEventListener('click', () => {
            this.closeMessageEditModal()
        })

        this.messageEditInput?.addEventListener('input', () => {
            this.setMessageEditError('')
        })

        this.chatInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.inlineEditMessageId) {
                event.preventDefault()
                this.clearInlineEditMode({ resetInput: false })
                return
            }

            if (event.key === 'Escape' && this.emojiPickerVisible) {
                event.preventDefault()
                this.closeEmojiPicker()
            }
        })

        this.chatInput?.addEventListener('focus', this.rememberComposerSelection)
        this.chatInput?.addEventListener('click', this.rememberComposerSelection)
        this.chatInput?.addEventListener('keyup', this.rememberComposerSelection)
        this.chatInput?.addEventListener('mouseup', this.rememberComposerSelection)
        this.chatInput?.addEventListener('select', this.rememberComposerSelection)
        this.chatInput?.addEventListener('input', this.rememberComposerSelection)

        this.emojiButton?.addEventListener('mousedown', (event) => {
            event.preventDefault()
            this.rememberComposerSelection()
        })

        this.emojiButton?.addEventListener('click', (event) => {
            event.preventDefault()
            if (!this.activeConversationId) {
                return
            }

            if (this.voiceRecordingMode) {
                if (this.voiceRecordingActive) {
                    this.stopVoiceRecording()
                } else if (this.voiceRecordedBlob) {
                    this.toggleVoicePreview()
                } else {
                    this.startVoiceRecording()
                }

                return
            }

            this.toggleEmojiPicker()
        })

        this.voiceButton?.addEventListener('click', async (event) => {
            event.preventDefault()

            if (!this.activeConversationId) {
                return
            }

            if (this.voiceRecordingMode) {
                this.cancelVoiceRecording({ resetComposer: true })
                return
            }

            await this.startVoiceRecording()
        })

        this.emojiFallbackCloseButton?.addEventListener('click', (event) => {
            event.preventDefault()
            this.closeEmojiPicker()
        })

        this.emojiFallbackItems.forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault()
                const emoji = item.dataset.emoji || item.textContent || ''
                this.insertEmoji(emoji)
            })
        })

        document.addEventListener('click', (event) => {
            if (!this.emojiPickerVisible) {
                return
            }

            const target = event.target instanceof HTMLElement ? event.target : null
            if (!target) {
                this.closeEmojiPicker()
                return
            }

            if (target.closest('[data-apps-chat="emoji-fallback-picker"]') || target.closest('[data-apps-chat="emoji-button"]')) {
                return
            }

            this.closeEmojiPicker()
        })

        this.inlineEditCancelButton?.addEventListener('click', () => {
            this.clearInlineEditMode({ resetInput: true })
        })

        this.aiSummaryModal?.addEventListener('hidden.bs.modal', () => {
            this.aiSummaryLoading?.classList.add('d-none')
        })

        this.attachmentButton?.addEventListener('click', (event) => {
            event.preventDefault()
            if (!this.activeConversationId) {
                return
            }

            this.attachmentInput?.click()
        })

        this.attachmentInput?.addEventListener('change', async () => {
            await this.uploadSelectedAttachments()
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

    buildGroupCandidatesEndpoint = (query = '') => {
        const q = String(query || '').trim()
        if (!q) {
            return '/apps-chat/groups/candidates'
        }

        return `/apps-chat/groups/candidates?q=${encodeURIComponent(q)}`
    }

    buildCreateGroupEndpoint = () => {
        return '/apps-chat/groups/create'
    }

    setGroupTitleError = (message = '') => {
        if (!this.groupTitleError) {
            return
        }

        const text = String(message || '').trim()
        this.groupTitleError.textContent = text
        this.groupTitleError.classList.toggle('d-none', text.length === 0)
    }

    clearGroupAvatar = () => {
        if (this.groupAvatarInput) {
            this.groupAvatarInput.value = ''
        }

        if (this.groupAvatarPreview) {
            this.groupAvatarPreview.removeAttribute('src')
        }

        this.groupAvatarTrigger?.classList.remove('has-image')
    }

    updateGroupAvatarPreview = () => {
        const file = this.groupAvatarInput?.files?.[0]
        if (!file || !this.groupAvatarPreview) {
            this.clearGroupAvatar()
            return
        }

        const reader = new FileReader()
        reader.onload = () => {
            this.groupAvatarPreview.src = String(reader.result || '')
            this.groupAvatarTrigger?.classList.add('has-image')
        }
        reader.readAsDataURL(file)
    }

    renderGroupCandidates = (candidates) => {
        if (!this.groupCandidatesList) {
            return
        }

        this.groupCandidatesList.innerHTML = ''
        candidates.forEach((candidate) => {
            const userId = String(candidate.userId || '')

            const row = document.createElement('label')
            row.className = 'd-flex align-items-center gap-2 border rounded-3 px-2 py-2'

            const checkbox = document.createElement('input')
            checkbox.type = 'checkbox'
            checkbox.className = 'form-check-input mt-0'
            checkbox.value = userId
            checkbox.checked = this.selectedGroupMemberIds.has(userId)
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    this.selectedGroupMemberIds.add(userId)
                } else {
                    this.selectedGroupMemberIds.delete(userId)
                }
            })
            row.appendChild(checkbox)

            const avatar = this.createAvatarElement(candidate.avatarSrc || '', candidate.name || 'M')
            row.appendChild(avatar)

            const body = document.createElement('div')
            body.className = 'flex-grow-1 min-w-0'
            body.innerHTML = `<div class="fw-medium text-truncate">${candidate.name || 'Unknown User'}</div><div class="text-muted small">${candidate.role || 'Member'}</div>`
            row.appendChild(body)

            this.groupCandidatesList.appendChild(row)
        })

        if (this.groupEmpty) {
            this.groupEmpty.classList.toggle('d-none', candidates.length > 0)
        }
    }

    loadGroupCandidates = async (query = '') => {
        const response = await fetch(this.buildGroupCandidatesEndpoint(query), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
        })

        const payload = await response.json().catch(() => ({}))
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to load users.')
        }

        const candidates = Array.isArray(payload.candidates) ? payload.candidates : []
        this.renderGroupCandidates(candidates)
    }

    createGroupConversation = async () => {
        if (!this.groupNameInput || !this.groupCreateSubmitButton) {
            return
        }

        const title = String(this.groupNameInput.value || '').trim()
        const memberIds = Array.from(this.selectedGroupMemberIds)
        this.setGroupTitleError('')

        if (title.length === 0) {
            this.setGroupTitleError('Chat name cannot be empty.')
            return
        }

        this.groupCreateSubmitButton.setAttribute('disabled', 'disabled')

        try {
            const formData = new FormData()
            formData.append('title', title)
            memberIds.forEach((id) => {
                formData.append('userIds[]', String(id))
            })

            const avatarFile = this.groupAvatarInput?.files?.[0]
            if (avatarFile) {
                formData.append('avatar', avatarFile)
            }

            const response = await fetch(this.buildCreateGroupEndpoint(), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })

            const payload = await response.json().catch(() => ({}))
            if (!response.ok || !payload.success) {
                const serverError = payload.error || 'Failed to create group.'
                if (/chat name|letters only|between 4 and 7/i.test(serverError)) {
                    this.setGroupTitleError(serverError)
                    return
                }

                throw new Error(serverError)
            }

            this.getBootstrapModal(this.groupCreateModal)?.hide()
            setTimeout(() => {
                window.location.reload()
            }, 150)
        } catch (error) {
            this.showBottomNotice(error?.message || 'Failed to create group.')
        } finally {
            this.groupCreateSubmitButton.removeAttribute('disabled')
        }
    }

    initGroupModal = () => {
        if (!this.groupCreateModal) {
            return
        }

        this.groupCreateModal.addEventListener('show.bs.modal', async () => {
            this.selectedGroupMemberIds.clear()
            this.setGroupTitleError('')
            if (this.groupNameInput) {
                this.groupNameInput.value = ''
            }
            if (this.groupAvatarInput) {
                this.groupAvatarInput.value = ''
            }
            this.clearGroupAvatar()
            if (this.groupSearchInput) {
                this.groupSearchInput.value = ''
            }

            try {
                await this.loadGroupCandidates('')
            } catch (error) {
                this.showBottomNotice(error?.message || 'Failed to load users.')
            }
        })

        this.groupSearchInput?.addEventListener('input', async () => {
            try {
                await this.loadGroupCandidates(this.groupSearchInput?.value || '')
            } catch (error) {
                this.showBottomNotice(error?.message || 'Failed to load users.')
            }
        })

        this.groupCreateSubmitButton?.addEventListener('click', async () => {
            await this.createGroupConversation()
        })

        this.groupNameInput?.addEventListener('input', () => {
            this.setGroupTitleError('')
        })

        this.groupAvatarTrigger?.addEventListener('click', () => {
            this.groupAvatarInput?.click()
        })

        this.groupAvatarTrigger?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            event.preventDefault()
            this.groupAvatarInput?.click()
        })

        this.groupAvatarInput?.addEventListener('change', () => {
            this.updateGroupAvatarPreview()
        })

        this.groupAvatarClear?.addEventListener('click', (event) => {
            event.preventDefault()
            event.stopPropagation()
            this.clearGroupAvatar()
        })
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
                    this.promoteConversationItem(conversationItem)
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

    enc = (value) => encodeURIComponent(String(value || ''))

    getSelectedConversationId = () => {
        const convId = parseInt(String(this.activeConversationId || '0'), 10)
        return Number.isInteger(convId) && convId > 0 ? convId : 0
    }

    initCallSignaling = () => {
        if (!this.callSignalingEnabled || !this.callSocketUrl) {
            return
        }

        this.stompConnectRequested = true
        this.connectCallSocket()
    }

    loadExternalScript = (src) => {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${src}"]`)
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true })
                existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true })
                if ((existing.dataset.loaded || '') === '1') {
                    resolve()
                }
                return
            }

            const script = document.createElement('script')
            script.src = src
            script.async = true
            script.onload = () => {
                script.dataset.loaded = '1'
                resolve()
            }
            script.onerror = () => reject(new Error(`Failed to load ${src}`))
            document.head.appendChild(script)
        })
    }

    ensureStompLibraries = async () => {
        if (window.SockJS && window.Stomp) {
            return
        }

        await this.loadExternalScript('https://cdn.jsdelivr.net/npm/sockjs-client@1/dist/sockjs.min.js')
        await this.loadExternalScript('https://cdn.jsdelivr.net/npm/stompjs@2.3.3/lib/stomp.min.js')

        if (!window.SockJS || !window.Stomp) {
            throw new Error('SockJS/STOMP libraries are unavailable.')
        }
    }

    getSockJsEndpointUrl = () => {
        const raw = String(this.callSocketUrl || '').trim()
        if (!raw) {
            return `${window.location.protocol}//${window.location.host}/ws`
        }

        if (raw.startsWith('/')) {
            return `${window.location.protocol}//${window.location.host}${raw}`
        }

        if (raw.startsWith('ws://')) {
            return `http://${raw.slice('ws://'.length)}`
        }

        if (raw.startsWith('wss://')) {
            return `https://${raw.slice('wss://'.length)}`
        }

        return raw
    }

    getCallSignalTopics = () => {
        const ids = new Set()
        this.conversationItems.forEach((item) => {
            const convId = parseInt(String(item?.dataset?.conversationId || '0'), 10)
            if (Number.isInteger(convId) && convId > 0) {
                ids.add(convId)
            }
        })

        return Array.from(ids).map((id) => `/topic/call.${id}`)
    }

    subscribeToCallTopics = () => {
        if (!this.stompConnected || !this.stomp) {
            return
        }

        this.stompSubscriptions.forEach((subscription) => {
            try {
                subscription?.unsubscribe?.()
            } catch {
                // ignore stale subscriptions
            }
        })
        this.stompSubscriptions = []

        const topics = this.getCallSignalTopics()
        console.info('Subscribing to call topics:', topics)
        topics.forEach((topic) => {
            const subscription = this.stomp.subscribe(topic, (frame) => {
                this.onIncomingSignal(frame?.body || '{}')
            })
            this.stompSubscriptions.push(subscription)
        })
    }

    sendCallSignal = (destination, payload) => {
        if (!this.stompConnected || !this.stomp) {
            return false
        }

        try {
            this.stomp.send(destination, {}, JSON.stringify(payload))
            return true
        } catch (error) {
            console.error(`Failed to send ${destination}:`, error)
            return false
        }
    }

    connectCallSocket = async () => {
        if (this.stompConnecting || this.stompConnected) {
            return
        }

        this.stompConnecting = true

        try {
            await this.ensureStompLibraries()
        } catch (error) {
            console.error('Call libraries failed to load:', error)
            this.stompConnecting = false
            return
        }

        const endpoint = this.getSockJsEndpointUrl()
        const socket = new window.SockJS(endpoint)
        const client = window.Stomp.over(socket)
        client.debug = () => {}

        this.stomp = client
        client.connect(
            {},
            () => {
                this.stompConnecting = false
                this.stompConnected = true
                console.info('STOMP connected via SockJS')
                this.subscribeToCallTopics()
                this.flushPendingCallAction()
            },
            (error) => {
                console.error('STOMP error:', error)
                this.stompConnecting = false
                this.stompConnected = false
                if (this.stompReconnectTimer) {
                    window.clearTimeout(this.stompReconnectTimer)
                }
                this.stompReconnectTimer = window.setTimeout(() => {
                    this.connectCallSocket()
                }, 2500)
            }
        )

        socket.onclose = () => {
            this.stompConnecting = false
            this.stompConnected = false
            if (this.stompReconnectTimer) {
                window.clearTimeout(this.stompReconnectTimer)
            }
            this.stompReconnectTimer = window.setTimeout(() => {
                this.connectCallSocket()
            }, 2500)
        }
    }

    flushPendingCallAction = () => {
        if (!this.pendingOutgoingCall || !this.stompConnected) {
            return
        }

        const { convId, video } = this.pendingOutgoingCall
        const payload = {
            type: 'RING',
            conversationId: convId,
            fromUserId: this.currentUserId,
            fromName: this.currentUserName || `User ${this.currentUserId}`,
            callKind: video ? 'VIDEO' : 'AUDIO',
        }

        try {
            console.info('Sending pending call.start payload:', payload)
            this.sendCallSignal('/app/call.start', payload)
            this.showBottomNotice(video ? 'Video call invitation sent.' : 'Audio call invitation sent.')
        } catch (error) {
            console.error('Failed to flush pending call:', error)
        }
    }

    initCallActions = () => {
        this.callButtonAudio?.addEventListener('click', (event) => {
            event.preventDefault()
            this.handleAudioCall()
        })

        this.callButtonVideo?.addEventListener('click', (event) => {
            event.preventDefault()
            this.handleVideoCall()
        })
    }

    handleAudioCall = () => {
        const convId = this.getSelectedConversationId()
        if (convId <= 0) {
            this.showBottomNotice('Select a conversation before starting a call.')
            return
        }

        if (!this.stomp) {
            this.stompConnectRequested = true
            this.connectCallSocket()
            this.pendingOutgoingCall = { convId, video: false }
            this.showBottomNotice('Connecting call socket, please wait...')
            return
        }

        if (!this.stompConnected) {
            this.pendingOutgoingCall = { convId, video: false }
            this.showBottomNotice('Connecting call socket, please wait...')
            return
        }

        const payload = {
            type: 'RING',
            conversationId: convId,
            fromUserId: this.currentUserId,
            fromName: this.currentUserName || `User ${this.currentUserId}`,
            callKind: 'AUDIO',
        }

        try {
            console.info('Sending call.start payload:', payload)
            this.sendCallSignal('/app/call.start', payload)
            this.pendingOutgoingCall = { convId, video: false }
            this.showBottomNotice('Audio call invitation sent.')
        } catch (error) {
            console.error('Failed to send audio call invite:', error)
            this.showBottomNotice('Failed to send call invitation.')
        }
    }

    handleVideoCall = () => {
        const convId = this.getSelectedConversationId()
        if (convId <= 0) {
            this.showBottomNotice('Select a conversation before starting a call.')
            return
        }

        if (!this.stomp) {
            this.stompConnectRequested = true
            this.connectCallSocket()
            this.pendingOutgoingCall = { convId, video: true }
            this.showBottomNotice('Connecting call socket, please wait...')
            return
        }

        if (!this.stompConnected) {
            this.pendingOutgoingCall = { convId, video: true }
            this.showBottomNotice('Connecting call socket, please wait...')
            return
        }

        const payload = {
            type: 'RING',
            conversationId: convId,
            fromUserId: this.currentUserId,
            fromName: this.currentUserName || `User ${this.currentUserId}`,
            callKind: 'VIDEO',
        }

        try {
            console.info('Sending call.start payload:', payload)
            this.sendCallSignal('/app/call.start', payload)
            this.pendingOutgoingCall = { convId, video: true }
            this.showBottomNotice('Video call invitation sent.')
        } catch (error) {
            console.error('Failed to send video call invite:', error)
            this.showBottomNotice('Failed to send call invitation.')
        }
    }

    onIncomingSignal = (json) => {
        try {
            const root = JSON.parse(String(json || '{}'))
            const type = String(root?.type || '')
            const convId = parseInt(String(root?.conversationId || '-1'), 10)
            const fromUserId = parseInt(String(root?.fromUserId || '-1'), 10)
            const fromName = String(root?.fromName || `User ${fromUserId}`)
            const callKind = String(root?.callKind || 'AUDIO')
            const video = callKind.toUpperCase() === 'VIDEO'

            console.info('Received call signal:', {
                type,
                convId,
                fromUserId,
                currentUserId: this.currentUserId,
            })

            if (convId <= 0) {
                return
            }

            if (fromUserId === this.currentUserId) {
                return
            }

            const room = `conv_${convId}`

            if (type === 'RING') {
                this.showIncomingCallPopup(convId, video, room, fromName)
                return
            }

            if (type === 'ACCEPT') {
                this.closeIncomingPopup()
                if (this.pendingOutgoingCall && this.pendingOutgoingCall.convId === convId) {
                    this.openCallWindow(convId, this.pendingOutgoingCall.video, room)
                    this.pendingOutgoingCall = null
                }
                return
            }

            if (type === 'REJECT') {
                this.closeIncomingPopup()
                this.pendingOutgoingCall = null
                this.showBottomNotice('Call rejected.')
            }
        } catch (error) {
            console.error('Failed to process incoming call signal:', error)
        }
    }

    showIncomingCallPopup = (convId, video, room, fromName) => {
        this.pendingIncomingCall = { convId, video, room, fromName }
        const kindLabel = video ? 'video' : 'audio'
        const accepted = window.confirm(`${fromName} is calling you (${kindLabel}). Accept?`)
        if (accepted) {
            this.sendAccept(convId, video)
            this.openCallWindow(convId, video, room)
            return
        }

        this.sendReject(convId, video)
    }

    closeIncomingPopup = () => {
        this.pendingIncomingCall = null
    }

    sendAccept = (convId, video) => {
        if (!this.stompConnected || !this.stomp) {
            return
        }

        const payload = {
            type: 'ACCEPT',
            conversationId: convId,
            fromUserId: this.currentUserId,
            callKind: video ? 'VIDEO' : 'AUDIO',
        }

        this.sendCallSignal('/app/call.accept', payload)
    }

    sendReject = (convId, video) => {
        if (!this.stompConnected || !this.stomp) {
            return
        }

        const payload = {
            type: 'REJECT',
            conversationId: convId,
            fromUserId: this.currentUserId,
            callKind: video ? 'VIDEO' : 'AUDIO',
        }

        this.sendCallSignal('/app/call.reject', payload)
    }

    openCallWindow = async (convId, videoEnabled, room) => {
        if (convId <= 0) {
            return
        }

        try {
            const roomName = String(room || '').trim() !== ''
                ? String(room)
                : `conv-${convId}`

            const identity = `user-${this.currentUserId}`
            const myName = this.currentUserName || `User ${this.currentUserId}`

            const tokenUrl = `${this.callTokenEndpoint}?room=${this.enc(roomName)}&identity=${this.enc(identity)}&name=${this.enc(myName)}`
            const tokenResponse = await fetch(tokenUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            })

            if (!tokenResponse.ok) {
                const body = await tokenResponse.text()
                throw new Error(`Token failed: ${body}`)
            }

            const payload = await tokenResponse.json()
            const token = String(payload?.token || '').trim()
            if (!token) {
                throw new Error('Token response is missing token field.')
            }

            const callUrl = `${this.callPageEndpoint}?wsUrl=${this.enc(this.callLivekitUrl)}&token=${this.enc(token)}&mic=true&cam=${videoEnabled ? 'true' : 'false'}`
            window.open(callUrl, '_blank', 'noopener')
        } catch (error) {
            console.error('Failed to open call window:', error)
            this.showBottomNotice(error?.message || 'Could not start call.')
        }
    }

    init = () => {
        this.cacheElements();
        this.loadAiSummaryPendingState();
        this.initEmojiPicker();
        this.setComposerEnabled(false);
        this.initDetailsDrawer();
        this.initCustomization();
        this.initDMModal();
        this.initGroupModal();
        this.scrollToBottom();
        this.initFilters();
        this.initSearch();
        this.initConversationSelection();
        this.initCallSignaling();
        this.initCallActions();
        this.closeEmojiPicker();
        this.setVoiceComposerUi();
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
