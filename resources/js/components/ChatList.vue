<template>
    <aside class="p-2 relative w-1/4 border-r dark:border-gray-700 overflow-y-auto">
        <div class="p-2 border-b border-gray-200 dark:border-gray-700">
            <h5 class="leading-6 font-medium text-gray-900 dark:text-white">
                Диалоги с пользователями Telegram
            </h5>
            <p class="mt-1 max-w-2xl text-xs text-gray-500 dark:text-gray-400">
                Список пользователей, которые общались с ботом
            </p>
        </div>
        <div class="flex flex-col" id="chat-list-panel">
            <div
                v-if="!users || users.length === 0"
                class="px-4 py-5 sm:px-6 text-center text-gray-500 dark:text-gray-400">
                Нет активных диалогов
            </div>
            <ul
                v-else
                role="list"
                class=" dark:divide-gray-700 max-h-[650px] pr-1 custom-scrollbar">
                <li v-for="user in users" :key="user.user_id" class="mt-2">
                    <a
                        href="#"
                        :data-userid="user.user_id"
                        class="chat-user-link block hover:bg-gray-300 dark:hover:bg-gray-700 rounded-[10px] overflow-hidden transition-colors duration-150 ease-in-out"
                        :class="activeUserId == user.user_id ? 'bg-gray-300 dark:bg-gray-700 active text-white rounded-[10px]' : 'text-gray-400'"
                        @click.prevent="$emit('select', user.user_id)">
                        <div class="flex items-center p-2">
                            <div class="min-w-0 flex-1 flex items-center">
                                <!-- Аватар пользователя -->
                                <div class="flex-shrink-0">
                                    <img
                                        v-if="user.avatar_url"
                                        :src="user.avatar_url"
                                        :alt="user.first_name || user.username"
                                        class="h-16 w-16 rounded-[10px] object-cover" />
                                    <div
                                        v-else
                                        class="h-16 w-16 rounded-[10px] bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-800 dark:text-indigo-200 font-bold">
                                        {{ (user.first_name || user.username || 'U').substring(0,1).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="min-w-0 md:grid md:grid-cols-[1fr,auto] flex-1 px-2 ">
                                    <div class="w-max hidden md:block">
                                        <div>
                                            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 truncate">
                                                {{ user.first_name }} {{ user.last_name }}
                                            </p>
                                        </div>
                                        <div>
                                            <!-- Последнее сообщение -->
                                            <p v-if="user.messages && user.messages.length > 0"
                                               class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd"
                                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                          clip-rule="evenodd"/>
                                                </svg>
                                                {{ formatDate(user.messages[0].created_at) }}
                                            </p>
                                            <p v-else class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                                Начало диалога
                                            </p>
                                            <p v-if="user.messages && user.messages.length > 0"
                                                class="text-sm text-gray-900 dark:text-gray-200 truncate">
                                                {{ limitText(user.messages[0].text, 50) }}
                                            </p>
                                            <p v-else class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd"
                                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                          clip-rule="evenodd"/>
                                                </svg>
                                                Нет сообщений
                                            </p>
                                        </div>
                                    </div>
                                    <div class="w-[30px] right-0">
                                        <span v-if="hasUnread(user)" title="Новое сообщение">
                                            <svg class="h-5 w-5 text-red-500 animate-bounce" fill="currentColor" viewBox="0 0 20 20">
                                                <circle cx="10" cy="10" r="6"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <svg class="h-5 w-5 text-gray-400 dark:text-gray-500"
                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                     fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                          d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </aside>
</template>

<script setup>
import { defineProps } from 'vue'

const props = defineProps({
    users: Array,
    activeUserId: [String, Number]
})

function limitText(text, max) {
    if (!text) return ''
    return text.length > max ? text.slice(0, max) + '...' : text
}
function formatDate(str) {
    if (!str) return ''
    const date = new Date(str)
    if (isNaN(date.getTime())) return str
    const d = String(date.getDate()).padStart(2, '0')
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const y = date.getFullYear()
    const hh = String(date.getHours()).padStart(2, '0')
    const mm = String(date.getMinutes()).padStart(2, '0')
    return `${d}.${m}.${y} ${hh}:${mm}`
}
function hasUnread(user) {
    if (!user.messages) return false
    // Фикс для чисел и строк
    return user.messages.some(msg => String(msg.is_read) === '0')
}
</script>
