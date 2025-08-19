<template>
    <div class="h-full flex-1 flex flex-col p-3">
        <div v-if="user" class="flex flex-col justify-between h-full">
            <div class="flex gap-5">
                <!-- Заголовок чата -->
                <div class="flex items-center gap-3 mb-2">
                    <img
                        v-if="user.avatar_url"
                        :src="user.avatar_url"
                        :alt="user.first_name || user.username"
                        class="h-9 w-9 rounded-[10px] object-cover"
                    />
                    <div class="flex flex-col">
                        <div class="font-bold">{{ userTitle }}</div>
                        <div class="text-xs text-gray-500">@{{ user.username }}</div>
                    </div>
                </div>

                <!-- Кнопка «СМС из шаблона» -->
                <div class="relative mb-2">
                    <button
                        @click="showTplMenu = !showTplMenu"
                        class="px-4 py-2 bg-gray-700 text-white rounded"
                    >
                        СМС из шаблона
                    </button>
                    <ul
                        v-if="showTplMenu"
                        class="absolute bg-white dark:bg-gray-800 border dark:border-gray-700 mt-1 rounded shadow w-48 z-10"
                    >
                        <li v-for="tpl in savedTemplates" :key="tpl.id">
                            <a
                                @click="applyTemplate(tpl)"
                                class="block px-3 py-1 dark:hover:bg-gray-700 hover:bg-gray-100 dark:text-gray-100 cursor-pointer"
                            >
                                {{ tpl.title }}
                            </a>
                        </li>
                        <li v-if="!savedTemplates.length" class="px-3 py-1 text-gray-500">
                            Нет сохранённых шаблонов
                        </li>
                    </ul>
                </div>
            </div>

            <!-- История сообщений -->
            <div
                ref="messagesEnd"
                @scroll="handleScroll"
                class="max-h-[450px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent custom-scrollbar flex flex-col gap-2 flex-1"
                style="border:1px solid #333; border-radius: 8px; padding: 8px;"
            >
                <template v-for="msg in messages" :key="msg.id">
                    <!-- 1) Авто-сообщение -->
                    <div v-if="msg.is_auto" class="flex justify-end">
                        <div
                            class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-gray-300 text-gray-800 rounded-br-sm">
                            <div class="text-xs font-semibold mb-1">Авто-сообщение</div>
                            <span v-html="formatMessage(msg.text)"></span>

                            <!-- Файлы авто-сообщения -->
                            <div v-if="msg.files && msg.files.length" class="mt-2 flex flex-wrap gap-2">
                                <template v-for="file in msg.files" :key="file.id || file.url">
                                    <div v-if="isImage(file)" class="max-w-[120px]">
                                        <img :src="file.url" :alt="file.name || 'image'" class="rounded shadow" loading="lazy" />
                                    </div>
                                    <div v-else-if="isVideo(file)" class="max-w-[160px]">
                                        <video controls :src="file.url" class="rounded shadow" style="max-width:150px;max-height:120px"></video>
                                    </div>
                                    <div v-else-if="isAudio(file)" class="max-w-[120px]">
                                        <audio controls :src="file.url"></audio>
                                    </div>
                                    <div v-else>
                                        <a :href="file.url" target="_blank" class="underline text-blue-500">
                                            {{ file.name || 'Файл' }}
                                        </a>
                                    </div>
                                </template>
                            </div>

                            <div class="text-xs text-right mt-1 text-gray-500">
                                {{ formatDate(msg.created_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- 2) Сообщение от оператора -->
                    <div v-else-if="msg.is_operator" class="flex justify-end">
                        <div class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-blue-900 text-white rounded-br-sm">
                            <div class="text-xs font-semibold mb-1 flex justify-end">
                                Вы{{ msg.username ? `: ${msg.username}` : '' }}
                            </div>
                            <span v-html="formatMessage(msg.text)"></span>

                            <!-- Файлы оператора -->
                            <div v-if="msg.files && msg.files.length" class="mt-2 flex flex-wrap gap-2">
                                <template v-for="file in msg.files" :key="file.id || file.url">
                                    <div v-if="isImage(file)" class="max-w-[120px]">
                                        <img :src="file.url" :alt="file.name || 'image'" class="rounded shadow" loading="lazy" />
                                    </div>
                                    <div v-else-if="isVideo(file)" class="max-w-[160px]">
                                        <video controls :src="file.url" class="rounded shadow" style="max-width:150px;max-height:120px"></video>
                                    </div>
                                    <div v-else-if="isAudio(file)" class="max-w-[120px]">
                                        <audio controls :src="file.url"></audio>
                                    </div>
                                    <div v-else>
                                        <a :href="file.url" target="_blank" class="underline text-blue-200">
                                            {{ file.name || 'Файл' }}
                                        </a>
                                    </div>
                                </template>
                            </div>

                            <div class="text-xs text-right mt-1 text-gray-300 dark:text-gray-400">
                                {{ formatDate(msg.created_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- 3) Сообщение от пользователя -->
                    <div v-else class="flex justify-start">
                        <div
                            class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-bl-sm">
                            <div class="text-xs font-semibold mb-1">
                                {{ userTitle || 'Пользователь' }}
                            </div>
                            <span v-html="formatMessage(msg.text)"></span>

                            <!-- Файлы пользователя -->
                            <div v-if="msg.files && msg.files.length" class="mt-2 flex flex-wrap gap-2">
                                <template v-for="file in msg.files" :key="file.id || file.url">
                                    <div v-if="isImage(file)" class="max-w-[120px]">
                                        <img :src="file.url" :alt="file.name || 'image'" class="rounded shadow" loading="lazy" />
                                    </div>
                                    <div v-else-if="isVideo(file)" class="max-w-[160px]">
                                        <video controls :src="file.url" class="rounded shadow" style="max-width:150px;max-height:120px"></video>
                                    </div>
                                    <div v-else-if="isAudio(file)" class="max-w-[120px]">
                                        <audio controls :src="file.url"></audio>
                                    </div>
                                    <div v-else>
                                        <a :href="file.url" target="_blank" class="underline text-blue-500">
                                            {{ file.name || 'Файл' }}
                                        </a>
                                    </div>
                                </template>
                            </div>

                            <div class="text-xs text-right mt-1 text-gray-500 dark:text-gray-400">
                                {{ formatDate(msg.created_at) }}
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Форма отправки -->
            <form
                class="flex gap-2 p-4 bg-white dark:bg-gray-800"
                @submit.prevent="sendMessage">
        <textarea
            ref="textarea"
            v-model="newMessage"
            placeholder="Сообщение..."
            class="w-full h-[100px] flex-1 px-2 py-1 rounded bg-gray-800 text-gray-100 resize-y"
            rows="1"
            style="min-height:40px;max-height:120px;"
        ></textarea>
                <button type="submit" class="px-4 py-1 rounded bg-blue-600 text-white h-fit">
                    Отправить
                </button>
            </form>
        </div>

        <!-- Заглушка, когда чат не выбран -->
        <div v-else class="w-full flex items-center justify-center text-gray-500 flex-1">
            Выберите собеседника для открытия чата с ним
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import ChatList from './ChatList.vue'
import ChatComponent from './ChatComponent.vue'

const chatUsers = ref(window.chatUsers || [])
const activeUserId = ref(null)

function setActiveUser(id) {
    activeUserId.value = id
}
</script>

<script>
export default {
    props: {
        activeUserId: {
            type: [String, Number],
            default: null,
        },
    },
    data() {
        return {
            user: null,
            messages: [],
            newMessage: '',
            pollInterval: null,
            isAutoScroll: true,
            showTplMenu: false,
            savedTemplates: [], // заполняется из API
        }
    },
    computed: {
        userTitle() {
            return this.user ? this.user.first_name || this.user.username : ''
        },
    },
    watch: {
        activeUserId: {
            immediate: true,
            handler(newId) {
                this.stopPolling()
                if (!newId) {
                    this.user = null
                    this.messages = []
                    this.savedTemplates = []
                    return
                }
                this.fetchUser(newId).then(() => this.fetchTemplates())
                this.fetchMessages(newId)
                this.startPolling()
            },
        },
        messages: {
            handler() {
                this.$nextTick(this.scrollToBottom)
            },
            deep: true,
        },
    },
    methods: {
        fetchUser(userId) {
            return fetch(`/api/chats/${userId}/info`)
                .then(res => res.json())
                .then(data => {
                    this.user = data
                    return data
                })
                .catch(err => {
                    console.error(err)
                    this.user = null
                })
        },
        sendFile() {
            const formData = new FormData();
            formData.append('file', this.selectedFile); // Убедитесь, что selectedFile имеет правильное значение
            formData.append('chat_message_id', this.chatMessageId); // ID сообщения

            console.log('Отправка данных на сервер:', formData);  // Логируем данные перед отправкой

            axios.post('/api/chats/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            })
                .then(response => {
                    console.log('Ответ от сервера:', response.data);
                })
                .catch(error => {
                    console.error('Ошибка при загрузке файла:', error);
                });
        },
        fetchMessages(userId) {
            fetch(`/api/chats/${userId}/messages`)
                .then(res => res.json())
                .then(data => {
                    this.messages = data
                })
                .catch(console.error)
        },
        fetchTemplates() {
            if (!this.user?.bot_id) return
            fetch(`/api/bots/${this.user.bot_id}/templates`)
                .then(res => res.json())
                .then(list => {
                    this.savedTemplates = list.filter(t => t.type === 'custom')
                })
                .catch(err => {
                    console.error(err)
                    this.savedTemplates = []
                })
        },
        async applyTemplate(tpl) {
            this.showTplMenu = false
            const token = document
                .querySelector('meta[name="csrf-token"]')
                .content
            try {
                const res = await fetch(
                    `/api/chats/${this.user.id}/send-template`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ template_id: tpl.id }),
                    }
                )
                if (!res.ok) throw new Error(`HTTP ${res.status}`)
                const newMsgs = await res.json()
                // сохраняем прежнее поведение...
                this.messages.push(...newMsgs)
                // ...и сразу подгружаем с сервера, чтобы появились files/url
                this.fetchMessages(this.user.id)
                this.$nextTick(this.scrollToBottom)
            } catch (err) {
                console.error('Ошибка при отправке шаблона:', err)
            }
        },
        formatMessage(text) {
            if (!text) return ''
            return text
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>')
        },
        sendMessage() {
            if (!this.newMessage.trim() || !this.user) return
            const url = `/chats/${this.user.id}/messages`
            const headers = { 'Content-Type': 'application/json' }
            const meta = document.querySelector('meta[name="csrf-token"]')
            if (meta) headers['X-CSRF-TOKEN'] = meta.content
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({ text: this.newMessage }),
            })
                .then(res => res.json())
                .then(msg => {
                    this.messages.push(msg)
                    this.newMessage = ''
                })
                .catch(console.error)
        },
        formatDate(dateStr) {
            if (!dateStr) return ''
            const d = new Date(dateStr)
            if (isNaN(d)) return dateStr
            return (
                String(d.getDate()).padStart(2, '0') + '.' +
                String(d.getMonth() + 1).padStart(2, '0') + '.' +
                d.getFullYear() + ' ' +
                String(d.getHours()).padStart(2, '0') + ':' +
                String(d.getMinutes()).padStart(2, '0')
            )
        },
        startPolling() {
            this.pollInterval = setInterval(() => {
                if (this.activeUserId) {
                    this.fetchMessages(this.activeUserId)
                }
            }, 5000)
        },
        stopPolling() {
            clearInterval(this.pollInterval)
            this.pollInterval = null
        },
        scrollToBottom() {
            const el = this.$refs.messagesEnd
            if (el && this.isAutoScroll) el.scrollTop = el.scrollHeight
        },
        handleScroll() {
            const el = this.$refs.messagesEnd
            this.isAutoScroll = el.scrollTop + el.clientHeight >= el.scrollHeight - 30
        },

        // === ДОБАВЛЕНО: универсальные проверки типа файла ===
        isImage(file) {
            const m = (file.type || file.mime || '').toLowerCase()
            if (m.startsWith('image/')) return true
            return /\.(png|jpe?g|webp|gif|bmp|svg)$/i.test(file.url || '')
        },
        isVideo(file) {
            const m = (file.type || file.mime || '').toLowerCase()
            if (m.startsWith('video/')) return true
            return /\.(mp4|webm|mov|mkv|avi)$/i.test(file.url || '')
        },
        isAudio(file) {
            const m = (file.type || file.mime || '').toLowerCase()
            if (m.startsWith('audio/')) return true
            return /\.(mp3|wav|ogg|m4a)$/i.test(file.url || '')
        },
    },
    beforeUnmount() {
        this.stopPolling()
    },
    mounted() {
        if (this.activeUserId) {
            this.fetchTemplates()
        }
    },
}
</script>
