import './bootstrap';
import Alpine from 'alpinejs';
import { createApp } from 'vue';
import App from './App.vue';

window.Alpine = Alpine;
Alpine.start();

// монтируем единственное Vue-приложение в #vue-chat-app
createApp(App).mount('#vue-chat-app');



// // 1. Инициализируем реактивный ID активного чата из data-атрибута
// const el = document.getElementById('chat-app');
// const initial = el?.dataset.initialUserId || null;
// const activeUserId = ref(initial ? Number(initial) : null);
//
// // 2. Функция для переключения чата из Blade
// window.setActiveUser = (id) => {
//     activeUserId.value = Number(id);
// };
//
// const app = createApp({
//     setup() {
//         onMounted(() => {
//             // при монтировании можно сразу подсветить строку
//             if (activeUserId.value) window.setActiveUser(activeUserId.value);
//         });
//         return { activeUserId };
//     },
//     components: { ChatComponent },
//     // это рендерит именно наш компонент
//     template: `<chat-component :active-user-id="activeUserId" />`
// });
//
// app.mount('#chat-app');
// window.app = app;
