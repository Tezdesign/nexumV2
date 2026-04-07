class ChatApp {

    constructor() {
        this.messagesScrollWrapper = null
        this.messagesList = null
        this.conversationItems = []
        this.filterButtons = []
        this.filterEmptyState = null
        this.filterLabel = null
        this.activeFilter = 'all'
        this.messagesSimplebar = null
        this.chatForm = null
        this.chatInput = null
    }

    cacheElements = () => {
        this.messagesScrollWrapper = document.querySelector(
            '[data-apps-chat="messages-scroll-wrapper"]'
        )
        this.messagesList = document.querySelector('[data-apps-chat="messages-list"]')
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
        this.chatForm = document.querySelector('#chat-form')
        if (this.chatForm)
            this.chatInput = this.chatForm.querySelector('input')
        if (this.messagesScrollWrapper && window.SimpleBar)
            this.messagesSimplebar = new SimpleBar(this.messagesScrollWrapper)
    }

    getMessageHTML = (message) => {
        return `<li class="chat-group odd" id="odd-1">
                    <img src="assets/images/users/avatar-1.jpg" class="avatar-sm rounded-circle" alt="avatar-1" />

                    <div class="chat-body">
                        <div>
                            <h6 class="d-inline-flex">You.</h6>
                            <h6 class="d-inline-flex text-muted">10:05pm</h6>
                        </div>

                        <div class="chat-message">
                            <p>${message}</p>

                            <div class="chat-actions dropdown">
                                <button class="btn btn-sm btn-link" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>

                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"><i class="ti ti-copy fs-14 align-text-top me-1"></i>
                                        Copy Message</a>
                                    <a class="dropdown-item" href="#"><i class="ti ti-edit-circle fs-14 align-text-top me-1"></i>
                                        Edit</a>
                                    <a class="dropdown-item" href="#" data-dismissible="#odd-1"><i class="ti ti-trash fs-14 align-text-top me-1"></i>Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>`
    }

    addNewMessage = (message) => {
        if (this.messagesList) {
            this.messagesList.innerHTML += (this.getMessageHTML(message))
            this.scrollToBottom(true);
        }
    }

    applyConversationFilter = (filter) => {
        this.activeFilter = filter

        const selectedType = filter === 'dm' ? 'DM' : filter === 'group' ? 'GROUP' : null
        let hasMatches = false

        this.conversationItems.forEach((item) => {
            const rowType = (item.dataset.conversationType || '').toUpperCase()
            const isVisible = selectedType === null || rowType === selectedType

            item.classList.toggle('d-none', !isVisible)
            if (isVisible) {
                hasMatches = true
            }
        })

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

    initForm = () => {
        this.chatForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target).entries());
            if (data.message) {
                if (data.message.trim().length === 0) {
                    this.chatForm.reset();
                } else {
                    this.chatInput.value = " ";
                    this.addNewMessage(data['message']);
                    // this.chatForm.reset();
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
        this.scrollToBottom();
        this.initFilters();
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
