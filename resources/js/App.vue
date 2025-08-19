<template>
    <div class="flex w-full h-full bg-white dark:bg-gray-800 rounded-[10px] overflow-hidden gap-3">
        <ChatList :users="chatUsers" :activeUserId="activeUserId" @select="setActiveUser" />
        <ChatComponent :active-user-id="activeUserId" />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import ChatList from './components/ChatList.vue'
import ChatComponent from './components/ChatComponent.vue'

const chatUsers = ref(window.chatUsers || [])
const activeUserId = ref(null)
let pollInterval = null

function fetchChatUsers() {
    fetch('/api/chats/list')
        .then(res => res.json())
        .then(data => {
            chatUsers.value = data
        })
}

onMounted(() => {
    pollInterval = setInterval(fetchChatUsers, 5000)
})

onBeforeUnmount(() => {
    if (pollInterval) clearInterval(pollInterval)
})

async function setActiveUser(id) {
    activeUserId.value = id
    await fetch(`/dashboard/chats/read/${id}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    const user = chatUsers.value.find(u => u.user_id === id)
    if (user && user.messages) {
        user.messages.forEach(msg => msg.is_read = 1)
    }
}
</script>
