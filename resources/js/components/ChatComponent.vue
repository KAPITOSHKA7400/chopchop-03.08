<template>
    <div class="h-full flex-1 flex flex-col p-3">
        <div v-if="user" class="flex flex-col justify-between h-full">
            <div class="flex gap-5">
                <!-- Заголовок -->
                <div class="flex items-center gap-3 mb-2">
                    <img
                        v-if="user && user.avatar_url"
                        :src="user.avatar_url"
                        :alt="user.first_name || user.username"
                        class="h-9 w-9 rounded-[10px] object-cover"
                    />
                    <div class="flex flex-col">
                        <div class="font-bold">{{ userTitle }}</div>
                        <div class="text-xs text-gray-500">@{{ user && user.username }}</div>
                    </div>
                </div>

                <!-- Кнопка шаблонов -->
                <div class="relative mb-2">
                    <button @click="showTplMenu = !showTplMenu" class="px-4 py-2 bg-gray-700 text-white rounded">
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

            <!-- История -->
            <div
                ref="messagesEnd"
                @scroll="handleScroll"
                class="max-h-[450px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-500 custom-scrollbar flex flex-col gap-2 flex-1"
                style="border:1px solid #333; border-radius: 8px; padding: 8px;"
            >
                <template v-for="msg in messages" :key="msg.id">
                    <!-- Авто -->
                    <div v-if="msg.is_auto" class="flex justify-end">
                        <div class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-gray-300 text-gray-800 rounded-br-sm">
                            <div class="text-xs font-semibold mb-1">Авто-сообщение</div>
                            <span v-html="formatMessage(msg.text)"></span>
                            <MessageFiles :files="msg.files" @open="openPreview" />
                            <div class="text-xs text-right mt-1 text-gray-500">{{ formatDate(msg.created_at) }}</div>
                        </div>
                    </div>

                    <!-- Оператор -->
                    <div v-else-if="msg.is_operator" class="flex justify-end">
                        <div class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-blue-900 text-white rounded-br-sm">
                            <div class="text-xs font-semibold mb-1 flex items-center gap-2 justify-end">
                                <span>Вы{{ msg.username ? `: ${msg.username}` : '' }}</span>
                                <span v-if="msg.is_deleted" class="text-red-400 font-bold">(Сообщение удалено)</span>
                                <span v-else-if="msg.is_edited" class="text-red-300 font-semibold">(изменено)</span>
                            </div>

                            <span v-html="formatMessage(msg.text)"></span>
                            <MessageFiles :files="msg.files" @open="openPreview" />

                            <div class="mt-2 flex gap-2 justify-end">
                                <button
                                    class="px-2 py-1 text-xs rounded bg-gray-600 hover:bg-gray-500"
                                    @click="startEdit(msg)"
                                    :disabled="isEditing && editTarget && editTarget.id !== msg.id"
                                >Ред.</button>
                                <button
                                    class="px-2 py-1 text-xs rounded bg-red-700 hover:bg-red-600"
                                    @click="deleteMessage(msg)"
                                    :disabled="isEditing && editTarget && editTarget.id === msg.id"
                                >Удал.</button>
                            </div>

                            <div class="text-xs text-right mt-1 text-gray-300">{{ formatDate(msg.created_at) }}</div>
                        </div>
                    </div>

                    <!-- Пользователь -->
                    <div v-else class="flex justify-start">
                        <div class="relative max-w-[75%] px-4 py-2 rounded-2xl shadow bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-bl-sm">
                            <div class="text-xs font-semibold mb-1">{{ userTitle || 'Пользователь' }}</div>
                            <span v-html="formatMessage(msg.text)"></span>
                            <MessageFiles :files="msg.files" @open="openPreview" />
                            <div class="text-xs text-right mt-1 text-gray-500">{{ formatDate(msg.created_at) }}</div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Форма -->
            <form class="flex flex-col gap-2 p-4 bg-white dark:bg-gray-800" @submit.prevent="submitHandler">
                <!-- Предпросмотр вложений -->
                <div v-if="attachments.length" class="flex flex-wrap gap-3">
                    <div
                        v-for="(att, i) in attachments"
                        :key="att.id"
                        class="relative border border-gray-600/40 rounded-lg p-2 bg-gray-900/20"
                    >
                        <img v-if="att.preview && att.isImage" :src="att.preview" class="h-20 w-20 object-cover rounded" />
                        <div v-else class="h-20 w-40 flex items-center justify-center text-xs text-gray-400">
                            {{ att.file.name }}
                        </div>
                        <button
                            type="button"
                            class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-black/70 text-white"
                            @click="removeAttachment(i)"
                        >✕</button>
                    </div>
                </div>

                <div class="flex gap-2 items-end">
                    <textarea
                        ref="textarea"
                        v-model="newMessage"
                        :placeholder="isEditing ? 'Редактирование сообщения…' : 'Сообщение...'"
                        class="w-full h-[100px] flex-1 px-2 py-1 rounded bg-gray-800 text-gray-100 resize-y"
                        rows="1"
                        style="min-height:40px;max-height:120px;"
                        @paste="handlePaste"
                        @keydown="onTextareaKeydown"
                    ></textarea>

                    <div class="flex flex-col gap-2">
                        <button type="button" class="px-3 py-1 rounded bg-gray-600 text-white"
                                @click="triggerFile" :disabled="isEditing">
                            Добавить файл
                        </button>

                        <button
                            v-if="isEditing"
                            type="button"
                            class="px-4 py-1 rounded bg-blue-600 text-white"
                            :disabled="savingEdit"
                            @click.prevent="confirmEdit"
                        >
                            {{ savingEdit ? 'Сохраняю...' : 'Сохранить' }}
                        </button>
                        <button v-else type="submit" class="px-4 py-1 rounded bg-blue-600 text-white">
                            Отправить
                        </button>

                        <button v-if="isEditing" type="button" class="px-4 py-1 rounded bg-gray-500 text-white" @click="cancelEdit">
                            Отмена
                        </button>
                    </div>

                    <input ref="fileInput" type="file" class="hidden" multiple @change="handleFileSelect" />
                </div>
            </form>
        </div>

        <div v-else class="w-full flex items-center justify-center text-gray-500 flex-1">
            Выберите собеседника для открытия чата с ним
        </div>

        <transition name="fade">
            <div v-if="lightbox.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-3" @click.self="closePreview">
                <button class="absolute top-4 right-4 text-white/80 hover:text-white text-2xl" aria-label="Закрыть" @click="closePreview">×</button>
                <div class="max-w-[90vw] max-h-[85vh]">
                    <img v-if="lightbox.type==='image'" :src="lightbox.url" class="max-w-[90vw] max-h-[85vh] rounded-lg shadow-xl select-none" draggable="false" />
                    <video v-else-if="lightbox.type==='video'" :src="lightbox.url" class="max-w-[90vw] max-h-[85vh] rounded-lg shadow-xl" controls autoplay />
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import ChatList from './ChatList.vue'
import ChatComponent from './ChatComponent.vue'

const chatUsers = ref(window.chatUsers || [])
const activeUserId = ref(null)
function setActiveUser(id) { activeUserId.value = id }
</script>

<script>
const MessageFiles = {
    name: 'MessageFiles',
    props: { files: { type: Array, default: () => [] } },
    emits: ['open'],
    methods: {
        mime(f) { return (f.mime_type || f.mime || f.type || '').toLowerCase() },
        url(f) { return f.url || (f.file_path ? `/storage/${f.file_path}` : '') },
        isImage(f) { const m=this.mime(f); if(m.startsWith('image/')) return true; return /\.(png|jpe?g|webp|gif|bmp|svg)$/i.test(this.url(f)) },
        isVideo(f) { const m=this.mime(f); if(m.startsWith('video/')) return true; return /\.(mp4|webm|mov|mkv|avi)$/i.test(this.url(f)) },
        isAudio(f) { const m=this.mime(f); if(m.startsWith('audio/')) return true; return /\.(mp3|wav|ogg|m4a)$/i.test(this.url(f)) },
        open(f){ this.$emit('open', { file:f, url:this.url(f), type: this.isImage(f)?'image': this.isVideo(f)?'video': 'file' }) }
    },
    template: `
        <div v-if="files && files.length" class="mt-2 flex flex-wrap gap-2">
            <template v-for="f in files" :key="f.id || url(f)">
                <div v-if="isImage(f)" class="max-w-[250px]">
                    <img :src="url(f)" :alt="f.name || 'image'" class="rounded shadow cursor-zoom-in" loading="lazy" @click="open(f)"/>
                </div>
                <div v-else-if="isVideo(f)" class="max-w-[300px]">
                    <video controls :src="url(f)" class="rounded shadow cursor-pointer" style="max-width:300px;max-height:180px" @click.prevent="open(f)"></video>
                </div>
                <div v-else-if="isAudio(f)" class="max-w-[300px]">
                    <audio controls :src="url(f)"></audio>
                </div>
                <div v-else>
                    <a :href="url(f)" target="_blank" class="underline text-blue-500">{{ f.name || 'Файл' }}</a>
                </div>
            </template>
        </div>
    `
}

export default {
    components: { MessageFiles },
    props: {
        activeUserId: { type: [String, Number], default: null },
    },
    data() {
        return {
            user: null,
            messages: [],
            newMessage: '',
            pollInterval: null,
            isAutoScroll: true,
            showTplMenu: false,
            savedTemplates: [],

            lightbox: { open: false, url: '', type: '' },
            attachments: [],

            isEditing: false,
            editTarget: null,
            savingEdit: false,
        }
    },
    computed: {
        userTitle() { return this.user ? (this.user.first_name || this.user.username) : '' },
    },
    watch: {
        activeUserId: {
            immediate: true,
            handler(newId) {
                this.stopPolling()
                if (!newId) {
                    this.user = null; this.messages = []; this.savedTemplates = []
                    return
                }
                this.fetchUser(newId).then(() => this.fetchTemplates())
                this.fetchMessages(newId)
                this.startPolling()
            },
        },
        messages: { handler() { this.$nextTick(this.scrollToBottom) }, deep: true },
    },
    methods: {
        fetchUser(userId) {
            return fetch(`/api/chats/${userId}/info`)
                .then(r => r.json())
                .then(d => (this.user = d, d))
                .catch(e => (console.error(e), this.user = null))
        },
        fetchMessages(userId) {
            fetch(`/api/chats/${userId}/messages`)
                .then(r => r.json())
                .then(d => { this.messages = d })
                .catch(console.error)
        },
        fetchTemplates() {
            if (!this.user || !this.user.bot_id) return
            fetch(`/api/bots/${this.user.bot_id}/templates`)
                .then(r => r.json())
                .then(list => { this.savedTemplates = list.filter(t => t.type === 'custom') })
                .catch(e => (console.error(e), this.savedTemplates = []))
        },
        async applyTemplate(tpl) {
            this.showTplMenu = false
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            try {
                const r = await fetch(`/api/chats/${this.user.id}/send-template`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
                    body: JSON.stringify({ template_id: tpl.id }),
                })
                if (!r.ok) throw new Error(`HTTP ${r.status}`)
                const newMsgs = await r.json()
                this.messages.push(...newMsgs)
                this.fetchMessages(this.user.id)
                this.$nextTick(this.scrollToBottom)
            } catch (e) { console.error('Ошибка при отправке шаблона:', e) }
        },

        // ------- редактирование/удаление -------
        startEdit(msg) {
            this.isEditing = true
            this.editTarget = msg
            this.newMessage = msg.text || ''
            if (this.attachments.length) {
                this.attachments.forEach(a => a.preview && URL.revokeObjectURL(a.preview))
                this.attachments = []
            }
            this.$nextTick(() => this.$refs.textarea?.focus())
        },
        cancelEdit() {
            this.isEditing = false
            this.savingEdit = false
            this.editTarget = null
            this.newMessage = ''
        },
        async confirmEdit() {
            if (!this.isEditing || !this.editTarget || !this.user) return
            const msgId = this.editTarget.id
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            this.savingEdit = true
            try {
                const r = await fetch(`/api/chats/${this.user.id}/messages/${msgId}/update`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
                    body: JSON.stringify({ text: this.newMessage }),
                })
                if (!r.ok) throw new Error(`HTTP ${r.status}`)
                const payload = await r.json()
                const i = this.messages.findIndex(m => m.id === msgId)
                if (i !== -1) { this.messages[i].text = payload.text; this.messages[i].is_edited = true }
                this.cancelEdit()
            } catch (e) {
                console.error('Ошибка редактирования:', e)
                this.savingEdit = false
            }
        },
        async deleteMessage(msg) {
            if (!this.user) return
            if (!window.confirm('Удалить сообщение у пользователя?')) return
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            try {
                const r = await fetch(`/api/chats/${this.user.id}/messages/${msg.id}/delete`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: token ? { 'X-CSRF-TOKEN': token } : undefined,
                })
                if (!r.ok) throw new Error(`HTTP ${r.status}`)
                const i = this.messages.findIndex(m => m.id === msg.id)
                if (i !== -1) this.messages[i].is_deleted = true
                if (this.isEditing && this.editTarget?.id === msg.id) this.cancelEdit()
            } catch (e) { console.error('Ошибка удаления:', e) }
        },

        submitHandler() { return this.isEditing ? this.confirmEdit() : this.sendMessage() },

        onTextareaKeydown(e) {
            if (e.isComposing) return
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.submitHandler() }
        },

        formatMessage(t){ return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') },
        formatDate(s){ if(!s) return ''; const d=new Date(s); if(isNaN(d)) return s;
            return `${String(d.getDate()).padStart(2,'0')}.${String(d.getMonth()+1).padStart(2,'0')}.${d.getFullYear()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}` },
        startPolling(){ this.pollInterval = setInterval(()=>{ if(this.activeUserId) this.fetchMessages(this.activeUserId) }, 5000) },
        stopPolling(){ clearInterval(this.pollInterval); this.pollInterval=null },
        scrollToBottom(){ const el=this.$refs.messagesEnd; if(el && this.isAutoScroll) el.scrollTop = el.scrollHeight },
        handleScroll(){ const el=this.$refs.messagesEnd; this.isAutoScroll = el.scrollTop + el.clientHeight >= el.scrollHeight - 30 },

        openPreview({file,url,type}){ if(type==='file') window.open(url,'_blank'); else this.lightbox={open:true,url,type} },
        closePreview(){ this.lightbox={open:false,url:'',type:''} },

        triggerFile(){ if(!this.isEditing) this.$refs.fileInput?.click() },
        handleFileSelect(e){ if(this.isEditing){ e.target.value=''; return }
            const files = Array.from(e.target.files||[]); this.addAttachments(files); e.target.value='' },
        handlePaste(e){ if(this.isEditing || !e.clipboardData) return
            const items=Array.from(e.clipboardData.items||[]); const files=[]
            for(const it of items){ if(it.kind==='file'){ const f=it.getAsFile(); if(f && f.size>0) files.push(f) } }
            if(files.length){ e.preventDefault(); this.addAttachments(files) } },
        addAttachments(files){
            for(const file of files){
                const isImg = file.type?.startsWith('image/')
                this.attachments.push({
                    id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
                    file, isImage: isImg, preview: isImg ? URL.createObjectURL(file) : null
                })
            }
        },
        removeAttachment(i){ const a=this.attachments[i]; if(a?.preview) URL.revokeObjectURL(a.preview); this.attachments.splice(i,1) },

        async sendMessage(){
            if(!this.user) return
            if(!this.newMessage.trim() && this.attachments.length===0) return
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            const fd = new FormData()
            fd.append('text', this.newMessage)
            this.attachments.forEach(a => fd.append('files[]', a.file, a.file.name))
            try{
                const r = await fetch(`/api/chats/${this.user.id}/messages`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: token ? { 'X-CSRF-TOKEN': token } : undefined,
                    body: fd,
                })
                if(!r.ok) throw new Error(`HTTP ${r.status}`)
                const payload = await r.json()
                if(Array.isArray(payload)) this.messages.push(...payload); else if(payload) this.messages.push(payload)
                this.newMessage=''; this.attachments.forEach(a=>a.preview && URL.revokeObjectURL(a.preview)); this.attachments=[]
                this.$nextTick(this.scrollToBottom)
            }catch(e){ console.error('Ошибка отправки:', e) }
        },
    },
    mounted(){ if(this.activeUserId) this.fetchTemplates(); window.addEventListener('keydown', e=>{ if(e.key==='Escape' && this.lightbox.open) this.closePreview() }) },
    beforeUnmount(){
        this.stopPolling()
        this.attachments.forEach(a=>a.preview && URL.revokeObjectURL(a.preview))
    }
}
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
