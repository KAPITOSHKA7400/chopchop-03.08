<template>
    <div class="h-full">
        <div v-if="user" class="flex flex-col justify-between">
            <div class="flex flex-col">
                <!-- Заголовок чата -->
                <div class="mb-2 font-bold">{{ userTitle }}</div>

                <!-- Окно истории сообщений с кастомным скроллбаром -->
                <div
                    class="mb-4 max-h-[450px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 scrollbar-track-transparent"
                    style="border:1px solid #333; border-radius: 8px; padding: 8px;">
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        :class="msg.is_operator ? 'text-right text-blue-400 mb-2' : 'text-left text-gray-300 mb-2'">
                        <div class="text-xs font-semibold mb-1">
                            <span v-if="msg.is_operator">Вы: {{ msg.username }}</span>
                            <span v-else>Пользователь</span>
                        </div>
                        <span>{{ msg.text }}</span>
                        <div class="text-xs text-gray-500">{{ formatDate(msg.created_at) }}</div>
                    </div>
                </div>
            </div>

            <!-- Форма отправки -->
            <form class="flex gap-2 p-4 bg-white dark:bg-gray-800" @submit.prevent="sendMessage">
                <input
                    v-model="newMessage"
                    placeholder="Сообщение..."
                    class="w-full flex-1 px-2 py-1 rounded bg-gray-800 text-gray-100"/>
                <button type="submit" class="px-4 py-1 rounded bg-blue-600 text-white">
                    Отправить
                </button>
            </form>
        </div>

        <!-- Заглушка, когда чат не выбран -->
        <div v-else class="w-full flex items-center justify-center text-gray-500">
            Выберите собеседника для открытия чата с ним
        </div>
    </div>
</template>

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
        };
    },
    watch: {
        activeUserId: {
            immediate: true,
            handler(newId) {
                // Сбросим старые данные и таймер
                this.stopPolling();

                if (!newId) {
                    this.user = null;
                    this.messages = [];
                    return;
                }

                // Загрузим нового пользователя и его историю
                this.fetchUser(newId);
                this.fetchMessages(newId);

                // Запустим опрос каждые 5 секунд
                this.startPolling();
            },
        },
    },
    computed: {
        userTitle() {
            if (!this.user) return '';
            return this.user.first_name || this.user.username || '';
        },
    },
    methods: {
        fetchUser(userId) {
            fetch(`/api/chats/${userId}/info`)
                .then(res => res.json())
                .then(data => {
                    this.user = data;
                });
        },
        fetchMessages(userId) {
            fetch(`/api/chats/${userId}/messages`)
                .then(res => res.json())
                .then(data => {
                    this.messages = data;
                });
        },
        sendMessage() {
            if (!this.newMessage.trim() || !this.user) return;

            const url = `/chats/${this.user.id}/messages`;
            const headers = { 'Content-Type': 'application/json' };
            const meta = document.head.querySelector('meta[name="csrf-token"]');
            if (meta) headers['X-CSRF-TOKEN'] = meta.content;

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({ text: this.newMessage }),
            })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(msg => {
                    this.messages.push(msg);
                    this.newMessage = '';
                })
                .catch(err => {
                    console.error('Ошибка при отправке сообщения:', err);
                });
        },
        formatDate(dateStr) {
            if (!dateStr) return '';
            // Парсим как обычно
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;

            // Берём UTC-получасовые значения, чтобы не было смещения
            const d = String(date.getUTCDate()).padStart(2, '0');
            const m = String(date.getUTCMonth() + 1).padStart(2, '0');
            const y = date.getUTCFullYear();
            const hh = String(date.getUTCHours()).padStart(2, '0');
            const mm = String(date.getUTCMinutes()).padStart(2, '0');

            return `${d}.${m}.${y} ${hh}:${mm}`;
        },

        // Запускаем интервал для регулярного обновления
        startPolling() {
            this.pollInterval = setInterval(() => {
                if (this.activeUserId) {
                    this.fetchMessages(this.activeUserId);
                }
            }, 5000);
        },
        // Останавливаем опрос
        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },
    },
    // На всякий случай очищаем при размонтировании
    beforeUnmount() {
        this.stopPolling();
    },
};
</script>
